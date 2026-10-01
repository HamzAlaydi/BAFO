<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BAFO mail preview</title>
<style>
body { font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, Arial, sans-serif; color: #1C1F26; background: #F9FAFA; margin: 0; padding: 32px; }
main { max-width: 720px; margin: 0 auto; background: #fff; border: 1px solid #E5E7EB; border-radius: 8px; padding: 24px 32px; }
h1 { font-size: 20px; margin: 0 0 4px; }
p { color: #6B7280; margin: 0 0 24px; }
table { width: 100%; border-collapse: collapse; }
td { padding: 10px 0; border-top: 1px solid #E5E7EB; }
code { font-size: 14px; }
a { color: #0B7A55; margin-inline-start: 16px; }
</style>
</head>
<body>
<main>
<h1>BAFO mail preview</h1>
<p>Local environment only. Sample data rendered through the real notifications, layout and theme.</p>
<table>
@foreach ($links as $type => $link)
<tr>
<td><code>{{ $type }}</code></td>
<td style="text-align: end">
<a href="{{ $link['ar'] }}" lang="ar">العربية</a>
<a href="{{ $link['en'] }}" lang="en">English</a>
</td>
</tr>
@endforeach
</table>
</main>
</body>
</html>
