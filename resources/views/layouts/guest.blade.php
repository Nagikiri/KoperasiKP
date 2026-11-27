<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=ibm-plex-sans:400,500,600|ibm-plex-mono:400,500&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-stone-100 text-stone-900">
        <div class="relative flex min-h-screen items-center justify-center overflow-hidden px-6 py-12">
            {{--
                Hiasan datar (F1 §5, palet A): lingkaran arang kiri atas dengan aksen lingkaran merah kecil
                di tepinya, pola titik stone-300 kanan atas, cincin emas kanan bawah.
            --}}
            <div aria-hidden="true" class="pointer-events-none absolute -left-40 -top-40 h-[420px] w-[420px] rounded-full bg-arang"></div>
            <div aria-hidden="true" class="pointer-events-none absolute left-[162px] top-[162px] h-[72px] w-[72px] rounded-full bg-merah"></div>
            <div aria-hidden="true" data-pola-latar class="pointer-events-none absolute right-0 top-0 h-72 w-[28rem] opacity-60"
                 style="background-image: url(&quot;data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='20' height='20'%3E%3Ccircle cx='2' cy='2' r='1.5' fill='%23d6d3d1'/%3E%3C/svg%3E&quot;);"></div>
            <div aria-hidden="true" class="pointer-events-none absolute -bottom-16 -right-16 h-[200px] w-[200px] rounded-full border-[24px] border-emas"></div>

            <div class="relative w-full max-w-5xl">
                {{ $slot }}
            </div>
        </div>

        @include('partials.cegah-kirim-ganda')
    </body>
</html>
