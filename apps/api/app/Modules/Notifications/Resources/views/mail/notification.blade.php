{{--
    Catalogue notification mail (ARCHITECTURE §11.1), rendered in the recipient's language with
    the shared BAFO theme (resources/views/vendor/mail). Texts are HTML paragraphs, not Markdown,
    so user-entered values (competition titles, reasons, messages) are escaped by Blade and can
    never become links or formatting. Keep every line at column 0: indentation is Markdown code.
--}}
<x-mail::message>
<p>{{ $greeting }}</p>

<p>{{ $intro }}</p>

@foreach ($lines as $line)
<p>{{ $line }}</p>

@endforeach
<x-mail::button :url="$actionUrl">
{{ $actionText }}
</x-mail::button>

<p>{{ $salutation }}<br>{{ $signature }}</p>
</x-mail::message>
