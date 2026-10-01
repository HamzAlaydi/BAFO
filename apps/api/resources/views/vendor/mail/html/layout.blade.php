{{--
    BAFO mail layout (Notifications module, ARCHITECTURE §3.2): every markdown mail uses it,
    including the token-carrying mails of Identity and Competitions (§11.5). The direction
    follows the mail's language: RTL for Arabic, LTR for English. Inline `dir` and `text-align`
    are set on each block, because mail clients ignore or strip CSS direction rules.
--}}
@php($brand = \App\Modules\Notifications\Support\MailBranding::class)
@php($dir = $brand::direction())
@php($start = $brand::startSide())
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $brand::locale() }}" dir="{{ $dir }}">
<head>
<title>{{ __('notifications.mail.brand') }}</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
<meta name="color-scheme" content="light">
<meta name="supported-color-schemes" content="light">
<style>
@media only screen and (max-width: 600px) {
.inner-body {
width: 100% !important;
}

.footer {
width: 100% !important;
}
}

@media only screen and (max-width: 500px) {
.button {
width: 100% !important;
}
}
</style>
{!! $head ?? '' !!}
</head>
<body dir="{{ $dir }}">

<table class="wrapper" width="100%" cellpadding="0" cellspacing="0" role="presentation" dir="{{ $dir }}">
<tr>
<td align="center">
<table class="content" width="100%" cellpadding="0" cellspacing="0" role="presentation" dir="{{ $dir }}">
{!! $header ?? '' !!}

<!-- Email Body -->
<tr>
<td class="body" width="100%" cellpadding="0" cellspacing="0" style="border: hidden !important;">
<table class="inner-body" align="center" width="570" cellpadding="0" cellspacing="0" role="presentation" dir="{{ $dir }}">
<!-- Body content -->
<tr>
<td class="content-cell" dir="{{ $dir }}" align="{{ $start }}" style="direction: {{ $dir }}; text-align: {{ $start }};">
{!! Illuminate\Mail\Markdown::parse($slot) !!}

{!! $subcopy ?? '' !!}
</td>
</tr>
</table>
</td>
</tr>

{!! $footer ?? '' !!}
</table>
</td>
</tr>
</table>
</body>
</html>
