<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use App\Services\GrafikService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * F2b A.4: data grafik "Arus simpanan" untuk enam periode.
 */
class GrafikServiceTest extends TestCase
{
    use RefreshDatabase;

    private const PERIODE = ['minggu', 'bulan', 'tiga_bulan', 'enam_bulan', 'tahun', 'lima_tahun'];

    private User $admin;

    private Anggota $anggota;

    protected function setUp(): void
    {
        parent::setUp();

        // Kamis, 12 November 2026.
        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();
        $this->anggota = Anggota::factory()->create(['tanggal_bergabung' => '2015-01-01']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function arus(): array
    {
        return app(GrafikService::class)->arusSimpanan();
    }

    public function test_kunci_periode_dan_default(): void
    {
        $arus = $this->arus();

        $this->assertSame(['default', 'periode', 'ada_data'], array_keys($arus));
        $this->assertSame('enam_bulan', $arus['default']);
        $this->assertSame(self::PERIODE, array_keys($arus['periode']));

        foreach ($arus['periode'] as $kunci => $periode) {
            // F2d B.1: kunci `batang` hanya untuk periode yang embernya > 13.
            $batang = in_array($kunci, ['bulan', 'lima_tahun'], true) ? ['batang'] : [];
            $this->assertSame(['nama', 'mulai', 'selesai', 'label', 'label_lengkap', 'setor', 'tarik', 'saldo', 'ringkasan', ...$batang], array_keys($periode));
            if ($batang) {
                $this->assertSame(['label', 'label_lengkap', 'setor', 'tarik', 'saldo'], array_keys($periode['batang']));
            }
            $this->assertSame(['total_setor', 'total_tarik', 'bersih', 'saldo_awal', 'saldo_akhir', 'jumlah_setor', 'jumlah_tarik', 'sebelumnya'], array_keys($periode['ringkasan']));
            $this->assertSame(['total_setor', 'total_tarik'], array_keys($periode['ringkasan']['sebelumnya']));
        }

        $this->assertSame('6 bulan terakhir', $arus['periode']['enam_bulan']['nama']);
    }

    public function test_jumlah_ember_dan_label_urut_lama_ke_baru(): void
    {
        $periode = $this->arus()['periode'];

        $jumlah = ['minggu' => 7, 'bulan' => 30, 'tiga_bulan' => 13, 'enam_bulan' => 6, 'tahun' => 12, 'lima_tahun' => 20];
        foreach ($jumlah as $kunci => $n) {
            foreach (['label', 'label_lengkap', 'setor', 'tarik', 'saldo'] as $seri) {
                $this->assertCount($n, $periode[$kunci][$seri], "{$kunci}.{$seri} harus {$n} ember.");
            }
        }

        $this->assertSame(['Jum 6', 'Sab 7', 'Min 8', 'Sen 9', 'Sel 10', 'Rab 11', 'Kam 12'], $periode['minggu']['label']);
        $this->assertSame('Jumat, 6 November 2026', $periode['minggu']['label_lengkap'][0]);
        $this->assertSame('Kamis, 12 November 2026', $periode['minggu']['label_lengkap'][6]);

        $this->assertSame('14 Okt', $periode['bulan']['label'][0]);
        $this->assertSame('12 Nov', $periode['bulan']['label'][29]);
        $this->assertSame('12 November 2026', $periode['bulan']['label_lengkap'][29]);

        $this->assertSame('17 Agt', $periode['tiga_bulan']['label'][0]);
        $this->assertSame('9 Nov', $periode['tiga_bulan']['label'][12]);
        $this->assertSame("9\u{2013}15 November 2026", $periode['tiga_bulan']['label_lengkap'][12]);

        $this->assertSame(['Jun', 'Jul', 'Agt', 'Sep', 'Okt', 'Nov'], $periode['enam_bulan']['label']);
        $this->assertSame(['Juni 2026', 'Juli 2026', 'Agustus 2026', 'September 2026', 'Oktober 2026', 'November 2026'], $periode['enam_bulan']['label_lengkap']);

        $this->assertSame('Des 25', $periode['tahun']['label'][0]);
        $this->assertSame('Nov 26', $periode['tahun']['label'][11]);
        $this->assertSame('Desember 2025', $periode['tahun']['label_lengkap'][0]);
    }

    public function test_batas_mulai_dan_selesai_setiap_periode(): void
    {
        $periode = $this->arus()['periode'];

        $harapan = [
            'minggu' => '2026-11-06',
            'bulan' => '2026-10-14',
            'tiga_bulan' => '2026-08-17',
            'enam_bulan' => '2026-06-01',
            'tahun' => '2025-12-01',
            'lima_tahun' => '2022-01-01',
        ];

        foreach ($harapan as $kunci => $mulai) {
            $this->assertSame($mulai, $periode[$kunci]['mulai'], "mulai {$kunci}");
            $this->assertSame('2026-11-12', $periode[$kunci]['selesai'], "selesai {$kunci}");
        }

        $this->assertTrue(Carbon::parse($periode['tiga_bulan']['mulai'])->isMonday());
    }

    public function test_total_setor_dan_tarik_termasuk_batas_awal_dan_akhir(): void
    {
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 1000000, '2026-11-05'); // tepat sebelum minggu
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 200000, '2026-11-06');  // batas awal minggu
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 50000, '2026-11-12');   // batas akhir (hari ini)
        $this->transaksi(JenisSimpanan::POKOK, 'setor', 300000, '2026-05-31');     // tepat sebelum enam_bulan
        $this->transaksi(JenisSimpanan::WAJIB, 'setor', 40000, '2026-06-01');      // batas awal enam_bulan

