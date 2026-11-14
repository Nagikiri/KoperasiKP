<?php

namespace App\Exports;

use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\TransaksiSimpanan;
use App\Support\Format;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Excel laporan per anggota (revisi R6): satu sheet "Laporan per Anggota"
 * dengan SATU BLOK PER ANGGOTA, sama dengan PDF. Lebar blok 8 kolom (A..H):
 *   1. pita judul anggota (merge A..H, abu-abu gelap, bold, status berwarna)
 *      + keterangan saldo nonaktif (R5) bila perlu
 *   2. tabel saldo 4 kolom sama lebar (A:B, C:D, E:F, G:H)
 *   3. pita "RIWAYAT TRANSAKSI — nama (no)" (merge A..H, abu-abu muda)
 *   4. tabel riwayat A..H dengan header sendiri, atau "Tidak ada transaksi ..."
 *   5. ringkasan total setor (A:D) dan total tarik (E:H) periode, bold, tetap angka
 *   6. satu baris kosong pemisah
 * Data dari array LaporanService::perAnggota(), tanpa query ulang.
 */
class LaporanSimpananExport implements WithMultipleSheets
{
    public const JUDUL = 'LAPORAN SIMPANAN PER ANGGOTA';

    public const TANPA_TRANSAKSI = 'Tidak ada transaksi pada periode ini.';

    public const TANPA_DATA = 'Tidak ada data sesuai filter.';

    public const KOLOM = 8;

    public const FORMAT_TOTAL_SETOR = '"Total setor periode: Rp" #,##0';

    public const FORMAT_TOTAL_TARIK = '"Total tarik periode: Rp" #,##0';

    /** Pasangan kolom tabel saldo (4 kolom sama lebar). */
    private const PASANGAN = [[1, 2], [3, 4], [5, 6], [7, 8]];

    /**
     * @param  array<string, mixed>  $laporan  hasil LaporanService::perAnggota() + dicetak_oleh
     */
    public function __construct(public readonly array $laporan) {}

    /**
     * @return array<int, LembarLaporan>
     */
    public function sheets(): array
    {
        return [$this->lembar()];
    }

    /**
     * Teks pita judul anggota (sama dengan PDF).
     *
     * @param  array<string, mixed>  $anggota
     */
    public static function pitaJudul(int $nomor, array $anggota): string
    {
        return "{$nomor}. {$anggota['no_anggota']} — {$anggota['nama']}   |   Bergabung: "
            .Format::tanggal($anggota['tanggal_bergabung'])
            .'   |   Status: '.Format::statusAnggota($anggota['status']);
    }

    /**
     * @param  array<string, mixed>  $anggota
     */
    public static function pitaRiwayat(array $anggota): string
    {
        return "RIWAYAT TRANSAKSI — {$anggota['nama']} ({$anggota['no_anggota']})";
    }

    public function lembar(): LembarLaporan
    {
        $dicetak = 'Dicetak pada '.Format::tanggalWaktu($this->laporan['dicetak_pada']).' oleh '.$this->laporan['dicetak_oleh'];

        $lembar = (new LembarLaporan('Laporan per Anggota', self::KOLOM))
            ->kepala(self::JUDUL, $this->laporan['periode']['label'], $this->laporan['filter_label'])
            ->ulangiBarisCetak(1, 6)
            ->lebarMinimum([1 => 6, 2 => 18, 3 => 18, 4 => 15, 5 => 16, 6 => 16, 7 => 16, 8 => 30])
            ->kakiCetak($dicetak);

        if ($this->laporan['anggota']->isEmpty()) {
            $lembar->penuh(self::TANPA_DATA, ['miring' => true]);
        }

        foreach ($this->laporan['anggota']->values() as $i => $anggota) {
            $this->blok($lembar, $i + 1, $anggota);
        }

        return $lembar->catatan($dicetak);
    }

