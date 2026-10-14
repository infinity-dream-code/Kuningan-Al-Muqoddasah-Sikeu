@php
    $logoFile = config('app.logo', 'Muqoddasah.png');
    $logoPath = public_path($logoFile);
    $logoVersion = is_file($logoPath) ? filemtime($logoPath) : time();
    $logoUrl = asset($logoFile) . '?v=' . $logoVersion;
@endphp
<link rel="icon" type="image/jpeg" href="{{ $logoUrl }}">
<link rel="shortcut icon" type="image/jpeg" href="{{ $logoUrl }}">
<link rel="apple-touch-icon" href="{{ $logoUrl }}">
<meta name="application-name" content="{{ config('app.name') }}">
