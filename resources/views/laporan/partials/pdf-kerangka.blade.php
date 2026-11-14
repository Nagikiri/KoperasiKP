{{--
    Kerangka PDF bersama (revisi R2): A4 landscape, kepala dan kaki fixed
    (diulang di setiap halaman), gaya tabel seragam.
    Variabel: $judul, $periode, $filter, $dicetakPada, $dicetakOleh, $isi (view isi), $data (data untuk isi).
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $judul }}</title>
    <style>
        @page { margin: 4.1cm 1cm 1.4cm 1cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; }
        header { position: fixed; top: -3.7cm; left: 0; right: 0; height: 3.5cm; }
        footer { position: fixed; bottom: -1cm; left: 0; right: 0; height: 0.8cm; }
    </style>
    @include('laporan.partials.gaya')
</head>
<body class="lap-dokumen">
    <header>
        @include('laporan.partials.kepala', ['judul' => $judul, 'periode' => $periode, 'filter' => $filter])
    </header>

    <footer>
        @include('laporan.partials.kaki', ['dicetakPada' => $dicetakPada, 'dicetakOleh' => $dicetakOleh])
    </footer>

    @include($isi, ['laporan' => $data])
</body>
</html>
