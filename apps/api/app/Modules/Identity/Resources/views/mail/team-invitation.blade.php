{{--
    User-entered values (names, titles) are in HTML elements, not Markdown lines, so Blade escapes
    them and Markdown can never turn them into links or formatting (Notifications mail theme note).
    Keep every line at column 0: indentation is Markdown code.
--}}
<x-mail::message>
<h1>{{ $greeting }}</h1>

<p>{{ $intro }}</p>

<x-mail::button :url="$url">
{{ $action }}
</x-mail::button>

{{ $expires }}

{{ $ignore }}
</x-mail::message>
