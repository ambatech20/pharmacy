{{--
    Icons, shared by the signed-in layout and the sign-in page so the two
    cannot drift apart.

    The "?v=" is not decoration. Browsers cache icons far more stubbornly than
    ordinary files, and favicon.ico spent its whole life as a zero-byte
    placeholder: a browser that already fetched the empty file keeps showing a
    blank tab until the address changes. Taking the version from the files
    themselves means regenerating the icons (tools/assets/make-icons.ps1)
    busts every cache without anyone remembering to edit a number.

    The icon files sit at the web root, NOT in a public/icons/ folder. The
    live Apache has the stock `Alias /icons/` enabled, so anything at that
    address is answered by the web server's own sample images and never
    reaches the application - the folder looks correct in File Manager while
    every icon 404s.
--}}
@php
    $iconVersion = @filemtime(public_path('icon-32.png')) ?: '1';
@endphp
<link rel="icon" href="/favicon.ico?v={{ $iconVersion }}" sizes="16x16 32x32 48x48">
<link rel="icon" type="image/png" sizes="32x32" href="/icon-32.png?v={{ $iconVersion }}">
<link rel="icon" type="image/png" sizes="16x16" href="/icon-16.png?v={{ $iconVersion }}">
{{--
    iOS ignores the manifest's icons and paints transparency black, so this is
    a flattened 180px file rather than the uploaded logo.
--}}
<link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png?v={{ $iconVersion }}">
{{-- Chrome, Edge and Android read the icon set from here. --}}
<link rel="manifest" href="/manifest.webmanifest">
