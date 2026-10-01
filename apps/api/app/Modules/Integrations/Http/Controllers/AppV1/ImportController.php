<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Http\Controllers\AppV1;

use App\Modules\Integrations\Actions\Imports\StartVendorImport;
use App\Modules\Integrations\Enums\ExportFormat;
use App\Modules\Integrations\Enums\ImportType;
use App\Modules\Integrations\Http\Concerns\ResolvesTenant;
use App\Modules\Integrations\Http\Requests\StoreImportRequest;
use App\Modules\Integrations\Http\Resources\ImportJobResource;
use App\Modules\Integrations\Models\ImportJob;
use App\Modules\Integrations\Services\Imports\VendorImportTemplate;
use App\Support\Auth\CurrentActor;
use App\Support\Http\Controllers\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/**
 * CSV/XLSX vendor import in the dashboard (API.md §1.9, ARCHITECTURE §14.7). The route group
 * applies `integrations.access` (`integrations.manage`; no `api_enabled` needed).
 */
final class ImportController extends ApiController
{
    use ResolvesTenant;

    public function template(Request $request, ImportType $type, VendorImportTemplate $template): Response
    {
        $format = ExportFormat::from((string) ($request->validate([
            'format' => ['sometimes', 'string', Rule::enum(ExportFormat::class)],
        ])['format'] ?? ExportFormat::Csv->value));

        $name = $type->value.'-import-template.'.$format->value;

        return new Response($template->build($format), 200, [
            'Content-Type' => $format === ExportFormat::Xlsx
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function store(StoreImportRequest $request, StartVendorImport $start): JsonResponse
    {
        $job = $start->handle($this->organization($request), $request->upload(), $request->importType(), $request->mode(), CurrentActor::get());

        return $this->ok(new ImportJobResource($job->refresh()), status: 202);
    }

    public function show(ImportJob $job): JsonResponse
    {
        $this->authorize('view', $job);

        return $this->ok(new ImportJobResource($job));
    }
}
