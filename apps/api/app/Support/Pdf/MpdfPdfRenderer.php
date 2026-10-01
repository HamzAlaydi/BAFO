<?php

declare(strict_types=1);

namespace App\Support\Pdf;

use Illuminate\Contracts\Translation\Translator;
use Illuminate\Contracts\View\Factory as ViewFactory;
use Illuminate\Filesystem\Filesystem;
use Mpdf\Config\ConfigVariables;
use Mpdf\Config\FontVariables;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;

/**
 * The `mpdf` PdfRenderer driver (ARCHITECTURE §4.11). mPDF shapes Arabic locally (OpenType
 * layout), so no external service is needed.
 *
 * - Arabic documents are RTL.
 * - Font: IBM Plex Sans Arabic when its files are in resources/fonts/
 *   (IBMPlexSansArabic-Regular.ttf, IBMPlexSansArabic-Bold.ttf), otherwise mPDF's built-in
 *   DejaVu Sans, which covers Arabic and Latin.
 * - Digit runs in text nodes are wrapped in <bdi> so amounts, dates and codes keep their
 *   order inside Arabic text (text already inside <bdi>, <style>, <script> or <head> is left
 *   alone).
 */
final readonly class MpdfPdfRenderer implements PdfRenderer
{
    public const string PLEX_FONT = 'ibmplexsansarabic';

    public const string FALLBACK_FONT = 'dejavusans';

    /**
     * @param  array{compress?: bool, font_dir?: string, temp_dir?: string}  $options
     */
    public function __construct(
        private ViewFactory $views,
        private Translator $translator,
        private Filesystem $files,
        private array $options = [],
    ) {}

    public function render(string $view, array $data, string $locale): string
    {
        $direction = $locale === 'ar' ? 'rtl' : 'ltr';
        $html = $this->renderView($view, [...$data, 'locale' => $locale, 'direction' => $direction], $locale);

        $mpdf = $this->makeMpdf();
        $mpdf->SetDirectionality($direction);
        $mpdf->WriteHTML(self::isolateNumbers($html));

        return $mpdf->Output('', Destination::STRING_RETURN);
    }

    /**
     * Wraps runs of digits (with their separators: 12,500.00 · 2026-10-01 · 10:30:00) found in
     * text nodes in <bdi>. Character references (&#1234;) are never touched.
     */
    public static function isolateNumbers(string $html): string
    {
        $parts = preg_split('/(<[^>]*>)/u', $html, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return $html;
        }

        $skipDepth = 0;
        $out = '';

        foreach ($parts as $part) {
            if ($part !== '' && $part[0] === '<') {
                if (preg_match('/^<\s*(\/?)\s*(bdi|style|script|head|title)\b/i', $part, $m) === 1) {
                    $skipDepth = max(0, $skipDepth + ($m[1] === '/' ? -1 : 1));
                }

                $out .= $part;

                continue;
            }

            $out .= $skipDepth > 0 ? $part : (string) preg_replace_callback(
                '/&#?[A-Za-z0-9]+;|(\d+(?:[.,:\/\-]\d+)*)/u',
                static fn (array $m): string => isset($m[1]) ? '<bdi>'.$m[1].'</bdi>' : $m[0],
                $part,
            );
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function renderView(string $view, array $data, string $locale): string
    {
        $previous = $this->translator->getLocale();
        $this->translator->setLocale($locale);

        try {
            return $this->views->make($view, $data)->render();
        } finally {
            $this->translator->setLocale($previous);
        }
    }

    private function makeMpdf(): Mpdf
    {
        $tempDir = $this->options['temp_dir'] ?? storage_path('framework/cache/mpdf');
        $this->files->ensureDirectoryExists($tempDir);

        $fontDir = $this->options['font_dir'] ?? resource_path('fonts');
        $hasPlex = $this->files->exists($fontDir.'/IBMPlexSansArabic-Regular.ttf');

        /** @var array{fontDir: list<string>} $configDefaults */
        $configDefaults = (new ConfigVariables)->getDefaults();
        /** @var array{fontdata: array<string, array<string, mixed>>} $fontDefaults */
        $fontDefaults = (new FontVariables)->getDefaults();

        $fontData = $fontDefaults['fontdata'];

        if ($hasPlex) {
            $bold = $this->files->exists($fontDir.'/IBMPlexSansArabic-Bold.ttf') ? 'IBMPlexSansArabic-Bold.ttf' : 'IBMPlexSansArabic-Regular.ttf';
            $fontData[self::PLEX_FONT] = [
                'R' => 'IBMPlexSansArabic-Regular.ttf',
                'B' => $bold,
                'useOTL' => 0xFF,
                'useKashida' => 75,
            ];
        }

        $mpdf = new Mpdf([
            'mode' => 'utf-8',
            'format' => 'A4',
            'tempDir' => $tempDir,
            'fontDir' => $hasPlex ? [...$configDefaults['fontDir'], $fontDir] : $configDefaults['fontDir'],
            'fontdata' => $fontData,
            'default_font' => $hasPlex ? self::PLEX_FONT : self::FALLBACK_FONT,
            'autoScriptToLang' => true,
            'autoLangToFont' => false,
            'margin_top' => 16,
            'margin_bottom' => 16,
            'margin_left' => 14,
            'margin_right' => 14,
        ]);

        $mpdf->SetCompression($this->options['compress'] ?? true);

        return $mpdf;
    }
}
