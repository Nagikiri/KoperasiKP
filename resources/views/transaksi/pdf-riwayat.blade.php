{{--
    PDF Riwayat transaksi (F5 H.5.7), A4 landscape. Gaya setara PDF laporan: partial `laporan.partials.gaya` dan
    `laporan.partials.kepala` hanya di-include (tidak diubah); kerangka halaman sendiri di view ini.
    Header tabel diulang di tiap halaman (thead table-header-group dari gaya).
    Variabel: $kepala (judul, periode, filter, diunduh), $transaksi (Collection, urut lama → baru), $ringkasan.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $kepala['judul'] }}</title>
    <style>
        @page { margin: 1cm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; }
    </style>
    @include('laporan.partials.gaya')
</head>
<body class="lap-dokumen">
    @include('laporan.partials.kepala', ['judul' => $kepala['judul'], 'periode' => $kepala['periode'], 'filter' => $kepala['filter']])
    <p class="lap-keterangan" data-diunduh>{{ $kepala['diunduh'] }}</p>

    @include('transaksi.partials.riwayat-pdf-isi', ['transaksi' => $transaksi, 'ringkasan' => $ringkasan])
</body>
</html>