    /**
     * @param  array<string, mixed>  $anggota
     */
    private function blok(LembarLaporan $lembar, int $nomor, array $anggota): void
    {
        $aktif = $anggota['status'] === Anggota::STATUS_AKTIF;

        // 1. Pita judul anggota.
        $lembar->penuh(self::pitaJudul($nomor, $anggota), [
            'tebal' => true,
            'ukuran' => 11,
            'latar' => LembarLaporan::ABU_GELAP,
            'tengah' => false,
            'pita' => 'judul',
            'statusAkhir' => ['teks' => Format::statusAnggota($anggota['status']), 'warna' => $aktif ? 'hijau' : 'merah'],
        ]);

        if (Anggota::perluKeteranganSaldo($anggota['status'], $anggota['total_saldo'])) {
            $lembar->penuh(Anggota::KETERANGAN_SALDO_NONAKTIF, ['miring' => true, 'ukuran' => 9, 'warnaTeks' => LembarLaporan::MERAH, 'tengah' => false]);
        }

        // 2. Tabel saldo, 4 kolom sama lebar.
        $judulSaldo = array_merge(array_column($anggota['saldo_per_jenis'], 'nama_jenis'), ['Total Saldo']);
        $nilaiSaldo = array_merge(array_column($anggota['saldo_per_jenis'], 'saldo'), [$anggota['total_saldo']]);

        $lembar->header($this->sebar($judulSaldo), merge: self::PASANGAN);
        $lembar->data($this->sebar($nilaiSaldo), [
            'uang' => array_column(self::PASANGAN, 0),
            'merge' => self::PASANGAN,
            'tebal' => true,
            'saldo' => true,
        ]);

        // 3. Pita riwayat.
        $lembar->penuh(self::pitaRiwayat($anggota), ['tebal' => true, 'latar' => LembarLaporan::ABU_PITA, 'pita' => 'riwayat']);

        // 4. Tabel riwayat dengan header sendiri.
        $lembar->header(['No', 'Tanggal', 'Jenis Simpanan', 'Jenis Transaksi', 'Jumlah', 'Saldo Akhir', 'Dicatat Oleh', 'Catatan']);

        if ($anggota['transaksi']->isEmpty()) {
            $lembar->penuh(self::TANPA_TRANSAKSI, ['miring' => true]);
        }

        foreach ($anggota['transaksi']->values() as $j => $transaksi) {
            /** @var TransaksiSimpanan $transaksi */
            $lembar->data([
                $j + 1,
                $transaksi->tanggal_transaksi,
                $transaksi->jenisSimpanan->nama_jenis,
                ucfirst($transaksi->jenis_transaksi),
                (float) $transaksi->jumlah,
                (float) $transaksi->saldo_akhir,
                $transaksi->staff->nama,
                $transaksi->keterangan ?? '-',
            ], [
                'tanggal' => [2],
                'uang' => [5, 6],
                'tengah' => [1],
                'wrap' => [8],
                'riwayat' => true,
                'warna' => [4 => $transaksi->jenis_transaksi === TransaksiSimpanan::SETOR ? 'hijau' : 'merah'],
            ]);
        }

        // 5. Ringkasan periode (angka tetap angka, label lewat format tampilan).
        $lembar->data(
            [$anggota['total_setor_periode'], null, null, null, $anggota['total_tarik_periode'], null, null, null],
            [
                'merge' => [[1, 4], [5, 8]],
                'tebal' => true,
                'format' => [1 => self::FORMAT_TOTAL_SETOR, 5 => self::FORMAT_TOTAL_TARIK],
                'ringkasan' => true,
            ],
        );

        // 6. Pemisah antar-blok.
        $lembar->kosong();
    }

    /**
     * [a, b, c, d] -> [a, null, b, null, c, null, d, null] untuk kolom berpasangan.
     *
     * @param  array<int, mixed>  $nilai
     * @return array<int, mixed>
     */
    private function sebar(array $nilai): array
    {
        $hasil = [];
        foreach ($nilai as $isi) {
            $hasil[] = $isi;
            $hasil[] = null;
        }

        return $hasil;
    }
}
