<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Imports;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Actions\Vendors\CreateVendor;
use App\Modules\Integrations\Actions\Vendors\UpdateVendor;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ImportMode;
use App\Modules\Integrations\Enums\ImportRowErrorCode;
use App\Modules\Integrations\Enums\VendorSource;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Http\Requests\VendorRequest;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Models\Vendor;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Modules\Integrations\Services\Spreadsheets\SheetData;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetReader;
use App\Modules\Integrations\Services\Spreadsheets\SpreadsheetWriter;
use App\Support\Auth\Actor;
use App\Support\Exceptions\ApiException;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use App\Support\Files\FileStorage;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Runs a vendor import (ARCHITECTURE §14.7):
 *
 *   1. read the file (CSV or XLSX, ≤ 10 000 data rows) and map the header (case-insensitive);
 *   2. validate every row → `{row, column, code, message}` with the codes `required`,
 *      `invalid_format`, `unknown_region`, `unknown_category`, `duplicate_in_file` (and
 *      `vendor_email_taken`, see ImportRowErrorCode);
 *   3. `commit` upserts the valid rows (key: `external_system` + `external_id` through the
 *      `supplier` external ref when both are given, else the e-mail), source `import`;
 *      `validate` writes nothing;
 *   4. the errors file is the original columns plus `errors`, in the source format.
 *
 * CONTRACT-GAP (choices where §14.7 is silent):
 *   - `row` is the spreadsheet row number (the header is row 1), so users find it in Excel;
 *   - on update only the columns present in the file change, and an empty `status` keeps the
 *     current status (active on create);
 *   - an unknown ERP key whose e-mail matches a vendor updates that vendor and attaches the key
 *     (the same rule as the public upsert);
 *   - the errors file holds the rows with errors only;
 *   - a file without a header, without the `name` / `email` columns or over 10 000 rows fails the
 *     whole job (`failed` + `failure_message`).
 */
