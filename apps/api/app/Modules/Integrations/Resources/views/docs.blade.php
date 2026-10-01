<!doctype html>
<html lang="{{ app()->getLocale() }}" dir="ltr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('integrations.docs.title') }}</title>
    <meta name="description" content="{{ __('integrations.docs.description') }}">
    <style>
        body { margin: 0; }
        .bafo-docs-note { font: 14px/1.5 system-ui, sans-serif; padding: 8px 16px; background: #E6F7F1; color: #0B7A55; }
        .bafo-docs-note a { color: #0B7A55; }
    </style>
</head>
<body>
    <div class="bafo-docs-note">
        {{ __('integrations.docs.description') }}
        · <a href="{{ $specUrl }}">openapi.json</a>
        · <a href="{{ $yamlUrl }}">openapi.yaml</a>
    </div>
    <script id="api-reference" data-url="{{ $specUrl }}"></script>
    <script src="https://cdn.jsdelivr.net/npm/@scalar/api-reference"></script>
</body>
</html>
