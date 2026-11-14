<?php

namespace App\Exports;

use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\TransaksiSimpanan;
use App\Support\Format;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel laporan keseluruhan (revisi R3): sheet "Ringkasan", "Saldo Anggota",
 * "Transaksi", masing-masing dengan kepala laporan seragam dan gaya seperti
 * PDF. Data dari array LaporanService::keseluruhan(), tanpa query ulang.
 */
class LaporanKeseluruhanExport implements WithMultipleSheets
{
    public const JUDUL = 'LAPORAN SIMPANAN KESELURUHAN';

    public const KOSONG = 'Tidak ada data sesuai filter.';

    /**
     * @param  array<string, mixed>  $laporan  hasil LaporanService::keseluruhan() + dicetak_oleh
     */
    public function __construct(public readonly array $laporan) {}

    /**
     * @return array<int, LembarLaporan>
     */
    public function sheets(): array
    {
        return [
            $this->lembarRingkasan(),
            $this->lembarSaldoAnggota(),
            $this->lembarTransaksi(),
        ];
    }

    private function dicetak(): string
    {
        return 'Dicetak pada '.Format::tanggalWaktu($this->laporan['dicetak_pada']).' oleh '.$this->laporan['dicetak_oleh'];
    }

    private function lembarBaru(string $nama, int $jumlahKolom): LembarLaporan
    {
        return (new LembarLaporan($nama, $jumlahKolom))
            ->kepala(self::JUDUL, $this->laporan['periode']['label'], $this->laporan['filter_label'])
            ->kakiCetak($this->dicetak());
    }

    public function lembarRingkasan(): LembarLaporan
    {
        $lembar = $this->lembarBaru('Ringkasan', 4)
            ->header(['Jenis Simpanan', 'Total Setor', 'Total Tarik', 'Saldo Akhir Periode'], judulCetak: true)
            ->lebarMinimum([1 => 22, 2 => 18, 3 => 18, 4 => 20]);

        foreach ($this->laporan['ringkasan'] as $jenis) {
            $lembar->data([$jenis['nama_jenis'], $jenis['total_setor'], $jenis['total_tarik'], $jenis['saldo_akhir_periode']], ['uang' => [2, 3, 4]]);
        }

        $total = $this->laporan['total'];

        return $lembar
            ->data(['Total', $total['total_setor'], $total['total_tarik'], $total['saldo_akhir_periode']], [
                'uang' => [2, 3, 4], 'tebal' => true, 'latar' => LembarLaporan::ABU, 'tengah' => [1],
            ])
            ->kosong()
            ->catatan($this->dicetak());
    }

    public function lembarSaldoAnggota(): LembarLaporan
    {
        $namaJenis = $this->laporan['ringkasan']->pluck('nama_jenis')->all();
        $n = 6 + count($namaJenis);

        $lembar = $this->lembarBaru('Saldo Anggota', $n)
            ->header(array_merge(['No', 'No. Anggota', 'Nama', 'Status', 'Tanggal Bergabung'], $namaJenis, ['Total Saldo']), judulCetak: true);

        if ($this->laporan['saldo_anggota']->isEmpty()) {
            $lembar->penuh(self::KOSONG, ['miring' => true]);
        }

        foreach ($this->laporan['saldo_anggota']->values() as $i => $anggota) {
            $lembar->data(
                array_merge(
                    [$i + 1, $anggota['no_anggota'], $anggota['nama'], Format::statusAnggota($anggota['status']), $anggota['tanggal_bergabung']],
                    array_column($anggota['saldo_per_jenis'], 'saldo'),
                    [$anggota['total_saldo']],
                ),
                [
                    'tanggal' => [5],
                    'uang' => range(6, $n),
                    'tengah' => [1, 4],
                    'warna' => [4 => $anggota['status'] === Anggota::STATUS_AKTIF ? 'hijau' : 'merah'],
                ],
            );
        }

        return $lembar->kosong()->catatan($this->dicetak());
    }

    public function lembarTransaksi(): LembarLaporan
    {
        $lembar = $this->lembarBaru('Transaksi', 10)
            ->header(['No', 'Tanggal', 'No. Anggota', 'Nama', 'Jenis Simpanan', 'Jenis Transaksi', 'Jumlah', 'Saldo Akhir', 'Dicatat Oleh', 'Catatan'], judulCetak: true)
            ->lebarMinimum([10 => 24]);

        if ($this->laporan['transaksi']->isEmpty()) {
            $lembar->penuh(self::KOSONG, ['miring' => true]);
        }

        foreach ($this->laporan['transaksi']->values() as $i => $transaksi) {
            /** @var TransaksiSimpanan $transaksi */
            $lembar->data(
                [
                    $i + 1,
                    $transaksi->tanggal_transaksi,
                    $transaksi->anggota->no_anggota,
                    Format::namaAnggota($transaksi->anggota->nama, $transaksi->anggota->status),
                    $transaksi->jenisSimpanan->nama_jenis,
                    ucfirst($transaksi->jenis_transaksi),
                    (float) $transaksi->jumlah,
                    (float) $transaksi->saldo_akhir,
                    $transaksi->staff->nama,
                    $transaksi->keterangan ?? '-',
                ],
                [
                    'tanggal' => [2],
                    'uang' => [7, 8],
                    'tengah' => [1],
                    'wrap' => [10],
                    'warna' => [6 => $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR ? 'hijau' : 'merah'],
                ],
            );
        }

        return $lembar->kosong()->catatan($this->dicetak());
    }
}
