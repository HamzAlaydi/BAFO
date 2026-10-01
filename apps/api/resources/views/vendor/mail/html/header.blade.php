@props(['url'])
@php($brand = \App\Modules\Notifications\Support\MailBranding::class)
<tr>
<td class="header" align="center" dir="{{ $brand::direction() }}">
<a href="{{ $url }}" style="display: inline-block;" target="_blank" rel="noopener">
<img src="{{ $brand::logoUrl() }}" class="logo" width="40" height="40" alt="{{ trim(strip_tags((string) $slot)) }}" style="vertical-align: middle;">
<span class="brand-name" style="vertical-align: middle;">{{ trim(strip_tags((string) $slot)) }}</span>
</a>
</td>
</tr>
