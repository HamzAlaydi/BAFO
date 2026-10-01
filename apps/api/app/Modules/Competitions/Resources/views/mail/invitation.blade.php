{{--
    User-entered values (names, titles) are in HTML elements, not Markdown lines, so Blade escapes
    them and Markdown can never turn them into links or formatting (Notifications mail theme note).
    Keep every line at column 0: indentation is Markdown code.
--}}
<x-mail::message>
<h1>{{ $greeting }}</h1>

<p>{{ $intro }}</p>

<p>{{ $reference }}</p>
@if ($closes)

{{ $closes }}
@endif
@if ($sponsoredLine)

**{{ $sponsoredLine }}**
@endif

<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

[{{ $declineText }}]({{ $declineUrl }})

{{ $outro }}
</x-mail::message>