final readonly class VendorImportProcessor
{
    public function __construct(
        private SpreadsheetReader $reader,
        private SpreadsheetWriter $writer,
        private FileStorage $files,
        private FilesystemFactory $filesystems,
        private CreateVendor $create,
        private UpdateVendor $update,
        private ExternalRefs $externalRefs,
    ) {}

    public function process(ImportJob $job, Actor $actor): ImportOutcome
    {
        $source = File::query()->findOrFail($job->source_file_id);
        $organization = Organization::query()->findOrFail($job->organization_id);
        $localPath = $this->localCopy($source);

        try {
            [$header, $rows] = $this->read($localPath, $source->extension);
        } finally {
            @unlink($localPath);
        }

        $index = self::columnIndex($header);
        $missing = array_values(array_diff(VendorColumns::REQUIRED, array_keys($index)));

        if ($missing !== []) {
            throw new ImportFailed('integrations.import.failures.missing_columns', ['columns' => implode(', ', $missing)]);
        }

        $errors = [];
        $planned = $this->validate($organization, $rows, $index, $errors);
        $created = 0;
        $updated = 0;

        if ($job->mode === ImportMode::Commit) {
            foreach ($planned as $number => $row) {
                try {
                    if (DB::transaction(fn (): bool => $this->apply($organization, $row, $actor))) {
                        $created++;
                    } else {
                        $updated++;
                    }
                } catch (ApiException $e) {
                    unset($planned[$number]);
                    $column = $e->errorCode === 'external_ref_conflict' ? 'external_id' : 'email';
                    $errors[$number][] = self::error($number, $column, ImportRowErrorCode::VendorEmailTaken);
                }
            }
        }

        ksort($errors);
        $flat = array_merge([], ...array_values($errors));

        return new ImportOutcome(
            totalRows: count($rows),
            validRows: count($planned),
            createdRows: $created,
            updatedRows: $updated,
            errorRows: count($errors),
            errorsPreview: array_slice($flat, 0, (int) config('bafo.integrations.imports.preview_errors')),
            errorsFile: $errors === [] ? null : $this->errorsFile($job, $source, $header, $rows, $errors),
        );
    }

    /**
     * @return array{0: list<string>, 1: array<int, list<string>>} the header and the non-empty data rows by row number
     */
    private function read(string $path, string $extension): array
    {
        $header = null;
        $rows = [];
        $maxRows = (int) config('bafo.integrations.imports.max_rows');

        foreach ($this->reader->rows($path, $extension, VendorColumns::REQUIRED) as $number => $cells) {
            if (implode('', $cells) === '') {
                continue;
            }

            if ($header === null) {
                $header = $cells;

                continue;
            }

            $rows[$number] = $cells;

            if (count($rows) > $maxRows) {
                throw new ImportFailed('integrations.import.failures.too_many_rows', ['max' => (string) $maxRows]);
            }
        }

        if ($header === null) {
            throw new ImportFailed('integrations.import.failures.empty_file');
        }

        return [$header, $rows];
    }

    /**
     * @param  list<string>  $header
     * @return array<string, int> column name => cell index
     */
    private static function columnIndex(array $header): array
    {
        $index = [];

        foreach ($header as $position => $name) {
            $name = strtolower(trim(str_replace("\u{FEFF}", '', $name)));

            if (in_array($name, VendorColumns::ALL, true) && ! isset($index[$name])) {
                $index[$name] = $position;
            }
        }

        return $index;
    }

    /**
     * @param  array<int, list<string>>  $rows
     * @param  array<string, int>  $index
     * @param  array<int, list<array{row: int, column: string, code: string, message: string}>>  $errors
     * @return array<int, PlannedVendorRow>
     */
    private function validate(Organization $organization, array $rows, array $index, array &$errors): array
    {
        $regions = Region::query()->where('is_active', true)->pluck('id', 'code')->all();
        $categories = Category::query()->where('is_active', true)->pluck('id', 'code')->all();
        $seenEmails = [];
        $seenKeys = [];
        $planned = [];

        foreach ($rows as $number => $cells) {
            $value = static fn (string $column): string => isset($index[$column]) ? trim($cells[$index[$column]] ?? '') : '';
            $rowErrors = [];
            $fail = static function (string $column, ImportRowErrorCode $code) use (&$rowErrors, $number): void {
                $rowErrors[] = self::error($number, $column, $code);
            };

            $system = $value('external_system');
            $externalId = $value('external_id');

            if ($system !== '' && preg_match(ExternalRef::SYSTEM_PATTERN, $system) !== 1) {
                $fail('external_system', ImportRowErrorCode::InvalidFormat);
            }

            if ($system !== '' && $externalId === '') {
                $fail('external_id', ImportRowErrorCode::Required);
            } elseif ($system === '' && $externalId !== '') {
                $fail('external_system', ImportRowErrorCode::Required);
            } elseif (mb_strlen($externalId) > 120) {
                $fail('external_id', ImportRowErrorCode::InvalidFormat);
            }

            $email = mb_strtolower($value('email'));

            if ($value('name') === '') {
                $fail('name', ImportRowErrorCode::Required);
            }

            if ($email === '') {
                $fail('email', ImportRowErrorCode::Required);
            } elseif (filter_var($email, FILTER_VALIDATE_EMAIL) === false || mb_strlen($email) > 255) {
                $fail('email', ImportRowErrorCode::InvalidFormat);
            }

            foreach ([
                'name' => 200, 'name_en' => 200, 'contact_name' => 150, 'city' => 100,
            ] as $column => $max) {
                if (mb_strlen($value($column)) > $max) {
                    $fail($column, ImportRowErrorCode::InvalidFormat);
                }
            }

            foreach ([
                'cr_number' => VendorRequest::CR_PATTERN,
                'vat_number' => VendorRequest::VAT_PATTERN,
                'phone' => VendorRequest::PHONE_PATTERN,
            ] as $column => $pattern) {
                if ($value($column) !== '' && preg_match($pattern, $value($column)) !== 1) {
                    $fail($column, ImportRowErrorCode::InvalidFormat);
                }
            }

            $regionCode = strtoupper($value('region_code'));

            if ($regionCode !== '' && ! isset($regions[$regionCode])) {
                $fail('region_code', ImportRowErrorCode::UnknownRegion);
            }

            $categoryCodes = array_values(array_unique(array_filter(array_map(
                static fn (string $code): string => strtolower(trim($code)),
                explode(VendorColumns::LIST_SEPARATOR, $value('category_codes')),
            ), static fn (string $code): bool => $code !== '')));

            if (array_diff($categoryCodes, array_keys($categories)) !== []) {
                $fail('category_codes', ImportRowErrorCode::UnknownCategory);
            }

            $status = strtolower($value('status'));

            if ($status !== '' && ! in_array($status, [VendorStatus::Active->value, VendorStatus::Blocked->value], true)) {
                $fail('status', ImportRowErrorCode::InvalidFormat);
            }

            if ($rowErrors === [] && isset($seenEmails[$email])) {
                $fail('email', ImportRowErrorCode::DuplicateInFile);
            }

            $key = $system !== '' && $externalId !== '' ? $system."\n".$externalId : null;

            if ($rowErrors === [] && $key !== null && isset($seenKeys[$key])) {
                $fail('external_id', ImportRowErrorCode::DuplicateInFile);
            }

            if ($email !== '') {
                $seenEmails[$email] = true;
            }

            if ($key !== null) {
                $seenKeys[$key] = true;
            }

            if ($rowErrors === []) {
                $row = new PlannedVendorRow(
                    externalSystem: $key === null ? null : $system,
                    externalId: $key === null ? null : $externalId,
                    attributes: $this->attributes($index, $value, $email, $regionCode === '' ? null : $regions[$regionCode], $status),
                    categoryIds: isset($index['category_codes'])
                        ? array_map(static fn (string $code): int => (int) $categories[$code], $categoryCodes)
                        : null,
                );

                $row->target = $this->target($organization, $row);

                if ($row->target !== null && $this->emailTakenByAnother($organization, $row->target, $email)) {
                    $fail('email', ImportRowErrorCode::VendorEmailTaken);
                } else {
                    $planned[$number] = $row;
                }
            }

            if ($rowErrors !== []) {
                $errors[$number] = $rowErrors;
            }
        }

        return $planned;
    }

    /**
     * The attributes the row sets: every column present in the file (empty → null), except an
     * empty `status`.
     *
     * @param  array<string, int>  $index
     * @param  callable(string): string  $value
     * @return array<string, mixed>
     */
    private function attributes(array $index, callable $value, string $email, mixed $regionId, string $status): array
    {
        $attributes = ['email' => $email];

        foreach (['name', 'name_en', 'cr_number', 'vat_number', 'contact_name', 'phone', 'city'] as $column) {
            if (isset($index[$column])) {
                $attributes[$column] = $value($column) === '' ? null : $value($column);
            }
        }

        if (isset($index['region_code'])) {
            $attributes['region_id'] = is_numeric($regionId) ? (int) $regionId : null;
        }

        if ($status !== '') {
            $attributes['status'] = $status;
        }

        return $attributes;
    }

    private function target(Organization $organization, PlannedVendorRow $row): ?Vendor
    {
        if ($row->externalSystem !== null && $row->externalId !== null) {
            $vendorId = $this->externalRefs->findRefableId(
                $organization->id, 'vendor', $row->externalSystem, ExternalRefs::TYPE_SUPPLIER, $row->externalId,
            );

            if ($vendorId !== null) {
                return Vendor::query()->find($vendorId);
            }
        }

        return Vendor::query()
            ->where('organization_id', $organization->id)
            ->where('email', $row->attributes['email'])
            ->first();
    }

    private function emailTakenByAnother(Organization $organization, Vendor $target, string $email): bool
    {
        return $target->email !== $email && Vendor::query()
            ->where('organization_id', $organization->id)
            ->where('email', $email)
            ->whereKeyNot($target->id)
            ->exists();
    }

    /**
     * Applies one planned row. Returns true when a vendor was created.
     */
    private function apply(Organization $organization, PlannedVendorRow $row, Actor $actor): bool
    {
        $data = $row->attributes;

        if ($row->categoryIds !== null) {
            $data['category_ids'] = $row->categoryIds;
        }

        $created = $row->target === null;
        $vendor = $created
            ? $this->create->handle($organization, ['status' => VendorStatus::Active->value, ...$data], VendorSource::Import, $actor)
            : $this->update->handle($row->target, $data, $actor);

        if ($row->externalSystem !== null && $row->externalId !== null) {
            $this->externalRefs->put($vendor, $organization->id, $row->externalSystem, ExternalRefs::TYPE_SUPPLIER, $row->externalId);
        }

        return $created;
    }

    /**
     * @param  list<string>  $header
     * @param  array<int, list<string>>  $rows
     * @param  array<int, list<array{row: int, column: string, code: string, message: string}>>  $errors
     */
    private function errorsFile(ImportJob $job, File $source, array $header, array $rows, array $errors): File
    {
        $format = $source->extension === 'xlsx' ? ExportFormat::Xlsx : ExportFormat::Csv;
        $width = count($header);
        $lines = [[...$header, 'errors']];

        foreach ($errors as $number => $rowErrors) {
            $cells = array_pad(array_slice($rows[$number] ?? [], 0, $width), $width, '');
            $lines[] = [...$cells, implode(' | ', array_map(
                static fn (array $error): string => $error['column'].': '.$error['message'],
                $rowErrors,
            ))];
        }

        $bytes = $this->writer->write($format, [new SheetData('errors', $lines)]);

        return $this->files->storeContents(
            $bytes,
            'vendors-import-errors-'.$job->public_id.'.'.$format->value,
            $format === ExportFormat::Xlsx ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' : 'text/csv',
            FilePurpose::ImportErrors,
            $job->organization_id,
        );
    }

    /**
     * @return array{row: int, column: string, code: string, message: string}
     */
    private static function error(int $row, string $column, ImportRowErrorCode $code): array
    {
        return ['row' => $row, 'column' => $column, 'code' => $code->value, 'message' => $code->message($column)];
    }

    /**
     * The source bytes in a local temporary file (openspout reads from a path).
     */
    private function localCopy(File $file): string
    {
        $path = tempnam(sys_get_temp_dir(), 'bafo-import-');
        $stream = $this->filesystems->disk($file->disk)->readStream($file->path);

        if ($path === false || ! is_resource($stream)) {
            throw new RuntimeException("Cannot read import source [{$file->public_id}].");
        }

        $target = fopen($path, 'wb');

        if ($target === false) {
            throw new RuntimeException('Cannot write a temporary file.');
        }

        stream_copy_to_stream($stream, $target);
        fclose($target);
        fclose($stream);

        return $path;
    }
}
