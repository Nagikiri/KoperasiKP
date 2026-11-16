@php
    // Menu sidebar berkelompok tanpa dropdown (F1 §4.1). 'aktif' = pola request()->routeIs().
    $menu = [
        ['grup' => null, 'item' => [
            ['route' => 'dashboard', 'aktif' => 'dashboard', 'label' => 'Dashboard', 'ikon' => 'home'],
        ]],
        ['grup' => 'Anggota', 'item' => [
            ['route' => 'anggota.index', 'aktif' => 'anggota.*', 'label' => 'Data Anggota', 'ikon' => 'users'],
        ]],
        ['grup' => 'Transaksi', 'item' => [
            ['route' => 'transaksi.setor.create', 'aktif' => 'transaksi.setor.*', 'label' => 'Setor', 'ikon' => 'arrow-down-tray'],
            ['route' => 'transaksi.tarik.create', 'aktif' => 'transaksi.tarik.*', 'label' => 'Tarik', 'ikon' => 'arrow-up-tray'],
            ['route' => 'transaksi.riwayat', 'aktif' => 'transaksi.riwayat', 'label' => 'Riwayat Transaksi', 'ikon' => 'clock'],
        ]],
        ['grup' => 'Laporan', 'item' => [
            ['route' => 'laporan.keseluruhan', 'aktif' => 'laporan.keseluruhan*', 'label' => 'Rekap Keseluruhan', 'ikon' => 'chart-bar'],
            ['route' => 'laporan.anggota', 'aktif' => 'laporan.anggota*', 'label' => 'Laporan per Anggota', 'ikon' => 'document-text'],
        ]],
    ];

    // Jejak topbar (F2b C.2): hanya untuk halaman turunan Data Anggota. Halaman setingkat menu
    // tidak punya jejak karena judulnya sudah ada di konten. Halaman boleh menimpanya lewat slot "breadcrumb".
    // Nama anggota diambil dari model hasil route model binding (tanpa query tambahan).
    $jejak = null;
    $anggotaRute = request()->route('anggota');
    $namaAnggota = $anggotaRute instanceof \App\Models\Anggota ? $anggotaRute->nama : null;
    $induk = ['label' => 'Data Anggota', 'url' => route('anggota.index')];

    if (request()->routeIs('anggota.create')) {
        $jejak = [$induk, ['label' => 'Tambah']];
    } elseif ($namaAnggota !== null && request()->routeIs('anggota.show')) {
        $jejak = [$induk, ['label' => $namaAnggota]];
    } elseif ($namaAnggota !== null && request()->routeIs('anggota.edit')) {
        $jejak = [$induk, ['label' => $namaAnggota], ['label' => 'Ubah']];
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Laravel') }}</title>

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-stone-100 text-stone-900">
        @include('layouts.partials.sidebar', ['menu' => $menu])

        <div class="ml-64 min-h-screen min-w-0">
            @include('layouts.partials.topbar', ['jejak' => $jejak, 'breadcrumb' => $breadcrumb ?? null])

            <main class="px-8 py-8">
                <div class="mx-auto max-w-7xl">
                    <x-ui.flash class="mb-6" />

                    {{ $slot }}
                </div>
            </main>
        </div>

        @include('partials.cegah-kirim-ganda')
    </body>
</html>
