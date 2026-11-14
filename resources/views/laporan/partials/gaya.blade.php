{{--
    Gaya bersama kedua laporan (per anggota dan keseluruhan), dipakai di PDF
    dan pratinjau web (print preview). Revisi R2: kepala, tabel, warna, dan
    kaki seragam. Excel meniru gaya ini (App\Exports\Sheets\LembarLaporan).
--}}
<style>
    .lap-dokumen { font-family: 'DejaVu Sans', sans-serif; font-size: 10px; color: #000; }
    /* Kepala */
    .lap-kop { text-align: center; border-bottom: 2px solid #000; padding-bottom: 4px; margin-bottom: 6px; }
    .lap-kop-nama { font-size: 13px; font-weight: bold; }
    .lap-judul { text-align: center; font-size: 16px; font-weight: bold; margin: 0; }
    .lap-periode { text-align: center; margin: 2px 0 0; }
    .lap-keterangan { text-align: center; font-size: 8px; color: #555; margin: 2px 0 8px; }
    /* Judul bagian (laporan keseluruhan) */
    .lap-bagian { text-align: center; font-weight: bold; font-size: 11px; margin: 12px 0 4px; }
    /* Tabel */
    .lap-tabel { width: 100%; border-collapse: collapse; font-size: 10px; }
    .lap-tabel th, .lap-tabel td { border: 1px solid #000; padding: 3px 4px; vertical-align: top; }
    .lap-tabel thead th, .lap-tabel tfoot th { background: #d9d9d9; text-align: center; }
    .lap-tabel tfoot th { font-weight: bold; }
    .lap-tabel thead { display: table-header-group; }
    .lap-tabel tr { page-break-inside: avoid; }
    .lap-tabel .lap-kosong td { text-align: center; font-style: italic; }
    /* Blok per anggota (revisi R6): lebar penuh sama, tidak terpotong bila muat, jarak antar-blok */
    .lap-blok { width: 100%; margin: 0 0 16px 0; page-break-inside: avoid; }
    .lap-blok .lap-tabel { margin: 0; table-layout: fixed; }
    .lap-pita-judul td { background: #a6a6a6; font-weight: bold; font-size: 11px; }
    .lap-saldo th, .lap-saldo td { width: 25%; }
    .lap-saldo .lap-nilai-saldo td { font-weight: bold; }
    .lap-pita-riwayat td { background: #ededed; font-weight: bold; text-align: center; }
    .lap-riwayat th:nth-child(1) { width: 4%; }
    .lap-riwayat th:nth-child(8) { width: 24%; }
    .lap-ringkasan-anggota td { width: 50%; font-weight: bold; }
    .lap-tabel .lap-catatan-nonaktif td { font-style: italic; font-size: 9px; color: #b91c1c; }
    .lap-angka { text-align: right; white-space: nowrap; }
    .lap-tengah { text-align: center; }
    /* Warna status dan jenis transaksi (sama dengan Excel) */
    .lap-hijau { color: #15803d; font-weight: bold; }
    .lap-merah { color: #b91c1c; font-weight: bold; }
    /* Kaki */
    .lap-kaki { font-size: 8px; color: #555; margin-top: 10px; }
</style>
