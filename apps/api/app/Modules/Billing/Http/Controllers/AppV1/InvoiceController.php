<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\AppV1;

use App\Modules\Billing\Http\Resources\InvoiceResource;
use App\Modules\Billing\Models\Invoice;
use App\Support\Exceptions\ApiException;
use App\Support\Files\FileStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Invoices (perm `billing.view`), API.md §1.7: the paginated list (newest first), one invoice,
 * and its PDF (409 `invoice_pdf_not_ready` until the e-invoice is cleared and rendered).
 */
final class InvoiceController extends BillingController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Invoice::class);
        $organization = $this->organization($this->user($request));
        $perPage = min(100, max(1, $request->integer('per_page', 20)));

        $invoices = Invoice::query()
            ->where('organization_id', $organization->id)
            ->with(['lines', 'payment'])
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->paginate($perPage);

        return $this->paginated($invoices, InvoiceResource::class);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $this->authorize('view', $invoice);

        return $this->ok(InvoiceResource::make($invoice));
    }

    public function pdf(Invoice $invoice, FileStorage $files): StreamedResponse
    {
        $this->authorize('view', $invoice);

        $file = $invoice->pdfFile;

        if ($file === null || ! InvoiceResource::pdfAvailable($invoice)) {
            throw new ApiException('invoice_pdf_not_ready', 'billing.errors.invoice_pdf_not_ready', 409);
        }

        return $files->download($file);
    }
}
