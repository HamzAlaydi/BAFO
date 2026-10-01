<?php

declare(strict_types=1);

use App\Support\Pdf\MpdfPdfRenderer;
use App\Support\Pdf\PdfRenderer;
use Illuminate\Support\Facades\View;

beforeEach(function () {
    View::addNamespace('platform_test', __DIR__.'/Fixtures');
});

/**
 * The text-showing operators of an uncompressed PDF, as UTF-16BE strings.
 *
 * @return list<string>
 */
function platformPdfTexts(string $pdf): array
{
    preg_match_all('/\[\((.*?)\)\] TJ|\((.*?)\) Tj/s', $pdf, $matches);

    $strings = array_map(static fn (string $a, string $b): string => $a !== '' ? $a : $b, $matches[1], $matches[2]);

    return array_map(
        static fn (string $s): string => (string) preg_replace_callback('/\\\\(\d{3}|.)/s', static fn (array $m): string => ctype_digit($m[1]) ? chr((int) octdec($m[1])) : match ($m[1]) {
            'n' => "\n", 'r' => "\r", 't' => "\t", 'b' => "\x08", 'f' => "\f", default => $m[1],
        }, $s),
        $strings,
    );
}

function platformUtf16(string $text): string
{
    return mb_convert_encoding($text, 'UTF-16BE', 'UTF-8');
}

it('is bound to the mpdf driver', function () {
    expect(app(PdfRenderer::class))->toBeInstanceOf(MpdfPdfRenderer::class);
});

it('renders a view to PDF bytes', function () {
    $pdf = app(PdfRenderer::class)->render('platform_test::arabic', ['title' => 'Invoice', 'amount' => '12,500.00'], 'en');

    expect($pdf)->toStartWith('%PDF-')->toContain('%%EOF');
});

it('shapes Arabic text into joined presentation forms, right to left', function () {
    $renderer = app()->make(MpdfPdfRenderer::class, ['options' => ['compress' => false]]);

    $pdf = $renderer->render('platform_test::arabic', ['title' => 'بافو', 'amount' => '12,500.00 ر.س'], 'ar');
    $texts = implode('|', platformPdfTexts($pdf));

    // بافو = beh alef feh waw. Shaped and laid out right to left, it is drawn as
    // waw (final) · feh (initial) · alef (final) · beh (initial).
    expect($texts)->toContain("\xFE\xEE\xFE\xD3\xFE\x8E\xFE\x91")
        // The unshaped (isolated, logical-order) code points never reach the page.
        ->not->toContain(platformUtf16('بافو'))
        // The view rendered in the requested locale.
        ->and($pdf)->toStartWith('%PDF-');

    // Digits stay left to right inside the Arabic line.
    expect($texts)->toContain(platformUtf16('12,500.00'));
});

it('renders the view in the requested locale and restores the previous one', function () {
    app()->setLocale('en');
    $renderer = app()->make(MpdfPdfRenderer::class, ['options' => ['compress' => false]]);

    $pdf = $renderer->render('platform_test::arabic', ['title' => 'x', 'amount' => '1'], 'ar');

    expect(implode('|', platformPdfTexts($pdf)))->not->toContain(platformUtf16('Terms'))
        ->and(app()->getLocale())->toBe('en');
});

it('isolates numbers in text nodes with bdi', function () {
    $html = '<html><head><title>2026</title><style>td { width: 50%; }</style></head>'
        .'<body><p class="w-10">المبلغ 12,500.00 ر.س في 2026-10-01 الساعة 10:30</p>'
        .'<p><bdi>99</bdi> &#1575; &amp; 7</p></body></html>';

    expect(MpdfPdfRenderer::isolateNumbers($html))->toBe(
        '<html><head><title>2026</title><style>td { width: 50%; }</style></head>'
        .'<body><p class="w-10">المبلغ <bdi>12,500.00</bdi> ر.س في <bdi>2026-10-01</bdi> الساعة <bdi>10:30</bdi></p>'
        .'<p><bdi>99</bdi> &#1575; &amp; <bdi>7</bdi></p></body></html>'
    );
});