        $periode = $this->arus()['periode'];

        $minggu = $periode['minggu'];
        $this->assertSame(['total_setor' => 200000.0, 'total_tarik' => 50000.0, 'bersih' => 150000.0, 'saldo_awal' => 1340000.0, 'saldo_akhir' => 1490000.0,
            'jumlah_setor' => 1, 'jumlah_tarik' => 1, 'sebelumnya' => ['total_setor' => 1000000.0, 'total_tarik' => 0.0]], $minggu['ringkasan']);
        $this->assertSame(200000.0, $minggu['setor'][0]);
        $this->assertSame(50000.0, $minggu['tarik'][6]);

        $enam = $periode['enam_bulan'];
        $this->assertSame([40000.0, 0.0, 0.0, 0.0, 0.0, 1200000.0], $enam['setor']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 50000.0], $enam['tarik']);
        $this->assertSame(['total_setor' => 1240000.0, 'total_tarik' => 50000.0, 'bersih' => 1190000.0, 'saldo_awal' => 300000.0, 'saldo_akhir' => 1490000.0,
            'jumlah_setor' => 3, 'jumlah_tarik' => 1, 'sebelumnya' => ['total_setor' => 300000.0, 'total_tarik' => 0.0]], $enam['ringkasan']);

        $this->assertSame(1490000.0, $periode['lima_tahun']['ringkasan']['total_setor'] - $periode['lima_tahun']['ringkasan']['total_tarik']);
    }

    public function test_saldo_kumulatif_menghitung_transaksi_sebelum_rentang_dan_sama_dengan_rekap(): void
    {
        $this->transaksi(JenisSimpanan::POKOK, 'setor', 100000, '2020-03-01');      // sebelum lima_tahun
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 900000, '2021-12-31');   // tepat sebelum lima_tahun
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 400000, '2022-01-01');   // batas awal lima_tahun
        $this->transaksi(JenisSimpanan::WAJIB, 'setor', 50000, '2026-08-20');
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 25000, '2026-11-12');

        $arus = $this->arus();
        $lima = $arus['periode']['lima_tahun'];

        $this->assertTrue($arus['ada_data']);
        $this->assertSame(1000000.0, $lima['ringkasan']['saldo_awal']);
        $this->assertSame(600000.0, $lima['saldo'][0]);
        $this->assertSame(-375000.0, $lima['ringkasan']['bersih']);

        $totalRekap = round((float) app(SimpananService::class)->rekapPerJenis()->sum('saldo'), 2);
        $this->assertSame(625000.0, $totalRekap);

        foreach ($arus['periode'] as $kunci => $periode) {
            $this->assertSame($totalRekap, end($periode['saldo']), "Saldo ember terakhir {$kunci} = total rekap.");
            $this->assertSame($totalRekap, $periode['ringkasan']['saldo_akhir']);
            $this->assertSame(
                round($periode['ringkasan']['saldo_awal'] + $periode['ringkasan']['bersih'], 2),
                $periode['ringkasan']['saldo_akhir'],
                "saldo_awal + bersih = saldo_akhir untuk {$kunci}."
            );
        }

        // enam_bulan mulai 2026-06-01: saldo awal = semua transaksi sebelum itu.
        $this->assertSame(600000.0, $arus['periode']['enam_bulan']['ringkasan']['saldo_awal']);
    }

    public function test_ember_kosong_bernilai_nol_dengan_saldo_terbawa(): void
    {
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 70000, '2026-07-15');

        $enam = $this->arus()['periode']['enam_bulan'];

        $this->assertSame([0.0, 70000.0, 0.0, 0.0, 0.0, 0.0], $enam['setor']);
        $this->assertSame([0.0, 0.0, 0.0, 0.0, 0.0, 0.0], $enam['tarik']);
        $this->assertSame([0.0, 70000.0, 70000.0, 70000.0, 70000.0, 70000.0], $enam['saldo']);
    }

    public function test_minggu_dimulai_senin_dan_label_kuartal(): void
    {
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 10000, '2026-11-08'); // Minggu
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 20000, '2026-11-09'); // Senin
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 30000, '2026-09-30'); // akhir K3
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 40000, '2026-10-01'); // awal K4

        $periode = $this->arus()['periode'];

        $tiga = $periode['tiga_bulan'];
        $this->assertSame('2 Nov', $tiga['label'][11]);
        $this->assertSame(10000.0, $tiga['setor'][11]);
        $this->assertSame(20000.0, $tiga['setor'][12]);
        $this->assertSame("2\u{2013}8 November 2026", $tiga['label_lengkap'][11]);

        $lima = $periode['lima_tahun'];
        $this->assertSame(['K1 22', 'K2 22', 'K3 22', 'K4 22'], array_slice($lima['label'], 0, 4));
        $this->assertSame(['K3 26', 'K4 26'], array_slice($lima['label'], 18, 2));
        $this->assertSame('Kuartal 1 2022', $lima['label_lengkap'][0]);
        $this->assertSame('Kuartal 4 2026', $lima['label_lengkap'][19]);
        $this->assertSame(30000.0, $lima['setor'][18]);
        $this->assertSame(40000.0 + 10000.0 + 20000.0, $lima['setor'][19]);
    }

    public function test_label_lengkap_mingguan_satu_bulan_lintas_bulan_dan_lintas_tahun(): void
    {
        // Kamis, 8 Januari 2026: 13 minggu = 13 Oktober 2025 s.d. minggu 5 Januari 2026.
        Carbon::setTestNow('2026-01-08 09:00:00');

        $tiga = $this->arus()['periode']['tiga_bulan'];

        $this->assertSame('2025-10-13', $tiga['mulai']);
        $this->assertSame("13\u{2013}19 Oktober 2025", $tiga['label_lengkap'][0]);
        $this->assertSame("27 Oktober\u{2013}2 November 2025", $tiga['label_lengkap'][2]);
        $this->assertSame("22\u{2013}28 Desember 2025", $tiga['label_lengkap'][10]);
        $this->assertSame("29 Desember 2025\u{2013}4 Januari 2026", $tiga['label_lengkap'][11]);
        // Minggu berjalan tetap ditulis sampai Minggu.
        $this->assertSame("5\u{2013}11 Januari 2026", $tiga['label_lengkap'][12]);
    }

    public function test_ada_data_false_tanpa_transaksi(): void
    {
        $arus = $this->arus();

        $this->assertFalse($arus['ada_data']);
        foreach ($arus['periode'] as $periode) {
            $this->assertSame(0.0, $periode['ringkasan']['saldo_akhir']);
            $this->assertSame(0.0, array_sum($periode['setor']));
        }
    }

    public function test_ada_data_false_jika_semua_transaksi_lebih_tua_dari_lima_tahun(): void
    {
        $this->transaksi(JenisSimpanan::POKOK, 'setor', 100000, '2021-12-31');

        $arus = $this->arus();

        $this->assertFalse($arus['ada_data']);
        // Saldo tetap terbawa walau tidak ada transaksi di rentang.
        $this->assertSame(100000.0, $arus['periode']['lima_tahun']['ringkasan']['saldo_awal']);
        $this->assertSame(100000.0, $arus['periode']['minggu']['ringkasan']['saldo_akhir']);
    }

    public function test_ember_batang_bulan_dan_lima_tahun_totalnya_sama_dengan_periode(): void
    {
        $this->transaksi(JenisSimpanan::POKOK, 'setor', 500000, '2021-06-01');    // sebelum lima_tahun
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 70000, '2022-01-01');  // batas awal lima_tahun
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 20000, '2024-07-15');
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 300000, '2026-10-13'); // tepat sebelum bulan
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 100000, '2026-10-14'); // batas awal bulan (Rabu)
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 40000, '2026-10-19');  // Senin
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 15000, '2026-11-12');  // hari ini

        $periode = $this->arus()['periode'];

        foreach (['minggu', 'tiga_bulan', 'enam_bulan', 'tahun'] as $kunci) {
            $this->assertArrayNotHasKey('batang', $periode[$kunci]);
        }

        foreach (['bulan', 'lima_tahun'] as $kunci) {
            $p = $periode[$kunci];
            $this->assertSame($p['ringkasan']['total_setor'], round(array_sum($p['batang']['setor']), 2), "{$kunci} setor");
            $this->assertSame($p['ringkasan']['total_tarik'], round(array_sum($p['batang']['tarik']), 2), "{$kunci} tarik");
            $this->assertSame($p['ringkasan']['saldo_akhir'], $p['batang']['saldo'][count($p['batang']['saldo']) - 1], "{$kunci} saldo");
        }

        // bulan: 14 Okt (Rabu) s.d. 12 Nov (Kamis) = 5 minggu, dipotong di kedua ujung.
        $bulan = $periode['bulan']['batang'];
        $this->assertSame(['14 Okt', '19 Okt', '26 Okt', '2 Nov', '9 Nov'], $bulan['label']);
        $this->assertSame("14\u{2013}18 Oktober 2026", $bulan['label_lengkap'][0]);
        $this->assertSame("9\u{2013}12 November 2026", $bulan['label_lengkap'][4]);
        $this->assertSame([100000.0, 40000.0, 0.0, 0.0, 0.0], $bulan['setor']);
        $this->assertSame(15000.0, $bulan['tarik'][4]);

        // lima_tahun: 2022–2026, tahun berjalan terpotong.
        $lima = $periode['lima_tahun']['batang'];
        $this->assertSame(['2022', '2023', '2024', '2025', '2026'], $lima['label']);
        $this->assertSame('2022', $lima['label_lengkap'][0]);
        $this->assertSame("Januari\u{2013}November 2026", $lima['label_lengkap'][4]);
        $this->assertSame(570000.0, $lima['saldo'][0]);
    }

    public function test_ember_batang_tahun_pertama_terpotong(): void
    {
        Carbon::setTestNow('2026-05-20 10:00:00'); // K2 2026: 20 kuartal dimulai K3 2021 (1 Juli 2021)

        $batang = $this->arus()['periode']['lima_tahun']['batang'];

        $this->assertSame(['2021', '2022', '2023', '2024', '2025', '2026'], $batang['label']);
        $this->assertSame("Juli\u{2013}Desember 2021", $batang['label_lengkap'][0]);
        $this->assertSame("Januari\u{2013}Mei 2026", $batang['label_lengkap'][5]);
    }

    public function test_jumlah_transaksi_dihitung_per_baris(): void
    {
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 10000, '2026-11-10');
        $this->transaksi(JenisSimpanan::WAJIB, 'setor', 20000, '2026-11-10'); // tanggal sama: tetap dua baris
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 5000, '2026-11-11');
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 30000, '2026-11-05'); // di luar minggu

        $periode = $this->arus()['periode'];

        $this->assertSame(2, $periode['minggu']['ringkasan']['jumlah_setor']);
        $this->assertSame(1, $periode['minggu']['ringkasan']['jumlah_tarik']);
        $this->assertSame(3, $periode['bulan']['ringkasan']['jumlah_setor']);
        $this->assertSame(1, $periode['bulan']['ringkasan']['jumlah_tarik']);
    }

    public function test_jendela_sebelumnya_minggu_sama_panjang_tepat_sebelum_mulai(): void
    {
        // minggu: 6–12 Nov (7 hari) -> sebelumnya 30 Okt–5 Nov.
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 1000, '2026-10-29');  // di luar jendela
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 2000, '2026-10-30');  // batas awal sebelumnya
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 500, '2026-11-05');   // batas akhir sebelumnya
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 7000, '2026-11-06');  // masuk periode, bukan sebelumnya

        $ringkasan = $this->arus()['periode']['minggu']['ringkasan'];

        $this->assertSame(['total_setor' => 2000.0, 'total_tarik' => 500.0], $ringkasan['sebelumnya']);
        $this->assertSame(7000.0, $ringkasan['total_setor']);
    }

    public function test_jendela_sebelumnya_enam_bulan_sama_jumlah_hari(): void
    {
        // enam_bulan: 1 Jun–12 Nov 2026 = 165 hari -> sebelumnya 18 Des 2025–31 Mei 2026.
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 1000, '2025-12-17');  // di luar jendela
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 2000, '2025-12-18');  // batas awal sebelumnya
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 300, '2026-05-31');   // batas akhir sebelumnya
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 4000, '2026-06-01');  // masuk periode

        $ringkasan = $this->arus()['periode']['enam_bulan']['ringkasan'];

        $this->assertSame(['total_setor' => 2000.0, 'total_tarik' => 300.0], $ringkasan['sebelumnya']);
        $this->assertSame(4000.0, $ringkasan['total_setor']);
        $this->assertSame(2700.0, $ringkasan['saldo_awal']);
    }

    public function test_jendela_sebelumnya_lima_tahun_terbaca_dari_rentang_query_yang_diperluas(): void
    {
        // lima_tahun: 1 Jan 2022–12 Nov 2026 = 1777 hari -> sebelumnya mulai 19 Feb 2017.
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 1000, '2017-02-18');  // di luar jendela, masuk saldo awal
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 2000, '2017-02-19');  // batas awal sebelumnya
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 3000, '2021-12-31');

        $arus = $this->arus();
        $ringkasan = $arus['periode']['lima_tahun']['ringkasan'];

        $this->assertFalse($arus['ada_data']);
        $this->assertSame(['total_setor' => 5000.0, 'total_tarik' => 0.0], $ringkasan['sebelumnya']);
        $this->assertSame(6000.0, $ringkasan['saldo_awal']);
    }

    public function test_hanya_dua_query(): void
    {
        $this->transaksi(JenisSimpanan::POKOK, 'setor', 100000, '2020-01-10');
        $this->transaksi(JenisSimpanan::SUKARELA, 'setor', 50000, '2026-11-10');
        $this->transaksi(JenisSimpanan::SUKARELA, 'tarik', 10000, '2026-11-11');

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->arus();
        $jumlah = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(2, $jumlah);
    }

    private function transaksi(string $namaJenis, string $jenisTransaksi, int $jumlah, string $tanggal): TransaksiSimpanan
    {
        return TransaksiSimpanan::create([
            'anggota_id' => $this->anggota->id,
            'jenis_simpanan_id' => JenisSimpanan::where('nama_jenis', $namaJenis)->value('id'),
            'staff_id' => $this->admin->id,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => 0,
        ]);
    }
}
