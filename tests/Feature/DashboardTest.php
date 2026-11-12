<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_dashboard_tampil_tanpa_data(): void
    {
        $response = $this->actingAs($this->admin)->get(route('dashboard'));

        $response->assertOk();
        $response->assertViewHas('totalAnggotaAktif', 0);
        $response->assertViewHas('totalSaldo', 0.0);
        $this->assertCount(3, $response->viewData('rekapSaldo'));
        $this->assertCount(0, $response->viewData('transaksiTerbaru'));
    }

    public function test_total_anggota_aktif_tidak_menghitung_anggota_nonaktif(): void
    {
        Anggota::factory()->count(2)->create();
        Anggota::factory()->nonaktif()->create();

        $this->actingAs($this->admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertViewHas('totalAnggotaAktif', 2);
    }

    public function test_rekap_per_jenis_benar_termasuk_jenis_kosong_dan_anggota_nonaktif(): void
    {
        $aktif = Anggota::factory()->create();
        $nonaktif = Anggota::factory()->nonaktif()->create();

        $this->buatTransaksi($aktif, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-01-10');
        $this->buatTransaksi($aktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000, '2026-01-11');
        $this->buatTransaksi($aktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 200000, '2026-01-12');
        $this->buatTransaksi($nonaktif, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 300000, '2026-01-13');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        $rekap = $response->viewData('rekapSaldo')->keyBy('nama_jenis');

        $this->assertSame(
            [JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA],
            $response->viewData('rekapSaldo')->pluck('nama_jenis')->all()
        );

        $this->assertSame(100000.0, $rekap[JenisSimpanan::POKOK]['total_setor']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::POKOK]['total_tarik']);
        $this->assertSame(100000.0, $rekap[JenisSimpanan::POKOK]['saldo']);

        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_setor']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['total_tarik']);
        $this->assertSame(0.0, $rekap[JenisSimpanan::WAJIB]['saldo']);

        $this->assertSame(800000.0, $rekap[JenisSimpanan::SUKARELA]['total_setor']);
        $this->assertSame(200000.0, $rekap[JenisSimpanan::SUKARELA]['total_tarik']);
        $this->assertSame(600000.0, $rekap[JenisSimpanan::SUKARELA]['saldo']);

        $response->assertViewHas('totalSaldo', 700000.0);
    }

    public function test_transaksi_terbaru_maksimal_sepuluh_dan_urut_terbaru(): void
    {
        $anggota = Anggota::factory()->create();

        // 12 transaksi pada tanggal berbeda: 2026-01-01 s.d. 2026-01-12.
        for ($hari = 1; $hari <= 12; $hari++) {
            $this->buatTransaksi(
                $anggota,
                JenisSimpanan::SUKARELA,
                TransaksiSimpanan::SETOR,
                10000,
                sprintf('2026-01-%02d', $hari)
            );
        }

        // Dua transaksi di tanggal yang sama: yang id-nya lebih besar tampil lebih dulu.
        $lebihAwal = $this->buatTransaksi($anggota, JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 20000, '2026-01-12');
        $lebihAkhir = $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 30000, '2026-01-12');

        $response = $this->actingAs($this->admin)->get(route('dashboard'));
        $response->assertOk();

        $terbaru = $response->viewData('transaksiTerbaru');

        $this->assertCount(10, $terbaru);
        $this->assertSame($lebihAkhir->id, $terbaru[0]->id);
        $this->assertSame($lebihAwal->id, $terbaru[1]->id);

        $tanggal = $terbaru->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d'))->all();
        $this->assertSame(
            ['2026-01-12', '2026-01-12', '2026-01-12', '2026-01-11', '2026-01-10',
                '2026-01-09', '2026-01-08', '2026-01-07', '2026-01-06', '2026-01-05'],
            $tanggal
        );
    }

    private function buatTransaksi(
        Anggota $anggota,
        string $namaJenis,
        string $jenisTransaksi,
        int $jumlah,
        string $tanggal
    ): TransaksiSimpanan {
        return TransaksiSimpanan::create([
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => JenisSimpanan::where('nama_jenis', $namaJenis)->value('id'),
            'staff_id' => $this->admin->id,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => 0,
        ]);
    }
}
