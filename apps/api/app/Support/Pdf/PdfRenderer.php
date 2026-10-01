<?php

declare(strict_types=1);

namespace App\Support\Pdf;

/**
 * Renders a Blade view to PDF bytes (ARCHITECTURE §4.11, D17). The driver is selected by
 * `bafo.platform.pdf.driver` (env PDF_DRIVER); `mpdf` is the only one today.
 *
 *     $bytes = app(PdfRenderer::class)->render('billing::pdf.invoice', ['invoice' => $invoice], 'ar');
 *
 * The view renders with the given locale active and receives `$locale` and `$direction`
 * (`rtl` for Arabic, `ltr` otherwise). Numbers in text are isolated in <bdi>.
 */
interface PdfRenderer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $view, array $data, string $locale): string;
}
