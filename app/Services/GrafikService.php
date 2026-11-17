<?php

namespace App\Services;

use App\Models\TransaksiSimpanan;
use Carbon\Carbon;

class GrafikService
{
    /**
     * Data grafik "Arus simpanan" di dashboard (F2b A.1) untuk enam periode
     * sekaligus, supaya pilihan periode di browser tidak butuh request baru.
     *
     * Dua query saja: (1) saldo sebelum awal rentang terpanjang (lima_tahun),
     * (2) agregasi setor/tarik (nominal dan jumlah baris) per tanggal dalam rentang lima_tahun.
     * Pengelompokan ke ember dilakukan di PHP, tanpa fungsi tanggal khusus database.
     *
     * Asumsi: tidak ada transaksi bertanggal masa depan (BR-10), jadi saldo ember
     * terakhir setiap periode sama dengan total saldo rekapPerJenis().
     *
     * @return array{default: string, periode: array<string, array<string, mixed>>, ada_data: bool}
     */
    public function arusSimpanan(): array
    {
        $hariIni = now()->startOfDay();

        $definisi = [
            'minggu' => ['nama' => '7 hari terakhir', 'ember' => $this->emberHarian($hariIni, 7, 'D j', 'l, j F Y')],
            'bulan' => ['nama' => '30 hari terakhir', 'ember' => $this->emberHarian($hariIni, 30, 'j M', 'j F Y')],
            'tiga_bulan' => ['nama' => '3 bulan terakhir', 'ember' => $this->emberMingguan($hariIni, 13)],
            'enam_bulan' => ['nama' => '6 bulan terakhir', 'ember' => $this->emberBulanan($hariIni, 6, 'M')],
            'tahun' => ['nama' => '12 bulan terakhir', 'ember' => $this->emberBulanan($hariIni, 12, 'M y')],
            'lima_tahun' => ['nama' => '5 tahun terakhir', 'ember' => $this->emberKuartalan($hariIni, 20)],
        ];

        // F2d I.1.4: rentang query kembali ke awal lima_tahun (perbandingan "sebelumnya" dihapus).
        $mulaiQuery = $definisi['lima_tahun']['ember'][0]['mulai'];

        $saldoSebelumRentang = round((float) TransaksiSimpanan::query()
            ->whereDate('tanggal_transaksi', '<', $mulaiQuery->toDateString())
            ->selectRaw(
                "COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0)
               - COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS saldo"
            )
            ->value('saldo'), 2);

        /** @var array<string, array{setor: float, tarik: float, jumlah_setor: int, jumlah_tarik: int}> $perTanggal kunci Y-m-d, urut lama -> baru */
        $perTanggal = [];
        TransaksiSimpanan::query()
            ->whereDate('tanggal_transaksi', '>=', $mulaiQuery->toDateString())
            ->whereDate('tanggal_transaksi', '<=', $hariIni->toDateString())
            ->selectRaw("tanggal_transaksi,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0) AS total_setor,
                COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS total_tarik,
                COUNT(CASE WHEN jenis_transaksi = 'setor' THEN 1 END) AS jumlah_setor,
                COUNT(CASE WHEN jenis_transaksi = 'tarik' THEN 1 END) AS jumlah_tarik")
            ->groupBy('tanggal_transaksi')
            ->orderBy('tanggal_transaksi')
            ->toBase()
            ->get()
            ->each(function ($baris) use (&$perTanggal) {
                $kunci = Carbon::parse($baris->tanggal_transaksi)->toDateString();
                $perTanggal[$kunci] ??= ['setor' => 0.0, 'tarik' => 0.0, 'jumlah_setor' => 0, 'jumlah_tarik' => 0];
                $perTanggal[$kunci]['setor'] += (float) $baris->total_setor;
                $perTanggal[$kunci]['tarik'] += (float) $baris->total_tarik;
                $perTanggal[$kunci]['jumlah_setor'] += (int) $baris->jumlah_setor;
                $perTanggal[$kunci]['jumlah_tarik'] += (int) $baris->jumlah_tarik;
            });

        $periode = [];
        foreach ($definisi as $kunci => $item) {
            $periode[$kunci] = $this->hitungPeriode($item['nama'], $item['ember'], $perTanggal, $saldoSebelumRentang, $hariIni);

            // F2d B.1: ember khusus tampilan Batang untuk periode yang embernya > 13.
            if (count($item['ember']) > 13) {
                $mulai = $item['ember'][0]['mulai'];
                $emberBatang = $kunci === 'lima_tahun'
                    ? $this->emberBatangTahunan($mulai, $hariIni)
                    : $this->emberBatangMingguan($mulai, $hariIni);
                $batang = $this->hitungPeriode('', $emberBatang, $perTanggal, $saldoSebelumRentang, $hariIni);
                $periode[$kunci]['batang'] = array_intersect_key($batang, array_flip(['label', 'label_lengkap', 'setor', 'tarik', 'saldo']));
            }
        }

        return [
            'default' => 'enam_bulan',
            'periode' => $periode,
            'ada_data' => $perTanggal !== [],
        ];
    }

    /**
     * @param  list<array{mulai: Carbon, label: string, label_lengkap: string}>  $ember
     * @param  array<string, array{setor: float, tarik: float}>  $perTanggal
     * @return array<string, mixed>
     */
    private function hitungPeriode(string $nama, array $ember, array $perTanggal, float $saldoSebelumRentang, Carbon $hariIni): array
    {
        $jumlahEmber = count($ember);
        $batas = array_map(fn (array $e) => $e['mulai']->toDateString(), $ember);
        $mulai = $batas[0];

        $setor = array_fill(0, $jumlahEmber, 0.0);
        $tarik = array_fill(0, $jumlahEmber, 0.0);
        $saldoAwal = $saldoSebelumRentang;
        $jumlahSetor = 0;
        $jumlahTarik = 0;

        foreach ($perTanggal as $tanggal => $nilai) {
            if ($tanggal < $mulai) {
                // Transaksi sebelum rentang periode ini ikut ke saldo awal.
                $saldoAwal += $nilai['setor'] - $nilai['tarik'];

                continue;
            }

            $jumlahSetor += $nilai['jumlah_setor'];
            $jumlahTarik += $nilai['jumlah_tarik'];

            // Ember = batas awal terbesar yang <= tanggal (string Y-m-d bisa dibandingkan langsung).
            $indeks = $jumlahEmber - 1;
            while ($indeks > 0 && $batas[$indeks] > $tanggal) {
                $indeks--;
            }

            $setor[$indeks] += $nilai['setor'];
            $tarik[$indeks] += $nilai['tarik'];
        }

        $saldo = [];
        $berjalan = $saldoAwal;
        for ($i = 0; $i < $jumlahEmber; $i++) {
            $berjalan += $setor[$i] - $tarik[$i];
            $saldo[] = round($berjalan, 2);
        }

        $setor = array_map(fn (float $n) => round($n, 2), $setor);
        $tarik = array_map(fn (float $n) => round($n, 2), $tarik);
        $totalSetor = round(array_sum($setor), 2);
        $totalTarik = round(array_sum($tarik), 2);

        return [
            'nama' => $nama,
            'mulai' => $mulai,
            'selesai' => $hariIni->toDateString(),
            'label' => array_column($ember, 'label'),
            'label_lengkap' => array_column($ember, 'label_lengkap'),
            'setor' => $setor,
            'tarik' => $tarik,
            'saldo' => $saldo,
            'ringkasan' => [
                'total_setor' => $totalSetor,
                'total_tarik' => $totalTarik,
                'bersih' => round($totalSetor - $totalTarik, 2),
                'saldo_awal' => round($saldoAwal, 2),
                'saldo_akhir' => $saldo[$jumlahEmber - 1],
                // F2d C.4 (I.1.4: `sebelumnya` dihapus)
                'jumlah_setor' => $jumlahSetor,
                'jumlah_tarik' => $jumlahTarik,
            ],
        ];
    }

    /**
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberHarian(Carbon $hariIni, int $jumlah, string $formatLabel, string $formatLengkap): array
    {
        $ember = [];
        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $hari = $hariIni->copy()->subDays($i);
            $ember[] = [
                'mulai' => $hari,
                'label' => $hari->translatedFormat($formatLabel),
                'label_lengkap' => $hari->translatedFormat($formatLengkap),
            ];
        }

        return $ember;
    }

    /**
     * Minggu dimulai Senin. Label lengkap memakai rentang Senin–Minggu kalender,
     * termasuk untuk minggu berjalan.
     *
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberMingguan(Carbon $hariIni, int $jumlah): array
    {
        $seninIni = $hariIni->copy()->startOfWeek(Carbon::MONDAY);

        $ember = [];
        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $senin = $seninIni->copy()->subWeeks($i);
            $ember[] = [
                'mulai' => $senin,
                'label' => $senin->translatedFormat('j M'),
                'label_lengkap' => $this->rentangMinggu($senin, $senin->copy()->addDays(6)),
            ];
        }

        return $ember;
    }

    /**
     * Contoh: `4–10 November 2026`, `28 Oktober–3 November 2026`,
     * `29 Desember 2025–4 Januari 2026`. Pemisah en dash (U+2013) tanpa spasi.
     * Tanggal awal = akhir ditulis satu tanggal saja (`12 November 2026`).
     */
    private function rentangMinggu(Carbon $senin, Carbon $minggu): string
    {
        $akhir = $minggu->translatedFormat('j F Y');

        if ($senin->isSameDay($minggu)) {
            return $akhir;
        }

        if ($senin->year !== $minggu->year) {
            return $senin->translatedFormat('j F Y')."\u{2013}".$akhir;
        }

        if ($senin->month !== $minggu->month) {
            return $senin->translatedFormat('j F')."\u{2013}".$akhir;
        }

        return $senin->day."\u{2013}".$akhir;
    }

    /**
     * Ember batang periode `bulan` (F2d B.1, H.3.2): per minggu kalender (Senin), ember pertama
     * mulai dari `mulai` periode dan ember terakhir berhenti di hari ini (dipotong di kedua ujung).
     *
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberBatangMingguan(Carbon $mulai, Carbon $hariIni): array
    {
        $ember = [];
        $awal = $mulai->copy();
        while ($awal->lte($hariIni)) {
            $akhir = $awal->copy()->endOfWeek(Carbon::SUNDAY)->startOfDay()->min($hariIni);
            $ember[] = [
                'mulai' => $awal,
                'label' => $awal->translatedFormat('j M'),
                'label_lengkap' => $this->rentangMinggu($awal, $akhir),
            ];
            $awal = $akhir->copy()->addDay();
        }

        return $ember;
    }

    /**
     * Ember batang periode `lima_tahun` (F2d B.1, H.3.2): per tahun kalender, dipotong di kedua
     * ujung. Tahun penuh ditulis `2023`; tahun terpotong `April–Desember 2022`.
     *
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberBatangTahunan(Carbon $mulai, Carbon $hariIni): array
    {
        $ember = [];
        $awal = $mulai->copy();
        while ($awal->lte($hariIni)) {
            $akhir = $awal->copy()->endOfYear()->startOfDay()->min($hariIni);
            $penuh = $awal->isSameDay($awal->copy()->startOfYear()) && $akhir->isSameDay($awal->copy()->endOfYear()->startOfDay());

            if ($penuh) {
                $lengkap = (string) $awal->year;
            } elseif ($awal->month === $akhir->month) {
                $lengkap = $awal->translatedFormat('F Y');
            } else {
                $lengkap = $awal->translatedFormat('F')."\u{2013}".$akhir->translatedFormat('F Y');
            }

            $ember[] = [
                'mulai' => $awal,
                'label' => (string) $awal->year,
                'label_lengkap' => $lengkap,
            ];
            $awal = $akhir->copy()->addDay();
        }

        return $ember;
    }

    /**
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberBulanan(Carbon $hariIni, int $jumlah, string $formatLabel): array
    {
        $bulanIni = $hariIni->copy()->startOfMonth();

        $ember = [];
        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $bulan = $bulanIni->copy()->subMonthsNoOverflow($i);
            $ember[] = [
                'mulai' => $bulan,
                'label' => $bulan->translatedFormat($formatLabel),
                'label_lengkap' => $bulan->translatedFormat('F Y'),
            ];
        }

        return $ember;
    }

    /**
     * @return list<array{mulai: Carbon, label: string, label_lengkap: string}>
     */
    private function emberKuartalan(Carbon $hariIni, int $jumlah): array
    {
        $kuartalIni = $hariIni->copy()->startOfQuarter();

        $ember = [];
        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $awal = $kuartalIni->copy()->subMonthsNoOverflow($i * 3);
            $ember[] = [
                'mulai' => $awal,
                'label' => 'K'.$awal->quarter.' '.$awal->format('y'),
                'label_lengkap' => 'Kuartal '.$awal->quarter.' '.$awal->year,
            ];
        }

        return $ember;
    }
}
