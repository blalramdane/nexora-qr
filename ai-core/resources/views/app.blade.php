<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0b1020">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    @unless(request()->routeIs('public.menu.show'))
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="apple-touch-icon" sizes="180x180" href="/icons/apple-touch-icon.png">
    @endunless
    <link rel="icon" type="image/svg+xml" href="/icons/nexora-512.svg">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link rel="stylesheet" href="https://fonts.bunny.net/css?family=cairo:400,500,600,700,800,900&display=swap">
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/app.tsx'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
