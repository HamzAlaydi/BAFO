<?php

declare(strict_types=1);

namespace App\Support\Files;

use App\Support\Exceptions\ApiException;
use Closure;
use finfo;
use Illuminate\Contracts\Filesystem\Factory as FilesystemFactory;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Stores, serves and deletes files (ARCHITECTURE §4.6). Paths are
 * `{purpose}/{yyyy}/{mm}/{public_id}.{ext}` on the purpose's disk (`public` or `private`).
 *
 * Uploads are checked against the purpose: the extension must be allowed, the MIME type
 * sniffed with finfo must match the extension, and the size must be within the limit
 * (422 `file_type_not_allowed` / `file_too_large`, messages on the `file` field).
 */
final readonly class FileStorage
{
    /**
     * Sniffed MIME types accepted per extension. The first one is stored as `mime_type`.
     * OOXML documents are zip containers and legacy Office files are OLE containers, which
     * some libmagic versions report generically.
     *
     * @var array<string, list<string>>
     */
    private const array MIME_TYPES = [
        'png' => ['image/png'],
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'webp' => ['image/webp'],
        'pdf' => ['application/pdf'],
        'doc' => ['application/msword', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'xls' => ['application/vnd.ms-excel', 'application/vnd.ms-office', 'application/CDFV2', 'application/x-ole-storage'],
        'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/zip'],
        'xlsx' => ['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/zip'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'csv' => ['text/csv', 'text/plain', 'application/csv'],
    ];

    public function __construct(private FilesystemFactory $filesystems) {}

    public function store(UploadedFile $file, FilePurpose $purpose, ?int $organizationId, ?int $userId): File
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($extension, $purpose->allowedExtensions(), true)) {
            throw $this->typeNotAllowed($purpose);
        }

        $maxBytes = $purpose->maxBytes();

        if (in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($maxBytes !== null && (int) $file->getSize() > $maxBytes)) {
            throw $this->tooLarge($maxBytes ?? 0);
        }

        $realPath = $file->getRealPath();

        if (! $file->isValid() || $realPath === false) {
            throw $this->typeNotAllowed($purpose);
        }

        $sniffed = (new finfo(FILEINFO_MIME_TYPE))->file($realPath);

        if (! is_string($sniffed) || ! in_array($sniffed, self::MIME_TYPES[$extension] ?? [], true)) {
            throw $this->typeNotAllowed($purpose);
        }

        return $this->persist(
            purpose: $purpose,
            extension: $extension,
            originalName: $file->getClientOriginalName(),
            sizeBytes: (int) $file->getSize(),
            sha256: (string) hash_file('sha256', $realPath),
            organizationId: $organizationId,
            userId: $userId,
            write: static fn (Filesystem $disk, string $directory, string $name): mixed => $disk->putFileAs($directory, $file, $name),
        );
    }

    /**
     * Stores bytes the platform generated (invoice and report PDFs, import errors, exports).
     */
    public function storeContents(string $bytes, string $name, string $mime, FilePurpose $purpose, ?int $organizationId): File
    {
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        if (! in_array($extension, $purpose->allowedExtensions(), true)) {
            throw new InvalidArgumentException("Extension [{$extension}] is not allowed for purpose [{$purpose->value}].");
        }

        return $this->persist(
            purpose: $purpose,
            extension: $extension,
            originalName: $name,
            sizeBytes: strlen($bytes),
            sha256: hash('sha256', $bytes),
            organizationId: $organizationId,
            userId: null,
            write: static fn (Filesystem $disk, string $directory, string $file): bool => $disk->put($directory.'/'.$file, $bytes),
            mime: $mime,
        );
    }

    /**
     * Deletes the row now and the bytes once the surrounding transaction (if any) commits.
     */
    public function delete(File $file): void
    {
        $disk = $file->disk;
        $path = $file->path;

        $file->delete();

        DB::afterCommit(fn () => $this->disk($disk)->delete($path));
    }

    /**
     * Streams the file with `Content-Disposition: attachment` (RFC 5987 `filename*` for
     * non-ASCII names). 404 when the bytes are missing.
     */
    public function download(File $file): StreamedResponse
    {
        $disk = $this->disk($file->disk);

        if (! $disk->exists($file->path)) {
            throw new NotFoundHttpException;
        }

        if (! $disk instanceof FilesystemAdapter) {
            throw new RuntimeException("Disk [{$file->disk}] cannot stream downloads.");
        }

        return $disk->download($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            'Content-Length' => (string) $file->size_bytes,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Absolute URL of a public-disk file (logos, avatars); null for private files.
     */
    public function publicUrl(File $file): ?string
    {
        if (! $file->isPublic()) {
            return null;
        }

        $disk = $this->disk($file->disk);

        if (! $disk instanceof FilesystemAdapter) {
            return null;
        }

        $url = $disk->url($file->path);

        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://') ? $url : url($url);
    }

    /**
     * @param  Closure(Filesystem, string, string): mixed  $write
     */
    private function persist(
        FilePurpose $purpose,
        string $extension,
        string $originalName,
        int $sizeBytes,
        string $sha256,
        ?int $organizationId,
        ?int $userId,
        Closure $write,
        ?string $mime = null,
    ): File {
        $publicId = File::newPublicId();
        $directory = $purpose->value.'/'.Date::now()->utc()->format('Y/m');
        $name = $publicId.'.'.$extension;
        $disk = $this->disk($purpose->disk());

        if ($write($disk, $directory, $name) === false) {
            throw new RuntimeException("Could not write [{$directory}/{$name}] to disk [{$purpose->disk()}].");
        }

        try {
            return File::query()->create([
                'public_id' => $publicId,
                'organization_id' => $organizationId,
                'uploaded_by_user_id' => $userId,
                'purpose' => $purpose,
                'disk' => $purpose->disk(),
                'path' => $directory.'/'.$name,
                'original_name' => self::cleanName($originalName, $extension),
                'mime_type' => $mime ?? self::MIME_TYPES[$extension][0],
                'extension' => $extension,
                'size_bytes' => $sizeBytes,
                'sha256' => $sha256,
            ]);
        } catch (Throwable $e) {
            $disk->delete($directory.'/'.$name);

            throw $e;
        }
    }

    private function disk(string $name): Filesystem
    {
        return $this->filesystems->disk($name);
    }

    /**
     * The client's file name without directories or control characters, at most 255 chars.
     */
    private static function cleanName(string $name, string $extension): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $name));

        return $name === '' ? 'file.'.$extension : mb_substr($name, -255);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function trans(string $key, array $replace): string
    {
        $message = __($key, $replace);

        return is_string($message) ? $message : $key;
    }

    private function typeNotAllowed(FilePurpose $purpose): ApiException
    {
        $extensions = implode(', ', $purpose->allowedExtensions());

        return new ApiException(
            errorCode: 'file_type_not_allowed',
            status: 422,
            errors: ['file' => [self::trans('errors.file_type_not_allowed', ['extensions' => $extensions])]],
            replace: ['extensions' => $extensions],
            details: ['allowed_extensions' => $purpose->allowedExtensions()],
        );
    }

    private function tooLarge(int $maxBytes): ApiException
    {
        $megabytes = (string) intdiv($maxBytes, 1024 * 1024);

        return new ApiException(
            errorCode: 'file_too_large',
            status: 422,
            errors: ['file' => [self::trans('errors.file_too_large', ['max' => $megabytes])]],
            replace: ['max' => $megabytes],
            details: ['max_bytes' => $maxBytes],
        );
    }
}
