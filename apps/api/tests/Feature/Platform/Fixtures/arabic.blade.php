<!DOCTYPE html>
<html lang="{{ $locale }}" dir="{{ $direction }}">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>body { font-size: 11pt; } td { width: 50%; }</style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <p>{{ __('platform.enums.legal_document_code.terms') }}</p>
    <p>{{ $amount }}</p>
</body>
</html>
