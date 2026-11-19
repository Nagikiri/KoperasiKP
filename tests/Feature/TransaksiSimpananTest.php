<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class TransaksiSimpananTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private SimpananService $service;

    private JenisSimpanan $pokok;

    private JenisSimpanan $wajib;

    private JenisSimpanan $sukarela;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();
        $this->service = app(SimpananService::class);

        $this->pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->firstOrFail();
        $this->wajib = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->firstOrFail();
        $this->sukarela = JenisSimpanan::sukarela();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Bagian A — service
    // ---------------------------------------------------------------

    public function test_setor_berantai_menghitung_saldo_akhir(): void
    {
        $anggota = Anggota::factory()->create();

        $pertama = $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-01', $this->admin->id);
        $kedua = $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-02', $this->admin->id);

        $this->assertEquals(100000, (float) $pertama->saldo_akhir);
        $this->assertEquals(150000, (float) $kedua->saldo_akhir);
        $this->assertSame(TransaksiSimpanan::SETOR, $kedua->jenis_transaksi);
        $this->assertSame($this->admin->id, $kedua->staff_id);
        $this->assertSame(150000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_setor_ke_anggota_nonaktif_ditolak_service(): void
    {
        $anggota = Anggota::factory()->nonaktif()->create();

        try {
            $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('anggota_id', $e->errors());
        }

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tarik_melebihi_saldo_ditolak_tanpa_baris_baru(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-01', $this->admin->id);

        try {
            $this->service->tarik($anggota->id, 100001, '2026-11-02', $this->admin->id);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Penarikan gagal. Saldo Simpanan Sukarela anggota ini hanya Rp 100.000.'],
                $e->errors()['jumlah']
            );
        }

        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_tarik_tepat_sebesar_saldo_menjadi_nol(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-01', $this->admin->id);

        $tarik = $this->service->tarik($anggota->id, 100000, '2026-11-02', $this->admin->id);

        $this->assertSame(TransaksiSimpanan::TARIK, $tarik->jenis_transaksi);
        $this->assertSame($this->sukarela->id, $tarik->jenis_simpanan_id);
        $this->assertEquals(0, (float) $tarik->saldo_akhir);
        $this->assertSame(0.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_tarik_tidak_mengubah_saldo_pokok_dan_wajib(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($anggota->id, $this->wajib->id, 50000, '2026-11-01', $this->admin->id);
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $this->service->tarik($anggota->id, 75000, '2026-11-02', $this->admin->id);

        $this->assertSame(100000.0, $anggota->saldoPerJenis($this->pokok->id));
        $this->assertSame(50000.0, $anggota->saldoPerJenis($this->wajib->id));
        $this->assertSame(125000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_tanggal_sebelum_tanggal_bergabung_ditolak(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);

        try {
            $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-10-31', $this->admin->id);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Tanggal transaksi tidak boleh sebelum tanggal bergabung anggota (1 November 2026).'],
                $e->errors()['tanggal_transaksi']
            );
        }

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tanggal_sebelum_transaksi_terakhir_jenis_sama_ditolak(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-05', $this->admin->id);

        try {
            $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-04', $this->admin->id);
            $this->fail('ValidationException tidak dilempar.');
        } catch (ValidationException $e) {
            $this->assertSame(
                ['Tanggal transaksi tidak boleh lebih awal dari transaksi terakhir Simpanan Sukarela anggota ini (5 November 2026).'],
                $e->errors()['tanggal_transaksi']
            );
        }

        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_tarik_sebelum_transaksi_terakhir_sukarela_ditolak(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-05', $this->admin->id);

        $this->expectException(ValidationException::class);

        $this->service->tarik($anggota->id, 10000, '2026-11-04', $this->admin->id);
    }

    public function test_tanggal_sama_dengan_transaksi_terakhir_diperbolehkan(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-05', $this->admin->id);

        $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-05', $this->admin->id);
        $this->service->tarik($anggota->id, 25000, '2026-11-05', $this->admin->id);

        $this->assertSame(3, TransaksiSimpanan::count());
        $this->assertSame(125000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_tanggal_lebih_awal_untuk_jenis_lain_diperbolehkan(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-05', $this->admin->id);

        $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-02', $this->admin->id);

        $this->assertSame(2, TransaksiSimpanan::count());
        $this->assertSame(100000.0, $anggota->saldoPerJenis($this->pokok->id));
    }

    // ---------------------------------------------------------------
    // Bagian B — HTTP
    // ---------------------------------------------------------------

    public function test_halaman_setor_tarik_dan_riwayat_tampil(): void
    {
        $this->actingAs($this->admin)->get(route('transaksi.setor.create'))
            ->assertOk()
            ->assertViewHas('tanggalHariIni', '2026-11-12')
            ->assertViewHas('jenisSimpanan', fn ($jenis) => $jenis->count() === 3);

        $this->actingAs($this->admin)->get(route('transaksi.tarik.create'))
            ->assertOk()
            ->assertSee('Simpanan Sukarela')
            ->assertViewHas('jenisSukarela', fn ($jenis) => $jenis->is($this->sukarela));

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertOk()
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null, 'dari' => null, 'sampai' => null]);
    }

    public function test_setor_lewat_form_untuk_ketiga_jenis_sukses(): void
    {
        $anggota = Anggota::factory()->create(['nama' => 'Budi Santoso']);

        foreach ([$this->pokok, $this->wajib, $this->sukarela] as $jenis) {
            $this->actingAs($this->admin)
                ->post(route('transaksi.setor.store'), [
                    'kode_referensi' => (string) Str::uuid(),
                    'anggota_id' => $anggota->id,
                    'jenis_simpanan_id' => $jenis->id,
                    'jumlah' => 100000,
                    'tanggal_transaksi' => '2026-11-12',
                ])
                ->assertRedirect(route('transaksi.riwayat'))
                ->assertSessionHas('success', "Setoran {$jenis->nama_jenis} sebesar Rp 100.000 untuk Budi Santoso berhasil dicatat.");
        }

        $this->assertSame(3, TransaksiSimpanan::count());
        $this->assertSame(3, TransaksiSimpanan::where('staff_id', $this->admin->id)->count());

        foreach ([$this->pokok, $this->wajib, $this->sukarela] as $jenis) {
            $this->assertSame(100000.0, $anggota->saldoPerJenis($jenis->id));
        }
    }

    public function test_saldo_akhir_dan_staff_id_dari_form_diabaikan(): void
    {
        $anggota = Anggota::factory()->create();
        $userLain = User::factory()->create();

        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->sukarela->id,
            'jumlah' => 100000,
            'tanggal_transaksi' => '2026-11-12',
            'saldo_akhir' => 999999999,
            'staff_id' => $userLain->id,
        ])->assertRedirect(route('transaksi.riwayat'));

        $transaksi = TransaksiSimpanan::firstOrFail();

        $this->assertEquals(100000, (float) $transaksi->saldo_akhir);
        $this->assertSame($this->admin->id, $transaksi->staff_id);
    }

    public function test_setor_ke_anggota_nonaktif_lewat_request_ditolak(): void
    {
        $anggota = Anggota::factory()->nonaktif()->create();

        $this->actingAs($this->admin)
            ->from(route('transaksi.setor.create'))
            ->post(route('transaksi.setor.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => 100000,
                'tanggal_transaksi' => '2026-11-12',
            ])
            ->assertRedirect(route('transaksi.setor.create'))
            ->assertSessionHasErrors(['anggota_id' => 'Anggota tidak ditemukan atau sudah nonaktif.']);

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_jumlah_tidak_valid_ditolak(): void
    {
        $anggota = Anggota::factory()->create();

        foreach ([0, -5000, '1000,50', 'abc'] as $jumlah) {
            $this->actingAs($this->admin)
                ->from(route('transaksi.setor.create'))
                ->post(route('transaksi.setor.store'), [
                    'kode_referensi' => (string) Str::uuid(),
                    'anggota_id' => $anggota->id,
                    'jenis_simpanan_id' => $this->pokok->id,
                    'jumlah' => $jumlah,
                    'tanggal_transaksi' => '2026-11-12',
                ])
                ->assertRedirect(route('transaksi.setor.create'))
                ->assertSessionHasErrors('jumlah');
        }

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tanggal_di_masa_depan_ditolak(): void
    {
        $anggota = Anggota::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('transaksi.setor.create'))
            ->post(route('transaksi.setor.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => 100000,
                'tanggal_transaksi' => '2026-11-13',
            ])
            ->assertRedirect(route('transaksi.setor.create'))
            ->assertSessionHasErrors('tanggal_transaksi');

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tanggal_sebelum_transaksi_terakhir_lewat_form_kembali_ke_form(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-11-01']);
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-05', $this->admin->id);

        $this->actingAs($this->admin)
            ->from(route('transaksi.setor.create'))
            ->post(route('transaksi.setor.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->sukarela->id,
                'jumlah' => 50000,
                'tanggal_transaksi' => '2026-11-04',
            ])
            ->assertRedirect(route('transaksi.setor.create'))
            ->assertSessionHasErrors('tanggal_transaksi');

        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_tarik_selalu_tercatat_sebagai_sukarela(): void
    {
        $anggota = Anggota::factory()->create(['nama' => 'Siti Aminah']);
        $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $this->actingAs($this->admin)
            ->post(route('transaksi.tarik.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => 75000,
                'tanggal_transaksi' => '2026-11-12',
            ])
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHas('success', 'Penarikan Simpanan Sukarela sebesar Rp 75.000 untuk Siti Aminah berhasil dicatat.');

        $tarik = TransaksiSimpanan::where('jenis_transaksi', TransaksiSimpanan::TARIK)->firstOrFail();

        $this->assertSame($this->sukarela->id, $tarik->jenis_simpanan_id);
        $this->assertSame($this->admin->id, $tarik->staff_id);
        $this->assertEquals(125000, (float) $tarik->saldo_akhir);
        $this->assertSame(100000.0, $anggota->saldoPerJenis($this->pokok->id));
    }

    public function test_tarik_melebihi_saldo_lewat_form_ditolak_dengan_pesan_saldo(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $this->actingAs($this->admin)
            ->from(route('transaksi.tarik.create'))
            ->post(route('transaksi.tarik.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jumlah' => 250000,
                'tanggal_transaksi' => '2026-11-12',
            ])
            ->assertRedirect(route('transaksi.tarik.create'))
            ->assertSessionHasErrors('jumlah');

        $this->assertStringContainsString('Rp 200.000', session('errors')->first('jumlah'));
        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_anggota_nonaktif_tidak_muncul_di_dropdown(): void
    {
        $aktif = Anggota::factory()->create();
        $nonaktif = Anggota::factory()->nonaktif()->create();

        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))
                ->assertOk()
                ->assertViewHas('anggotaAktif', function ($anggotaAktif) use ($aktif, $nonaktif) {
                    return $anggotaAktif->contains('id', $aktif->id)
                        && ! $anggotaAktif->contains('id', $nonaktif->id);
                });
        }
    }

    public function test_saldo_sukarela_per_anggota_di_form_tarik(): void
    {
        $a = Anggota::factory()->create();
        $b = Anggota::factory()->create();
        $this->service->setor($a->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);
        $this->service->tarik($a->id, 50000, '2026-11-02', $this->admin->id);
        $this->service->setor($a->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($b->id, $this->sukarela->id, 30000, '2026-11-01', $this->admin->id);

        $this->actingAs($this->admin)->get(route('transaksi.tarik.create'))
            ->assertOk()
            ->assertViewHas('saldoSukarelaPerAnggota', [$a->id => 150000.0, $b->id => 30000.0]);
    }

    public function test_anggota_terpilih_dari_query_string(): void
    {
        $aktif = Anggota::factory()->create();
        $nonaktif = Anggota::factory()->nonaktif()->create();

        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route, ['anggota_id' => $aktif->id]))
                ->assertViewHas('anggotaTerpilih', $aktif->id);

            $this->actingAs($this->admin)->get(route($route, ['anggota_id' => $nonaktif->id]))
                ->assertViewHas('anggotaTerpilih', null);

            $this->actingAs($this->admin)->get(route($route, ['anggota_id' => 99999]))
                ->assertViewHas('anggotaTerpilih', null);

            $this->actingAs($this->admin)->get(route($route))
                ->assertViewHas('anggotaTerpilih', null);
        }
    }

    public function test_riwayat_bisa_difilter_dan_urut_terbaru_dulu(): void
    {
        $a = Anggota::factory()->create();
        $b = Anggota::factory()->create();

        $t1 = $this->service->setor($a->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $t2 = $this->service->setor($a->id, $this->sukarela->id, 200000, '2026-11-03', $this->admin->id);
        $t3 = $this->service->setor($b->id, $this->sukarela->id, 50000, '2026-11-02', $this->admin->id);
        $t4 = $this->service->setor($a->id, $this->sukarela->id, 10000, '2026-11-03', $this->admin->id);

        $ids = fn ($riwayat) => $riwayat->pluck('id')->all();

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertOk()
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$t4->id, $t2->id, $t3->id, $t1->id]);

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['anggota_id' => $a->id]))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$t4->id, $t2->id, $t1->id])
            ->assertViewHas('filter', ['anggota_id' => (string) $a->id, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null, 'dari' => null, 'sampai' => null]);

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['jenis_simpanan_id' => $this->sukarela->id]))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$t4->id, $t2->id, $t3->id]);

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', [
            'anggota_id' => $a->id,
            'jenis_simpanan_id' => $this->pokok->id,
        ]))->assertViewHas('riwayat', fn ($r) => $ids($r) === [$t1->id]);
    }

    public function test_riwayat_menampilkan_anggota_nonaktif_di_filter(): void
    {
        $nonaktif = Anggota::factory()->nonaktif()->create();

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertViewHas('anggotaList', fn ($list) => $list->contains('id', $nonaktif->id));
    }

    public function test_tamu_yang_post_setor_diarahkan_ke_login(): void
    {
        $anggota = Anggota::factory()->create();

        $this->post(route('transaksi.setor.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->pokok->id,
            'jumlah' => 100000,
            'tanggal_transaksi' => '2026-11-12',
        ])->assertRedirect(route('login'));

        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tidak_ada_route_ubah_atau_hapus_transaksi(): void
    {
        $terlarang = [];

        foreach (Route::getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'transaksi')) {
                continue;
            }

            if (array_intersect(['PUT', 'PATCH', 'DELETE'], $route->methods())) {
                $terlarang[] = implode('|', $route->methods()).' '.$route->uri();
            }
        }

        $this->assertSame([], $terlarang);
    }

    // ---------------------------------------------------------------
    // Revisi R1 — kiriman ganda (BR-19)
    // ---------------------------------------------------------------

    public function test_form_setor_dan_tarik_membuat_kode_referensi_uuid_baru(): void
    {
        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $pertama = $this->actingAs($this->admin)->get(route($route))
                ->assertOk()
                ->assertSee('name="kode_referensi"', false)
                ->viewData('kodeReferensi');

            $kedua = $this->actingAs($this->admin)->get(route($route))->viewData('kodeReferensi');

            $this->assertTrue(Str::isUuid($pertama));
            $this->assertTrue(Str::isUuid($kedua));
            $this->assertNotSame($pertama, $kedua);
        }
    }

    public function test_setor_dua_kali_dengan_kode_referensi_sama_hanya_tercatat_sekali(): void
    {
        $anggota = Anggota::factory()->create();
        $data = [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->sukarela->id,
            'jumlah' => 100000,
            'tanggal_transaksi' => '2026-11-12',
        ];

        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), $data)
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), $data)
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHas('info', 'Transaksi ini sudah tercatat sebelumnya.')
            ->assertSessionMissing('success');

        $this->assertSame(1, TransaksiSimpanan::count());
        $this->assertSame($data['kode_referensi'], TransaksiSimpanan::firstOrFail()->kode_referensi);
        $this->assertSame(100000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_tarik_dua_kali_dengan_kode_referensi_sama_hanya_tercatat_sekali(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $data = [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jumlah' => 75000,
            'tanggal_transaksi' => '2026-11-12',
        ];

        $this->actingAs($this->admin)->post(route('transaksi.tarik.store'), $data)
            ->assertSessionHas('success');

        $this->actingAs($this->admin)->post(route('transaksi.tarik.store'), $data)
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHas('info', 'Transaksi ini sudah tercatat sebelumnya.');

        $this->assertSame(1, TransaksiSimpanan::where('jenis_transaksi', TransaksiSimpanan::TARIK)->count());
        $this->assertSame(125000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_tarik_seluruh_saldo_dikirim_ganda_kiriman_kedua_mendapat_info_bukan_error_saldo(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $data = [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jumlah' => 200000,
            'tanggal_transaksi' => '2026-11-12',
        ];

        $this->actingAs($this->admin)->post(route('transaksi.tarik.store'), $data);

        $this->actingAs($this->admin)
            ->from(route('transaksi.tarik.create'))
            ->post(route('transaksi.tarik.store'), $data)
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info', 'Transaksi ini sudah tercatat sebelumnya.');

        $this->assertSame(2, TransaksiSimpanan::count());
        $this->assertSame(0.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_service_mengembalikan_transaksi_lama_untuk_kode_referensi_sama(): void
    {
        $anggota = Anggota::factory()->create();
        $kode = (string) Str::uuid();

        $pertama = $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id, $kode);
        $kedua = $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id, $kode);

        $this->assertTrue($pertama->wasRecentlyCreated);
        $this->assertFalse($kedua->wasRecentlyCreated);
        $this->assertTrue($kedua->is($pertama));
        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_tanpa_kode_referensi_atau_bukan_uuid_ditolak(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        foreach ([null, 'bukan-uuid'] as $kode) {
            $setor = [
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => 100000,
                'tanggal_transaksi' => '2026-11-12',
            ];
            $tarik = [
                'anggota_id' => $anggota->id,
                'jumlah' => 10000,
                'tanggal_transaksi' => '2026-11-12',
            ];

            if ($kode !== null) {
                $setor['kode_referensi'] = $kode;
                $tarik['kode_referensi'] = $kode;
            }

            $this->actingAs($this->admin)
                ->from(route('transaksi.setor.create'))
                ->post(route('transaksi.setor.store'), $setor)
                ->assertRedirect(route('transaksi.setor.create'))
                ->assertSessionHasErrors('kode_referensi');

            $this->actingAs($this->admin)
                ->from(route('transaksi.tarik.create'))
                ->post(route('transaksi.tarik.store'), $tarik)
                ->assertRedirect(route('transaksi.tarik.create'))
                ->assertSessionHasErrors('kode_referensi');
        }

        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_tombol_simpan_dinonaktifkan_setelah_klik_pertama(): void
    {
        // Revisi 04 R2: penonaktif tombol dipindah ke skrip global di layout; skrip per form dihapus.
        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $html = $this->actingAs($this->admin)->get(route($route))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'data-cegah-kirim-ganda'));
            $this->assertStringNotContainsString('onsubmit=', $html);
            $this->assertStringContainsString("getElementById('jumlah')", $html);
        }
    }

    // ---------------------------------------------------------------
    // Revisi R2 — input jumlah dengan pemisah ribuan
    // ---------------------------------------------------------------

    public function test_jumlah_dengan_pemisah_ribuan_diterima(): void
    {
        $anggota = Anggota::factory()->create();

        $kasus = [
            '2.000.000' => 2000000,
            'Rp 150.000' => 150000,
            '2.000.000,00' => 2000000,
        ];

        foreach ($kasus as $input => $harapan) {
            $this->actingAs($this->admin)
                ->post(route('transaksi.setor.store'), [
                    'kode_referensi' => (string) Str::uuid(),
                    'anggota_id' => $anggota->id,
                    'jenis_simpanan_id' => $this->sukarela->id,
                    'jumlah' => $input,
                    'tanggal_transaksi' => '2026-11-12',
                ])
                ->assertRedirect(route('transaksi.riwayat'))
                ->assertSessionHasNoErrors();

            $this->assertEquals($harapan, (float) TransaksiSimpanan::latest('id')->firstOrFail()->jumlah, "Input {$input}");
        }

        $this->assertSame(4150000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_jumlah_tarik_dengan_format_rupiah_diterima(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        $this->actingAs($this->admin)
            ->post(route('transaksi.tarik.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jumlah' => 'Rp 75.000',
                'tanggal_transaksi' => '2026-11-12',
            ])
            ->assertRedirect(route('transaksi.riwayat'))
            ->assertSessionHasNoErrors();

        $this->assertSame(125000.0, $anggota->saldoPerJenis($this->sukarela->id));
    }

    public function test_jumlah_dengan_desimal_selain_nol_ditolak(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);

        foreach (['1.500,50', '2.000.000,50'] as $jumlah) {
            $this->actingAs($this->admin)
                ->from(route('transaksi.setor.create'))
                ->post(route('transaksi.setor.store'), [
                    'kode_referensi' => (string) Str::uuid(),
                    'anggota_id' => $anggota->id,
                    'jenis_simpanan_id' => $this->sukarela->id,
                    'jumlah' => $jumlah,
                    'tanggal_transaksi' => '2026-11-12',
                ])
                ->assertRedirect(route('transaksi.setor.create'))
                ->assertSessionHasErrors('jumlah');

            $this->actingAs($this->admin)
                ->from(route('transaksi.tarik.create'))
                ->post(route('transaksi.tarik.store'), [
                    'kode_referensi' => (string) Str::uuid(),
                    'anggota_id' => $anggota->id,
                    'jumlah' => $jumlah,
                    'tanggal_transaksi' => '2026-11-12',
                ])
                ->assertRedirect(route('transaksi.tarik.create'))
                ->assertSessionHasErrors('jumlah');
        }

        $this->assertSame(1, TransaksiSimpanan::count());
    }

    public function test_input_jumlah_berupa_teks_numerik(): void
    {
        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))
                ->assertSee('type="text" inputmode="numeric"', false);
        }
    }

    // ---------------------------------------------------------------
    // Revisi R3 & R4 — filter riwayat dan keterangan tarik
    // ---------------------------------------------------------------

    public function test_reset_filter_hanya_muncul_saat_ada_filter(): void
    {
        $anggota = Anggota::factory()->create();

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertSee('Terapkan Filter')
            ->assertDontSee('Reset Filter');

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['anggota_id' => $anggota->id]))
            ->assertSee('Terapkan Filter')
            ->assertSee('Reset Filter');

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['jenis_simpanan_id' => $this->pokok->id]))
            ->assertSee('Reset Filter');

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['anggota_id' => '', 'jenis_simpanan_id' => '']))
            ->assertDontSee('Reset Filter');
    }

    // ---------------------------------------------------------------
    // F2d H.2.1, F4 H.3 — riwayat menerima filter dashboard lewat bilah filter penuh
    // ---------------------------------------------------------------

    public function test_riwayat_menerapkan_filter_dashboard_dan_menampilkannya_di_bilah(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso', 'tanggal_bergabung' => '2026-01-01']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah', 'tanggal_bergabung' => '2026-01-01']);

        $lama = $this->service->setor($budi->id, $this->sukarela->id, 100000, '2026-10-01', $this->admin->id);  // di luar 30 hari
        $baru = $this->service->setor($budi->id, $this->sukarela->id, 50000, '2026-11-10', $this->admin->id);
        $tarik = $this->service->tarik($budi->id, 20000, '2026-11-11', $this->admin->id);
        $this->service->setor($siti->id, $this->sukarela->id, 70000, '2026-11-10', $this->admin->id);

        $ids = fn ($riwayat) => $riwayat->pluck('id')->all();

        $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['cari' => 'budi', 'jenis_transaksi' => 'setor', 'rentang' => '30_hari']));
        $response->assertOk()
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => 'budi', 'jenis_transaksi' => 'setor', 'rentang' => '30_hari', 'dari' => null, 'sampai' => null])
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$baru->id]);

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['cari' => 'budi']))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$tarik->id, $baru->id, $lama->id]);
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['jenis_transaksi' => 'tarik']))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$tarik->id]);

        // H.3.4: nilai filter tampil di bilah, bukan sebagai input tersembunyi + baris "Juga difilter".
        $html = $response->getContent();
        $xpath = $this->xpath($html);
        $bilah = '//form[@data-filter-riwayat]';
        $this->assertSame('budi', $xpath->query($bilah.'//input[@type="search"][@name="cari"]')->item(0)->getAttribute('value'));
        $this->assertSame('Cari nama atau no. anggota', $xpath->query($bilah.'//input[@name="cari"]')->item(0)->getAttribute('placeholder'));
        $this->assertSame('setor', $xpath->query($bilah.'//input[@name="jenis_transaksi"][@checked]')->item(0)->getAttribute('value'));
        $this->assertSame('30_hari', $xpath->query($bilah.'//input[@type="hidden"][@name="rentang"]')->item(0)->getAttribute('value'));
        $this->assertSame('30 hari', trim($xpath->query('//*[@data-dropdown-riwayat-transaksi="rentang"]//*[@data-dropdown-label]')->item(0)->textContent));
        $this->assertStringNotContainsString('data-filter-dashboard', $html);
        $this->assertStringNotContainsString('<input type="hidden" name="cari"', $html);
        // H.3.5: Reset Filter muncul saat filter apa pun aktif.
        $response->assertSee('Reset Filter');
    }

    public function test_riwayat_reset_filter_muncul_untuk_filter_apa_pun_dan_menaut_tanpa_parameter(): void
    {
        $anggota = Anggota::factory()->create();

        foreach ([
            ['anggota_id' => $anggota->id],
            ['jenis_simpanan_id' => $this->sukarela->id],
            ['cari' => 'budi'],
            ['jenis_transaksi' => 'tarik'],
            ['rentang' => 'hari_ini'],
            ['rentang' => 'kustom', 'dari' => '2026-11-01', 'sampai' => '2026-11-05'],
        ] as $query) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', $query))->getContent());
            $reset = $xpath->query('//form[@data-filter-riwayat]//a[@data-reset-riwayat-transaksi]');

            $this->assertSame(1, $reset->length, json_encode($query));
            $this->assertSame('Reset Filter', trim($reset->item(0)->textContent));
            $this->assertSame(route('transaksi.riwayat'), $reset->item(0)->getAttribute('href'));
        }

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertDontSee('data-reset-riwayat-transaksi', false)
            ->assertDontSee('data-filter-dashboard', false);
    }

    public function test_riwayat_nilai_filter_dashboard_tidak_valid_diabaikan(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-10', $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat', [
            'cari' => '   ', 'jenis_transaksi' => 'pinjam', 'rentang' => 'kemarin',
        ]));

        $response->assertOk()
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null, 'dari' => null, 'sampai' => null])
            ->assertViewHas('riwayat', fn ($r) => $r->count() === 1)
            ->assertDontSee('data-reset-riwayat-transaksi', false);
    }

    public function test_form_tarik_menampilkan_keterangan_sukarela(): void
    {
        // F4b C.2: info dipendekkan menjadi satu baris, tanpa paragraf kedua.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.tarik.create'))->getContent());
        $info = $xpath->query('//*[@data-info-tarik]');

        $this->assertSame(1, $info->length);
        $this->assertSame('Penarikan hanya bisa dari saldo sukarela.', trim($info->item(0)->textContent));
        $this->assertSame(1, $xpath->query('.//p', $info->item(0))->length);
    }

    // ---------------------------------------------------------------
    // Revisi 04 R10 — catatan transaksi (keterangan)
    // ---------------------------------------------------------------

    public function test_form_setor_dan_tarik_punya_input_catatan(): void
    {
        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $this->actingAs($this->admin)->get(route($route))
                ->assertSee('name="keterangan"', false)
                ->assertSee('Catatan (opsional)');
        }
    }

    public function test_catatan_tersimpan_dan_tampil_di_riwayat_dan_detail(): void
    {
        $anggota = Anggota::factory()->create();

        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->sukarela->id,
            'jumlah' => '200.000',
            'tanggal_transaksi' => '2026-11-10',
            'keterangan' => 'Setoran lewat kas kantor',
        ])->assertRedirect(route('transaksi.riwayat'));

        $this->actingAs($this->admin)->post(route('transaksi.tarik.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jumlah' => '50.000',
            'tanggal_transaksi' => '2026-11-11',
            'keterangan' => 'Biaya sekolah anak',
        ])->assertRedirect(route('transaksi.riwayat'));

        // Tanpa catatan tetap boleh.
        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->pokok->id,
            'jumlah' => '100000',
            'tanggal_transaksi' => '2026-11-11',
        ])->assertSessionHasNoErrors();

        $this->assertSame(
            ['Setoran lewat kas kantor', 'Biaya sekolah anak', null],
            TransaksiSimpanan::orderBy('id')->pluck('keterangan')->all(),
        );

        foreach ([route('transaksi.riwayat'), route('anggota.show', $anggota)] as $url) {
            $this->actingAs($this->admin)->get($url)
                ->assertSee('Catatan')
                ->assertSee('Setoran lewat kas kantor')
                ->assertSee('Biaya sekolah anak');
        }
    }

    public function test_catatan_lebih_dari_255_karakter_ditolak(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-11-01', $this->admin->id);

        $this->actingAs($this->admin)
            ->from(route('transaksi.setor.create'))
            ->post(route('transaksi.setor.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => '100000',
                'tanggal_transaksi' => '2026-11-10',
                'keterangan' => str_repeat('a', 256),
            ])
            ->assertRedirect(route('transaksi.setor.create'))
            ->assertSessionHasErrors(['keterangan' => 'Catatan tidak boleh lebih dari 255 karakter.']);

        $this->actingAs($this->admin)
            ->from(route('transaksi.tarik.create'))
            ->post(route('transaksi.tarik.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $anggota->id,
                'jumlah' => '1000',
                'tanggal_transaksi' => '2026-11-10',
                'keterangan' => str_repeat('a', 256),
            ])
            ->assertRedirect(route('transaksi.tarik.create'))
            ->assertSessionHasErrors('keterangan');

        $this->assertSame(1, TransaksiSimpanan::count());

        // Tepat 255 karakter diterima.
        $this->actingAs($this->admin)->post(route('transaksi.setor.store'), [
            'kode_referensi' => (string) Str::uuid(),
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $this->pokok->id,
            'jumlah' => '100000',
            'tanggal_transaksi' => '2026-11-10',
            'keterangan' => str_repeat('a', 255),
        ])->assertSessionHasNoErrors();
    }

    // ---------------------------------------------------------------
    // F4 §1 — form setor
    // ---------------------------------------------------------------

    public function test_form_setor_kartu_radio_jenis_awalan_rp_dan_kode_referensi(): void
    {
        $html = $this->actingAs($this->admin)->get(route('transaksi.setor.create'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        // Tiga radio jenis di dalam fieldset berlegenda; tidak ada select jenis lagi.
        $radio = $xpath->query('//fieldset[@data-pilihan-jenis]//input[@type="radio"][@name="jenis_simpanan_id"]');
        $this->assertSame(3, $radio->length);
        $this->assertSame('Jenis simpanan', trim($xpath->query('//fieldset[@data-pilihan-jenis]/legend')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//select[@name="jenis_simpanan_id"]')->length);
        $this->assertSame(0, $xpath->query('//input[@name="jenis_simpanan_id"][@checked]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-keterangan-jenis]')->length);

        // Awalan "Rp" elemen terpisah, bukan bagian dari value; input jumlah HTML biasa dengan urutan atribut tetap.
        $this->assertSame('Rp', trim($xpath->query('//*[@data-awalan-rp]')->item(0)->textContent));
        $this->assertStringContainsString('<input id="jumlah" type="text" inputmode="numeric" autocomplete="off" name="jumlah" value=""', $html);
        $this->assertSame(1, $xpath->query('//input[@type="hidden"][@name="kode_referensi"]')->length);
        $this->assertStringContainsString('Catatan (opsional)', $html);
    }

    public function test_form_setor_old_jenis_terpilih_dan_kartu_tanpa_merah(): void
    {
        $html = $this->actingAs($this->admin)
            ->withSession(['_old_input' => ['jenis_simpanan_id' => (string) $this->wajib->id]])
            ->get(route('transaksi.setor.create'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $terpilih = $xpath->query('//input[@name="jenis_simpanan_id"][@checked]');
        $this->assertSame(1, $terpilih->length);
        $this->assertSame((string) $this->wajib->id, $terpilih->item(0)->getAttribute('value'));

        // H.1.4: kartu terpilih arang, merah hanya untuk tombol Masuk.
        $this->assertSame(1, preg_match('/<form[^>]*data-form-transaksi="setor".*?<\/form>/s', $html, $cocok));
        $form = $cocok[0];
        $this->assertStringNotContainsString('merah', $form);
        $this->assertStringContainsString('peer-checked:border-arang', $form);
    }

    public function test_form_setor_keterangan_jenis_kosong_tidak_dirender(): void
    {
        $this->sukarela->update(['keterangan' => null]);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.setor.create'))->getContent());

        $this->assertSame(2, $xpath->query('//*[@data-keterangan-jenis]')->length);
        $this->assertSame(3, $xpath->query('//*[@data-kartu-jenis]')->length);
    }

    // ---------------------------------------------------------------
    // F4 §2 — form tarik
    // ---------------------------------------------------------------

    public function test_form_tarik_data_saldo_hanya_anggota_aktif_kartu_sukarela_dan_tanpa_field_jenis(): void
    {
        $budi = Anggota::factory()->create();
        $siti = Anggota::factory()->create();
        $tanpaSukarela = Anggota::factory()->create();
        $keluar = Anggota::factory()->create();
        $this->service->setor($budi->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);
        $this->service->tarik($budi->id, 50000, '2026-11-02', $this->admin->id);
        $this->service->setor($siti->id, $this->sukarela->id, 30000, '2026-11-01', $this->admin->id);
        $this->service->setor($tanpaSukarela->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($keluar->id, $this->sukarela->id, 70000, '2026-11-01', $this->admin->id);
        $keluar->update(['status' => Anggota::STATUS_NONAKTIF]);

        $html = $this->actingAs($this->admin)->get(route('transaksi.tarik.create'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        // H.2.4: hanya anggota aktif; anggota tanpa transaksi Sukarela tidak ada di peta (JS membaca 0).
        $data = json_decode($xpath->query('//script[@id="data-saldo"][@type="application/json"]')->item(0)->textContent, true);
        $this->assertEquals([$budi->id => 150000, $siti->id => 30000], $data);

        // F4b C.4: badge diganti satu kartu Sukarela terpilih (arang, tanpa merah/emas), hanya nama jenis tanpa keterangan.
        $kartu = $xpath->query('//*[@data-kartu-jenis-tarik]');
        $this->assertSame(1, $kartu->length);
        $this->assertSame('Simpanan Sukarela', trim($kartu->item(0)->textContent));
        $this->assertSame('Simpanan Sukarela', trim($xpath->query('//*[@data-jenis-tarik]')->item(0)->textContent));
        $this->assertStringContainsString('border-arang', $kartu->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('bg-emas', $kartu->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('merah', $kartu->item(0)->getAttribute('class'));
        $this->assertSame(0, $xpath->query('//*[@data-kartu-jenis-tarik]//*[@data-keterangan-jenis]')->length);
        $this->assertStringNotContainsString($this->sukarela->keterangan, $html);
        $this->assertSame(0, $xpath->query('//*[@name="jenis_simpanan_id"]')->length);
        $this->assertStringNotContainsString('(Saldo Sukarela:', $html);

        // F4b C.5: tanpa anggota terpilih, saldo tersedia berisi ajakan memilih; peringatan tersedia tetapi tersembunyi.
        $tersedia = $xpath->query('//*[@data-kotak-nominal]//*[@data-saldo-tersedia]');
        $this->assertSame(1, $tersedia->length);
        $this->assertFalse($xpath->query('//*[@data-saldo-kosong]')->item(0)->hasAttribute('hidden'));
        $this->assertSame('Pilih anggota untuk melihat saldo', trim($xpath->query('//*[@data-saldo-kosong]')->item(0)->textContent));
        $this->assertTrue($xpath->query('//*[@data-saldo-ada]')->item(0)->hasAttribute('hidden'));
        $peringatan = $xpath->query('//*[@data-peringatan-saldo]')->item(0);
        $this->assertTrue($peringatan->hasAttribute('hidden'));
        $this->assertSame('Melebihi saldo tersedia.', trim($peringatan->textContent));
    }

    public function test_form_tarik_saldo_tersedia_langsung_tampil_untuk_anggota_terpilih(): void
    {
        $budi = Anggota::factory()->create();
        $this->service->setor($budi->id, $this->sukarela->id, 125000, '2026-11-01', $this->admin->id);
        $baru = Anggota::factory()->create();

        $kotak = fn (array $query, array $old = []) => $this->xpath(
            $this->actingAs($this->admin)->withSession(['_old_input' => $old])->get(route('transaksi.tarik.create', $query))->getContent()
        );

        $xpath = $kotak(['anggota_id' => $budi->id]);
        $this->assertFalse($xpath->query('//*[@data-saldo-ada]')->item(0)->hasAttribute('hidden'));
        $this->assertTrue($xpath->query('//*[@data-saldo-kosong]')->item(0)->hasAttribute('hidden'));
        $this->assertSame('Saldo sukarela tersedia Rp 125.000', preg_replace('/\s+/', ' ', trim($xpath->query('//*[@data-saldo-ada]')->item(0)->textContent)));
        $this->assertStringContainsString('font-semibold', $xpath->query('//*[@data-nilai-saldo]')->item(0)->getAttribute('class'));
        $this->assertSame('Rp 125.000', trim($xpath->query('//*[@data-nilai-saldo]')->item(0)->textContent));

        // old('anggota_id') menang atas ?anggota_id=; anggota tanpa saldo Sukarela tampil Rp 0.
        $xpath = $kotak(['anggota_id' => $budi->id], ['anggota_id' => (string) $baru->id]);
        $this->assertSame('Rp 0', trim($xpath->query('//*[@data-nilai-saldo]')->item(0)->textContent));
    }

    public function test_aset_saldo_tarik_dimuat_lewat_dynamic_import_hanya_jika_data_saldo_ada(): void
    {
        $app = file_get_contents(resource_path('js/app.js'));

        $this->assertMatchesRegularExpression("/if \\(document\\.getElementById\\('data-saldo'\\)\\) \\{\\s*import\\('\\.\\/transaksi-tarik'\\)/", $app);
        $this->assertStringContainsString("'Rp ' + new Intl.NumberFormat('id-ID')", file_get_contents(resource_path('js/transaksi-tarik.js')));

        $this->actingAs($this->admin)->get(route('transaksi.setor.create'))->assertDontSee('id="data-saldo"', false);
    }

    // ---------------------------------------------------------------
    // F4 §3 — riwayat
    // ---------------------------------------------------------------

    public function test_riwayat_menerima_rentang_gabungan_dan_kustom(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $okt = $this->service->setor($anggota->id, $this->sukarela->id, 100000, '2026-10-14', $this->admin->id);
        $nov = $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-12', $this->admin->id);

        foreach (TransaksiSimpanan::RENTANG_RIWAYAT as $rentang) {
            if ($rentang !== 'kustom') {
                $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['rentang' => $rentang, 'dari' => '2026-01-01', 'sampai' => '2026-01-02']))
                    ->assertViewHas('filter', fn ($filter) => $filter['rentang'] === $rentang && $filter['dari'] === null && $filter['sampai'] === null);
            }
        }

        $ids = fn ($query) => $this->actingAs($this->admin)->get(route('transaksi.riwayat', $query))->viewData('riwayat')->pluck('id')->all();
        $this->assertSame([$okt->id], $ids(['rentang' => 'bulan_lalu']));
        $this->assertSame([$nov->id], $ids(['rentang' => 'hari_ini']));

        // Kustom: dari > sampai ditukar; tanpa dua tanggal valid = tanpa filter.
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['rentang' => 'kustom', 'dari' => '2026-10-31', 'sampai' => '2026-10-01']))
            ->assertViewHas('filter', fn ($filter) => $filter['rentang'] === 'kustom' && $filter['dari'] === '2026-10-01' && $filter['sampai'] === '2026-10-31')
            ->assertViewHas('riwayat', fn ($r) => $r->pluck('id')->all() === [$okt->id]);
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['rentang' => 'kustom', 'dari' => '2026-10-01']))
            ->assertViewHas('filter', fn ($filter) => $filter['rentang'] === null && $filter['dari'] === null && $filter['sampai'] === null)
            ->assertViewHas('riwayat', fn ($r) => $r->count() === 2);

        // Menu waktu memuat semua nilai RENTANG_RIWAYAT, "Pilih tanggal…" terakhir; kolom tanggal disabled kecuali kustom.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent());
        $nilai = array_map(fn ($el) => $el->getAttribute('data-nilai'), iterator_to_array($xpath->query('//*[@data-dropdown-riwayat-transaksi="rentang"]//*[@role="menuitemradio"]')));
        $this->assertSame(array_merge([''], TransaksiSimpanan::RENTANG_RIWAYAT), $nilai);
        $this->assertSame(2, $xpath->query('//*[@data-rentang-tanggal]//input[@disabled]')->length);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', ['rentang' => 'kustom', 'dari' => '2026-10-01', 'sampai' => '2026-10-31']))->getContent());
        $this->assertSame(0, $xpath->query('//*[@data-rentang-tanggal]//input[@disabled]')->length);
        $this->assertSame('Pilih tanggal…', trim($xpath->query('//*[@data-dropdown-riwayat-transaksi="rentang"]//*[@data-dropdown-label]')->item(0)->textContent));
    }

    public function test_tautan_lihat_semua_dari_dashboard_tetap_berlaku_di_riwayat(): void
    {
        $anggota = Anggota::factory()->create(['nama' => 'Budi Santoso', 'tanggal_bergabung' => '2026-01-01']);
        for ($i = 0; $i < 12; $i++) {
            $this->service->setor($anggota->id, $this->sukarela->id, 1000, '2026-11-'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT), $this->admin->id);
        }
        $this->service->setor($anggota->id, $this->pokok->id, 1000, '2026-10-01', $this->admin->id); // di luar 30 hari

        $dashboard = $this->actingAs($this->admin)->get(route('dashboard', ['cari' => 'budi', 'rentang' => '30_hari', 'jenis_simpanan_id' => $this->sukarela->id]))->getContent();
        $href = html_entity_decode($this->xpath($dashboard)->query('//a[@data-lihat-semua-transaksi]')->item(0)->getAttribute('href'));

        $this->actingAs($this->admin)->get($href)->assertOk()
            ->assertViewHas('filter', fn ($filter) => $filter['cari'] === 'budi' && $filter['rentang'] === '30_hari' && $filter['jenis_simpanan_id'] === (string) $this->sukarela->id)
            ->assertViewHas('riwayat', fn ($r) => $r->total() === 12);
    }

    public function test_riwayat_id_anggota_dan_jenis_hanya_diterima_jika_ada(): void
    {
        $anggota = Anggota::factory()->create();
        $nonaktif = Anggota::factory()->nonaktif()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-10', $this->admin->id);

        foreach ([['anggota_id' => 99999], ['anggota_id' => 'abc'], ['jenis_simpanan_id' => 999], ['jenis_simpanan_id' => '1 OR 1']] as $query) {
            $this->actingAs($this->admin)->get(route('transaksi.riwayat', $query))->assertOk()
                ->assertViewHas('filter', fn ($filter) => $filter['anggota_id'] === null && $filter['jenis_simpanan_id'] === null)
                ->assertViewHas('riwayat', fn ($r) => $r->count() === 1)
                ->assertDontSee('data-reset-riwayat-transaksi', false);
        }

        // Anggota nonaktif tetap boleh dipilih (BR-21).
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['anggota_id' => $nonaktif->id]))
            ->assertViewHas('filter', fn ($filter) => $filter['anggota_id'] === (string) $nonaktif->id)
            ->assertViewHas('riwayat', fn ($r) => $r->count() === 0)
            ->assertSee('Tidak ada transaksi yang cocok dengan filter.');
    }

    public function test_riwayat_ringkasan_setor_tarik_mengikuti_filter(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso', 'tanggal_bergabung' => '2026-01-01']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah', 'tanggal_bergabung' => '2026-01-01']);
        $this->service->setor($budi->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);
        $this->service->tarik($budi->id, 50000, '2026-11-02', $this->admin->id);
        $this->service->setor($budi->id, $this->pokok->id, 100000, '2026-10-01', $this->admin->id);
        $this->service->setor($siti->id, $this->wajib->id, 30000, '2026-11-03', $this->admin->id);

        $ringkasan = function (array $query) {
            $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat', $query));
            $teks = trim(preg_replace('/\s+/', ' ', $this->xpath($response->getContent())->query('//*[@data-ringkasan-riwayat-transaksi]')->item(0)->textContent));

            return [$response->viewData('ringkasan'), $teks];
        };

        // Revisi 2 pasca-F4b: diawali label periode dan diakhiri Bersih bertanda.
        $this->assertSame([['total_setor' => 330000.0, 'total_tarik' => 50000.0], 'Semua waktu · 4 transaksi · Setor Rp 330.000 · Tarik Rp 50.000 · Bersih +Rp 280.000'], $ringkasan([]));
        $this->assertSame([['total_setor' => 300000.0, 'total_tarik' => 50000.0], 'Semua waktu · 3 transaksi · Setor Rp 300.000 · Tarik Rp 50.000 · Bersih +Rp 250.000'], $ringkasan(['anggota_id' => $budi->id]));
        $this->assertSame([['total_setor' => 230000.0, 'total_tarik' => 50000.0], 'Bulan ini · 3 transaksi · Setor Rp 230.000 · Tarik Rp 50.000 · Bersih +Rp 180.000'], $ringkasan(['rentang' => 'bulan_ini']));
        $this->assertSame([['total_setor' => 0.0, 'total_tarik' => 50000.0], 'Semua waktu · 1 transaksi · Setor Rp 0 · Tarik Rp 50.000 · Bersih -Rp 50.000'], $ringkasan(['jenis_transaksi' => 'tarik']));
        $this->assertSame([['total_setor' => 30000.0, 'total_tarik' => 0.0], 'Semua waktu · 1 transaksi · Setor Rp 30.000 · Tarik Rp 0 · Bersih +Rp 30.000'], $ringkasan(['cari' => 'siti']));
    }

    public function test_riwayat_paginasi_tanpa_input_kosong_dan_bilah_memulihkan_input_saat_pageshow(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        for ($i = 0; $i < 21; $i++) {
            $this->service->setor($anggota->id, $this->sukarela->id, 1000, '2026-11-01', $this->admin->id);
        }

        // URL dari bilah yang input kosongnya sudah di-disabled hanya membawa kunci terisi.
        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['jenis_transaksi' => 'setor']))->getContent();
        $this->assertSame(1, preg_match('/<nav[^>]*data-pagination[^>]*>.*?<\/nav>/s', $html, $nav));
        preg_match('/<a href="([^"]+)" rel="next"/', $nav[0], $berikut);
        $this->assertSame(route('transaksi.riwayat', ['jenis_transaksi' => 'setor', 'page' => 2]), html_entity_decode($berikut[1]));

        // H.3.4 / H.6.5: markup menonaktifkan input kosong saat submit dan memulihkannya saat pageshow.
        $this->assertSame(1, preg_match('/<form[^>]*data-filter-riwayat.*?<\/form>/s', $html, $form));
        $this->assertStringContainsString('x-on:submit="bersihkan()"', $form[0]);
        $this->assertStringContainsString('x-on:pageshow.window="pulihkan()"', $form[0]);
        $this->assertStringContainsString("el.value === ''", $form[0]);
        $this->assertStringContainsString('data-dimatikan-kosong', $form[0]);
        // Form tidak membawa page, jadi filter baru kembali ke halaman 1; "Terapkan Filter" tetap ada (sr-only).
        $this->assertStringNotContainsString('name="page"', $form[0]);
        $this->assertMatchesRegularExpression('/<button type="submit" class="sr-only">Terapkan Filter<\/button>/', $form[0]);
    }

    public function test_riwayat_kolom_tabel_nominal_bertanda_dan_catatan(): void
    {
        $anggota = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $catatan = 'Setoran untuk biaya pendidikan anak pertama semester ganjil';
        $setor = $this->service->setor($anggota->id, $this->sukarela->id, 150000, '2026-11-01', $this->admin->id);
        $setor->update(['keterangan' => $catatan]);
        $this->service->tarik($anggota->id, 50000, '2026-11-02', $this->admin->id);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);

        $kepala = array_map(fn ($th) => trim($th->textContent), iterator_to_array($xpath->query('//*[@data-kartu-riwayat-transaksi]//thead//th')));
        $this->assertSame(['Anggota', 'Tanggal', 'Transaksi', 'Jumlah', 'Saldo setelah transaksi', 'Dicatat oleh', 'Catatan'], $kepala);
        $this->assertSame(1, $xpath->query('//th[@title="Saldo jenis simpanan ini setelah transaksi tersebut"]')->length);

        $this->assertSame('+Rp 150.000', trim($xpath->query('//tr[@data-transaksi="setor"]//*[@data-nominal]')->item(0)->textContent));
        $this->assertSame('-Rp 50.000', trim($xpath->query('//tr[@data-transaksi="tarik"]//*[@data-nominal]')->item(0)->textContent));
        $this->assertStringContainsString('text-setor', $xpath->query('//tr[@data-transaksi="setor"]//*[@data-nominal]')->item(0)->getAttribute('class'));
        $this->assertStringContainsString('text-tarik', $xpath->query('//tr[@data-transaksi="tarik"]//*[@data-nominal]')->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('−', $html);
        // Tanda di span normal-nums (bukan angka tabular) supaya "-Rp" serapat "+Rp".
        foreach (['setor' => '+', 'tarik' => '-'] as $jenis => $tanda) {
            $span = $xpath->query('//tr[@data-transaksi="'.$jenis.'"]//*[@data-nominal]/span[contains(@class, "normal-nums")]')->item(0);
            $this->assertSame($tanda, $span->textContent);
        }

        // Nama sekali (avatar hanya inisial), no. anggota di bawahnya; tanpa badge success/danger (F4b D.2).
        $baris = $xpath->query('//tr[@data-transaksi="setor"]')->item(0);
        $this->assertSame(1, substr_count($baris->textContent, 'Budi Santoso'));
        $this->assertStringContainsString($anggota->no_anggota, $baris->textContent);
        $this->assertStringNotContainsString('bg-emerald-50', $html);

        // Revisi 2 pasca-F4b: catatan utuh, maksimal 2 baris (line-clamp-2), title berisi teks lengkap, kolom min 16rem.
        $sel = $xpath->query('//*[@data-catatan]')->item(0);
        $this->assertSame($catatan, $sel->getAttribute('title'));
        $this->assertSame($catatan, trim($sel->textContent));
        $this->assertStringContainsString('line-clamp-2', $sel->getAttribute('class'));
        $td = explode(' ', $xpath->query('//tr[@data-transaksi="setor"]//td[@data-sel-catatan]')->item(0)->getAttribute('class'));
        $this->assertContains('whitespace-normal', $td);
        $this->assertContains('min-w-[16rem]', $td);
        $this->assertSame('', trim($xpath->query('//tr[@data-transaksi="tarik"]//*[@data-sel-catatan]')->item(0)->textContent));
    }

    public function test_riwayat_keadaan_kosong_tanpa_dan_dengan_filter(): void
    {
        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertSee('Belum ada transaksi.')
            ->assertDontSee('Tidak ada transaksi yang cocok dengan filter.');

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', ['cari' => 'tidak ada']))->getContent());
        $kosong = $xpath->query('//*[@data-kosong-filter]')->item(0);
        $this->assertStringContainsString('Tidak ada transaksi yang cocok dengan filter.', $kosong->textContent);
        $this->assertSame(route('transaksi.riwayat'), $xpath->query('//*[@data-kosong-filter]//a[normalize-space()="Reset Filter"]')->item(0)->getAttribute('href'));
    }

    // ---------------------------------------------------------------
    // F4b — form setor/tarik dan riwayat dirapikan
    // ---------------------------------------------------------------

    public function test_pilih_anggota_opsi_dirender_server_dengan_input_anggota_id_tetap(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah']);
        $keluar = Anggota::factory()->nonaktif()->create(['nama' => 'Andi Keluar']);

        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route($route))->assertOk()->getContent());
            $pilih = '//form//*[@data-pilih-anggota-cari]';

            // A.1 (revisi pasca-F4b): opsi dirender server (hanya anggota aktif, urut nama), satu baris "{no} - {nama}" tanpa huruf mono.
            $opsi = $xpath->query($pilih.'//*[@data-opsi-anggota]');
            $this->assertSame([(string) $budi->id, (string) $siti->id], array_map(fn ($el) => $el->getAttribute('data-opsi-anggota'), iterator_to_array($opsi)), $route);
            $this->assertSame("{$budi->no_anggota} - Budi Santoso", trim($xpath->query('.//*[@data-label-opsi]', $opsi->item(0))->item(0)->textContent));
            $this->assertSame(0, $xpath->query($pilih.'//*[contains(@class, "font-mono")]')->length, $route);
            $this->assertSame(0, $xpath->query($pilih.'//*[@data-opsi-anggota="'.$keluar->id.'"]')->length);

            // Nilai lewat satu input tersembunyi anggota_id; select lama hilang; kolom cari tanpa name.
            $this->assertSame(1, $xpath->query('//form//input[@type="hidden"][@name="anggota_id"]')->length);
            $this->assertSame('', $xpath->query('//input[@name="anggota_id"]')->item(0)->getAttribute('value'));
            $this->assertSame(0, $xpath->query('//select[@name="anggota_id"]')->length);
            $this->assertSame('Cari nama atau no. anggota', $xpath->query($pilih.'//input[@type="text"]')->item(0)->getAttribute('placeholder'));
            $this->assertFalse($xpath->query($pilih.'//input[@type="text"]')->item(0)->hasAttribute('name'));
            $this->assertSame('Pilih anggota', trim($xpath->query($pilih.'//*[@data-anggota-terpilih]')->item(0)->textContent));
            $this->assertSame('Tidak ada anggota yang cocok.', trim($xpath->query($pilih.'//*[@data-anggota-kosong]')->item(0)->textContent));
            $this->assertSame(1, $xpath->query($pilih.'//*[contains(@class, "max-h-72")][contains(@class, "overflow-y-auto")]')->length);

            // ?anggota_id= memilih anggota terpilih.
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route($route, ['anggota_id' => $siti->id]))->getContent());
            $this->assertSame((string) $siti->id, $xpath->query('//input[@name="anggota_id"]')->item(0)->getAttribute('value'));
            $this->assertSame("{$siti->no_anggota} - Siti Aminah", trim($xpath->query('//*[@data-anggota-terpilih]')->item(0)->textContent));
            $this->assertSame(0, $xpath->query('//*[@data-no-terpilih]')->length);
        }
    }

    public function test_pilih_anggota_nilai_lama_kembali_dan_error_tampil_di_bawah_pemicu(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);

        // Error validasi di field lain: anggota yang dipilih kembali dari old('anggota_id').
        $html = $this->actingAs($this->admin)->followingRedirects()
            ->from(route('transaksi.setor.create'))
            ->post(route('transaksi.setor.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'anggota_id' => $budi->id,
                'jenis_simpanan_id' => $this->pokok->id,
                'jumlah' => 'abc',
                'tanggal_transaksi' => '2026-11-12',
            ])->getContent();
        $xpath = $this->xpath($html);
        $this->assertSame((string) $budi->id, $xpath->query('//input[@name="anggota_id"]')->item(0)->getAttribute('value'));
        $this->assertSame("{$budi->no_anggota} - Budi Santoso", trim($xpath->query('//*[@data-anggota-terpilih]')->item(0)->textContent));
        $this->assertSame(0, TransaksiSimpanan::count());

        // Anggota kosong: pesan tampil di dalam pemilih, tepat di bawah pemicu, dan pemicu bertepi merah.
        $xpath = $this->xpath($this->actingAs($this->admin)->followingRedirects()
            ->from(route('transaksi.tarik.create'))
            ->post(route('transaksi.tarik.store'), [
                'kode_referensi' => (string) Str::uuid(),
                'jumlah' => '1000',
                'tanggal_transaksi' => '2026-11-12',
            ])->getContent());
        $pemicu = $xpath->query('//*[@data-pilih-anggota-cari]//button[@data-pemicu-anggota]')->item(0);
        $this->assertStringContainsString('border-red-300', $pemicu->getAttribute('class'));
        $sesudah = $xpath->query('following-sibling::*[1]', $pemicu)->item(0);
        $this->assertSame('p', $sesudah->nodeName);
        $this->assertStringContainsString('text-red-700', $sesudah->getAttribute('class'));
        $this->assertNotSame('', trim($sesudah->textContent));
    }

    public function test_pilih_anggota_di_form_tarik_menampilkan_saldo_sukarela_yang_benar(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $siti = Anggota::factory()->create(['nama' => 'Siti Aminah']);
        $this->service->setor($budi->id, $this->sukarela->id, 200000, '2026-11-01', $this->admin->id);
        $this->service->tarik($budi->id, 50000, '2026-11-02', $this->admin->id);
        $this->service->setor($budi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($siti->id, $this->wajib->id, 30000, '2026-11-01', $this->admin->id);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.tarik.create'))->getContent());
        $saldo = fn (Anggota $anggota) => $xpath->query('//*[@data-opsi-anggota="'.$anggota->id.'"]//*[@data-saldo-opsi]')->item(0);

        $this->assertSame('Rp 150.000', trim($saldo($budi)->textContent));
        $this->assertSame('Rp 0', trim($saldo($siti)->textContent));
        $this->assertStringContainsString('text-right', $saldo($budi)->getAttribute('class'));
        $this->assertStringContainsString('tabular-nums', $saldo($budi)->getAttribute('class'));

        // Form setor tidak menampilkan saldo di opsi.
        $this->actingAs($this->admin)->get(route('transaksi.setor.create'))->assertDontSee('data-saldo-opsi', false);
    }

    public function test_isi_cepat_enam_tombol_di_setor_dan_tujuh_di_tarik(): void
    {
        $nominal = ['50000', '100000', '200000', '300000', '500000', '1000000'];
        $teks = ['Rp 50.000', 'Rp 100.000', 'Rp 200.000', 'Rp 300.000', 'Rp 500.000', 'Rp 1.000.000'];

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.setor.create'))->getContent());
        $tombol = iterator_to_array($xpath->query('//form//button[@type="button"][@data-isi-cepat]'));
        $this->assertSame($nominal, array_map(fn ($el) => $el->getAttribute('data-isi-cepat'), $tombol));
        $this->assertSame($teks, array_map(fn ($el) => trim($el->textContent), $tombol));
        $this->assertSame('Nominal setoran', trim($xpath->query('//*[@data-kotak-nominal]//label[@for="jumlah"]')->item(0)->textContent));
        $this->assertSame(0, $xpath->query('//*[@data-kotak-nominal]//*[@data-isi-cepat]')->length, 'Isi cepat berada di bawah kotak nominal.');

        $budi = Anggota::factory()->create();
        $this->service->setor($budi->id, $this->sukarela->id, 150000, '2026-11-01', $this->admin->id);

        // Tarik tanpa anggota: enam nominal aktif, "Semua saldo" nonaktif.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.tarik.create'))->getContent());
        $tombol = iterator_to_array($xpath->query('//form//button[@type="button"][@data-isi-cepat]'));
        $this->assertCount(7, $tombol);
        $this->assertSame([...$nominal, 'semua'], array_map(fn ($el) => $el->getAttribute('data-isi-cepat'), $tombol));
        $this->assertSame('Semua saldo', trim($tombol[6]->textContent));
        $this->assertSame([false, false, false, false, false, false, true], array_map(fn ($el) => $el->hasAttribute('disabled'), $tombol));
        $this->assertSame('Nominal penarikan', trim($xpath->query('//*[@data-kotak-nominal]//label[@for="jumlah"]')->item(0)->textContent));

        // Anggota dengan saldo 150.000: nominal di atas saldo nonaktif; "Semua saldo" berisi seluruh saldo.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.tarik.create', ['anggota_id' => $budi->id]))->getContent());
        $tombol = iterator_to_array($xpath->query('//form//button[@data-isi-cepat]'));
        $this->assertSame([false, false, true, true, true, true, false], array_map(fn ($el) => $el->hasAttribute('disabled'), $tombol));
        $this->assertSame('150000', $tombol[6]->getAttribute('data-nilai'));

        // Klik mengganti (bukan menambah) isi input lalu memicu event input dan fokus.
        $app = file_get_contents(resource_path('js/app.js'));
        $this->assertStringContainsString('jumlah.value = nilaiTombol(el);', $app);
        $this->assertStringContainsString("jumlah.dispatchEvent(new Event('input', { bubbles: true }));", $app);
        $this->assertStringContainsString('jumlah.focus();', $app);
    }

    public function test_form_tarik_modal_konfirmasi_empat_baris_dan_kiriman_akhir_lewat_cegah_kirim_ganda(): void
    {
        $html = $this->actingAs($this->admin)->get(route('transaksi.tarik.create'))->assertOk()->getContent();
        $xpath = $this->xpath($html);

        $this->assertSame(1, $xpath->query('//*[@data-kotak-nominal]//*[@data-saldo-tersedia]')->length);

        // C.7: modal di luar form, empat baris berurutan; sisa saldo setelah garis pemisah, tebal, netral.
        $modal = $xpath->query('//*[@data-konfirmasi-tarik]');
        $this->assertSame(1, $modal->length);
        $this->assertSame(0, $xpath->query('//form//*[@data-konfirmasi-tarik]')->length);
        $baris = iterator_to_array($xpath->query('//*[@data-konfirmasi-tarik]//*[@data-konfirmasi-baris]'));
        $this->assertSame(['anggota', 'jenis', 'jumlah', 'sisa'], array_map(fn ($el) => $el->getAttribute('data-konfirmasi-baris'), $baris));
        $this->assertSame(['Anggota', 'Jenis simpanan', 'Jumlah penarikan', 'Sisa saldo'], array_map(fn ($el) => trim($xpath->query('./dt', $el)->item(0)->textContent), $baris));
        $this->assertSame('Simpanan Sukarela', trim($xpath->query('./dd', $baris[1])->item(0)->textContent));
        $this->assertStringContainsString('border-t', $baris[3]->getAttribute('class'));
        $sisa = $xpath->query('./dd', $baris[3])->item(0)->getAttribute('class');
        $this->assertStringContainsString('font-semibold', $sisa);
        $this->assertStringContainsString('text-stone-900', $sisa);

        // Tombol modal: Batal (secondary) dan Konfirmasi penarikan (primary), keduanya type=button.
        $batal = $xpath->query('//*[@data-konfirmasi-tarik]//button[normalize-space()="Batal"]')->item(0);
        $kirim = $xpath->query('//*[@data-konfirmasi-tarik]//button[@data-konfirmasi-kirim]')->item(0);
        $this->assertSame('button', $batal->getAttribute('type'));
        $this->assertStringContainsString('border-stone-300', $batal->getAttribute('class'));
        $this->assertSame('Konfirmasi penarikan', trim($kirim->textContent));
        $this->assertSame('button', $kirim->getAttribute('type'));
        $this->assertStringContainsString('bg-arang', $kirim->getAttribute('class'));

        // Kiriman pertama dicegat (preventDefault), konfirmasi memanggil requestSubmit() sehingga cegah-kirim-ganda tetap bekerja.
        $js = file_get_contents(resource_path('js/transaksi-tarik.js'));
        $this->assertStringContainsString("form.addEventListener('submit'", $js);
        $this->assertStringContainsString('event.preventDefault();', $js);
        $this->assertStringContainsString("new CustomEvent('open-modal', { detail: 'konfirmasi-tarik' })", $js);
        $this->assertStringContainsString('form.requestSubmit();', $js);
        $this->assertStringNotContainsString('form.submit()', $js);
        $this->assertSame(1, substr_count($html, 'data-cegah-kirim-ganda'));
        $this->assertStringNotContainsString('onsubmit=', $html);
    }

    public function test_riwayat_jumlah_transaksi_berpemisah_ribuan(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $baris = [];
        for ($i = 1; $i <= 1001; $i++) {
            $baris[] = [
                'anggota_id' => $anggota->id,
                'jenis_simpanan_id' => $this->sukarela->id,
                'staff_id' => $this->admin->id,
                'jenis_transaksi' => TransaksiSimpanan::SETOR,
                'jumlah' => 1000,
                'saldo_akhir' => 1000 * $i,
                'tanggal_transaksi' => '2026-11-01',
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }
        foreach (array_chunk($baris, 200) as $potongan) {
            TransaksiSimpanan::insert($potongan);
        }

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent());

        $this->assertSame('1.001 transaksi', trim($xpath->query('//*[@data-ringkasan-jumlah]')->item(0)->textContent));
        $this->assertSame('Semua waktu · 1.001 transaksi · Setor Rp 1.001.000 · Tarik Rp 0 · Bersih +Rp 1.001.000', trim(preg_replace('/\s+/', ' ', $xpath->query('//*[@data-ringkasan-riwayat-transaksi]')->item(0)->textContent)));
    }

    public function test_riwayat_anggota_avatar_tautan_dan_penanda_nonaktif_di_baris_nomor(): void
    {
        $aktif = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $keluar = Anggota::factory()->create(['nama' => 'Andi Pratama']);
        $this->service->setor($aktif->id, $this->sukarela->id, 50000, '2026-11-01', $this->admin->id);
        $this->service->setor($keluar->id, $this->sukarela->id, 70000, '2026-11-02', $this->admin->id);
        $keluar->update(['status' => Anggota::STATUS_NONAKTIF]);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);

        // Revisi 2 pasca-F4b: lebar mengikuti isi (tanpa table-fixed/colgroup), w-max min-w-full, gulir di dalam kartu.
        $tabel = $xpath->query('//*[@data-kartu-riwayat-transaksi]//*[@data-gulir-riwayat]/div[contains(@class, "overflow-x-auto")]/table[contains(@class, "tabel")]');
        $this->assertSame(1, $tabel->length);
        $kelasTabel = explode(' ', $tabel->item(0)->getAttribute('class'));
        $this->assertContains('w-max', $kelasTabel);
        $this->assertContains('min-w-full', $kelasTabel);
        $this->assertNotContains('table-fixed', $kelasTabel);
        $this->assertSame(0, $xpath->query('//colgroup')->length);

        foreach ([[$aktif, false], [$keluar, true]] as [$anggota, $nonaktif]) {
            $sel = $xpath->query('//tbody/tr[.//a[@href="'.route('anggota.show', $anggota).'"]]/td[1]')->item(0);
            $this->assertNotNull($sel, $anggota->nama);

            // Kolom Anggota menempel di kiri dengan latar baris (termasuk saat hover).
            $kelasSel = explode(' ', $sel->getAttribute('class'));
            foreach (['sticky', 'left-0', 'z-10', 'bg-white', 'group-hover:bg-stone-50', 'whitespace-nowrap'] as $kelas) {
                $this->assertContains($kelas, $kelasSel, $kelas);
            }
            $this->assertStringContainsString('group', $xpath->query('..', $sel)->item(0)->getAttribute('class'));

            $this->assertSame(1, $xpath->query('.//span[@aria-hidden="true"][contains(@class, "rounded-full")][contains(@class, "w-7")]', $sel)->length, 'Avatar sm.');
            $tautan = $xpath->query('.//a[@data-tautan-detail]', $sel)->item(0);
            $this->assertSame($anggota->nama, trim($tautan->textContent));
            $kelasTautan = explode(' ', $tautan->getAttribute('class'));
            $this->assertContains('hover:underline', $kelasTautan);
            $this->assertNotContains('underline', $kelasTautan, 'Garis bawah hanya saat hover.');

            $baris = $xpath->query('.//*[@data-baris-no-anggota]', $sel)->item(0);
            $this->assertSame($anggota->no_anggota, trim($xpath->query('./*[contains(@class, "font-mono")]', $baris)->item(0)->textContent));
            $this->assertStringContainsString('text-xs', $baris->getAttribute('class'));
            $penanda = $xpath->query('.//*[@data-penanda-nonaktif]', $baris);
            $this->assertSame($nonaktif ? 1 : 0, $penanda->length, $anggota->nama);
            if ($nonaktif) {
                $this->assertSame("{$anggota->no_anggota} · Nonaktif", trim(preg_replace('/\s+/', ' ', $baris->textContent)));
                $kelasPenanda = explode(' ', $penanda->item(0)->getAttribute('class'));
                $this->assertContains('text-stone-600', $kelasPenanda);
                $this->assertNotContains('border', $kelasPenanda, 'Tanpa pil.');
                $titik = $xpath->query('./span[@aria-hidden="true"]', $penanda->item(0))->item(0)->getAttribute('class');
                $this->assertStringContainsString('h-1.5 w-1.5', $titik);
                $this->assertStringContainsString('bg-red-600', $titik);
            }
        }

        // Badge status tidak lagi dipakai di tabel.
        $this->assertSame(0, $xpath->query('//table//*[@data-status-anggota]')->length);

        // Kolom uang rata kanan (.num).
        $judulNum = array_map(fn ($th) => trim($th->textContent), iterator_to_array($xpath->query('//thead//th[contains(concat(" ", @class, " "), " num ")]')));
        $this->assertSame(['Jumlah', 'Saldo setelah transaksi'], $judulNum);
    }

    // ---------------------------------------------------------------
    // Revisi 2 pasca-F4b — tabel Riwayat mengikuti isi, kolom cari gabungan, ringkasan Bersih
    // ---------------------------------------------------------------

    public function test_riwayat_tujuh_kolom_tanggal_lengkap_dan_tanpa_truncate_selain_catatan(): void
    {
        $anggota = Anggota::factory()->create();
        $setor = $this->service->setor($anggota->id, $this->sukarela->id, 150000, '2026-11-17', $this->admin->id);
        $setor->update(['keterangan' => 'Catatan panjang untuk diuji']);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);

        $kepala = array_map(fn ($th) => trim($th->textContent), iterator_to_array($xpath->query('//*[@data-kartu-riwayat-transaksi]//thead//th')));
        $this->assertSame(['Anggota', 'Tanggal', 'Transaksi', 'Jumlah', 'Saldo setelah transaksi', 'Dicatat oleh', 'Catatan'], $kepala);
        $this->assertSame(0, $xpath->query('//tbody//td[@data-sel-jenis]')->length, 'Kolom Jenis terpisah dihapus.');

        // Tanggal lengkap lewat Format::tanggal (bulan ditulis penuh), tetap satu baris.
        $tanggal = $xpath->query('//tbody//td[@data-sel-tanggal]')->item(0);
        $this->assertSame('17 November 2026', trim($tanggal->textContent));
        $this->assertContains('whitespace-nowrap', explode(' ', $tanggal->getAttribute('class')));

        // Catatan maksimal 2 baris (line-clamp-2 memberi "…" di browser), lebar dibatasi supaya bisa turun baris, title lengkap.
        $catatan = $xpath->query('//tbody//*[@data-catatan]')->item(0);
        $this->assertSame('Catatan panjang untuk diuji', $catatan->getAttribute('title'));
        $kelasCatatan = explode(' ', $catatan->getAttribute('class'));
        $this->assertContains('line-clamp-2', $kelasCatatan);
        $this->assertContains('max-w-[22rem]', $kelasCatatan);
        $this->assertNotContains('block', $kelasCatatan, 'display:block akan membatalkan -webkit-box milik line-clamp.');

        // Semua sel satu baris (whitespace-nowrap) kecuali Catatan; tidak ada truncate di tabel.
        foreach ($xpath->query('//table//th | //table//td') as $sel) {
            $kelas = explode(' ', $sel->getAttribute('class'));
            $this->assertNotContains('truncate', $kelas);
            if ($sel->nodeName === 'td' && $sel->hasAttribute('data-sel-catatan')) {
                $this->assertContains('whitespace-normal', $kelas);
            } elseif (! ($sel->nodeName === 'th' && trim($sel->textContent) === 'Catatan')) {
                $this->assertContains('whitespace-nowrap', $kelas, trim($sel->textContent));
            }
        }
        $this->assertSame(1, preg_match('/<table.*?<\/table>/s', $html, $tabel));
        $this->assertStringNotContainsString('truncate', $tabel[0]);

        // Dicatat oleh tampil penuh.
        $this->assertSame($this->admin->nama, trim($xpath->query('//tbody//td[@data-sel-staf]')->item(0)->textContent));

        // Petunjuk geser (Alpine, scroll/resize) dan bayangan kolom tempel saat tergeser; header tidak menempel.
        $area = $xpath->query('//*[@data-area-riwayat]')->item(0);
        $this->assertSame('ukur()', $area->getAttribute('x-on:resize.window'));
        $this->assertSame('tergeser', $area->getAttribute('x-bind:data-tergeser'));
        $gulir = $xpath->query('.//*[@data-gulir-riwayat]', $area)->item(0);
        $this->assertSame('ukur()', $xpath->query('./div[contains(@class, "overflow-x-auto")]', $gulir)->item(0)->getAttribute('x-on:scroll.passive'));
        $this->assertSame('bisaKanan', $xpath->query('.//*[@data-petunjuk-geser]', $gulir)->item(0)->getAttribute('x-show'));
        $this->assertStringContainsString('[[data-tergeser]_&]:shadow-', $xpath->query('//tbody//td[@data-kolom-tempel]')->item(0)->getAttribute('class'));
        $this->assertSame(0, $xpath->query('//thead//th[contains(concat(" ", @class, " "), " top-0 ")]')->length);
    }

    public function test_riwayat_tombol_geser_tabel_di_baris_ringkasan_memakai_state_yang_sama(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 150000, '2026-11-01', $this->admin->id);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);
        $area = $xpath->query('//*[@data-area-riwayat]')->item(0);

        // Satu state Alpine untuk tombol, bayangan kolom tempel, dan petunjuk geser.
        $state = $area->getAttribute('x-data');
        foreach (['ada: false', 'tergeser: false', 'bisaKanan: false', "scrollBy({ left: arah * 320, behavior: 'smooth' })"] as $potongan) {
            $this->assertStringContainsString($potongan, $state);
        }
        $this->assertSame(0, $xpath->query('.//*[@x-data]', $area)->length, 'Tombol dan tabel tidak punya state Alpine sendiri.');

        // Tombol di ujung kanan baris ringkasan; hanya tampil saat tabel lebih lebar dari wadahnya.
        $ringkasan = $xpath->query('.//*[@data-ringkasan-riwayat-transaksi]', $area)->item(0);
        $baris = $xpath->query('..', $ringkasan)->item(0);
        $this->assertStringContainsString('justify-between', $baris->getAttribute('class'));
        $grup = $xpath->query('./div[.//*[@data-geser-tabel]]', $baris)->item(0);
        $this->assertSame('ada', $grup->getAttribute('x-show'));
        $this->assertTrue($grup->hasAttribute('x-cloak'));

        $tombol = iterator_to_array($xpath->query('.//button[@data-geser-tabel]', $grup));
        $this->assertSame(['kiri', 'kanan'], array_map(fn ($el) => $el->getAttribute('data-geser-tabel'), $tombol));
        foreach ([[$tombol[0], 'kiri', 'chevron-left', 'geser(-1)', '! tergeser'], [$tombol[1], 'kanan', 'chevron-right', 'geser(1)', '! bisaKanan']] as [$el, $arah, $ikon, $klik, $nonaktif]) {
            $this->assertSame('button', $el->getAttribute('type'));
            $this->assertSame("Geser tabel ke {$arah}", $el->getAttribute('aria-label'));
            $this->assertSame("Geser tabel ke {$arah} (Shift + gulir mouse juga bisa)", $el->getAttribute('title'));
            $this->assertSame($klik, $el->getAttribute('x-on:click'));
            $this->assertSame($nonaktif, $el->getAttribute('x-bind:disabled'));
            $kelas = explode(' ', $el->getAttribute('class'));
            $this->assertContains('h-8', $kelas, 'Ukuran sm.');
            $this->assertContains('w-8', $kelas, 'Square.');
            $this->assertContains('hover:bg-stone-100', $kelas, 'Ghost.');
            $this->assertSame(1, $xpath->query('.//*[local-name()="svg"]', $el)->length);
            $this->assertMatchesRegularExpression(
                '/data-geser-tabel="'.$arah.'".*?<x-ui\.icon name="'.$ikon.'"/s',
                file_get_contents(resource_path('views/transaksi/riwayat.blade.php'))
            );
        }

        // Wadah tabel bisa difokus dan diberi label.
        $wadah = $xpath->query('.//*[@data-gulir-riwayat]/div[contains(@class, "overflow-x-auto")]', $area)->item(0);
        $this->assertSame('0', $wadah->getAttribute('tabindex'));
        $this->assertSame('Tabel riwayat transaksi, bisa digeser', $wadah->getAttribute('aria-label'));
        $this->assertSame('gulir', $wadah->getAttribute('x-ref'));

        // Tanpa transaksi: tidak ada tombol geser.
        TransaksiSimpanan::query()->delete();
        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->assertDontSee('data-geser-tabel', false);
    }

    public function test_riwayat_pil_transaksi_berisi_jenis_tanpa_awalan_simpanan(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($anggota->id, $this->sukarela->id, 150000, '2026-11-01', $this->admin->id);
        $this->service->tarik($anggota->id, 50000, '2026-11-02', $this->admin->id);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);

        // Spasi visual dari gap flex; teks dibandingkan tanpa spasi.
        $teks = fn ($el) => preg_replace('/\s+/', '', $el->textContent);
        $pil = iterator_to_array($xpath->query('//tbody//*[@data-pil-transaksi]'));
        $this->assertEqualsCanonicalizing(['Setor·Pokok', 'Setor·Sukarela', 'Tarik·Sukarela'], array_map($teks, $pil));

        foreach (['setor' => ['Setor', 'bg-setor'], 'tarik' => ['Tarik', 'bg-tarik']] as $jenis => [$label, $titik]) {
            $el = $xpath->query('//tr[@data-transaksi="'.$jenis.'"]//*[@data-pil-transaksi="'.$jenis.'"]')->item(0);
            foreach (['rounded-full', 'border', 'border-stone-300', 'bg-white', 'text-[13px]'] as $kelas) {
                $this->assertContains($kelas, explode(' ', $el->getAttribute('class')), "{$jenis}: {$kelas}");
            }
            $bulatan = $xpath->query('./span[@aria-hidden="true"]', $el)->item(0)->getAttribute('class');
            $this->assertStringContainsString('h-1.5 w-1.5', $bulatan);
            $this->assertContains($titik, explode(' ', $bulatan));
            $this->assertSame($label, trim($xpath->query('./span[contains(@class, "font-medium")]', $el)->item(0)->textContent));
            $jenisEl = $xpath->query('./*[@data-pil-jenis]', $el)->item(0);
            $this->assertStringContainsString('text-stone-500', $jenisEl->getAttribute('class'));
            $this->assertStringStartsWith('· ', trim($jenisEl->textContent));
            $this->assertStringNotContainsString('Simpanan', $jenisEl->textContent);
        }

        // x-ui.jenis-transaksi (kotak ikon) tidak dipakai di tabel Riwayat.
        $this->assertSame(1, preg_match('/<tbody>.*?<\/tbody>/s', $html, $tbody));
        $this->assertStringNotContainsString('bg-setor-50', $tbody[0]);
        $this->assertStringNotContainsString('h-9 w-9', $tbody[0]);
    }

    public function test_riwayat_ringkasan_label_periode_dan_bersih(): void
    {
        $budi = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->service->setor($budi->id, $this->sukarela->id, 200000, '2026-11-10', $this->admin->id);
        $this->service->tarik($budi->id, 50000, '2026-11-11', $this->admin->id);
        $this->service->tarik($budi->id, 150000, '2026-11-12', $this->admin->id);

        $ambil = function (array $query) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', $query))->getContent());

            return [
                trim($xpath->query('//*[@data-ringkasan-periode]')->item(0)->textContent),
                $xpath->query('//*[@data-ringkasan-bersih]')->item(0),
            ];
        };

        // Bersih positif: naik.
        [$periode, $bersih] = $ambil(['jenis_transaksi' => 'setor']);
        $this->assertSame('Semua waktu', $periode);
        $this->assertSame('+Rp 200.000', trim($bersih->textContent));
        $this->assertContains('text-naik', explode(' ', $bersih->getAttribute('class')));

        // Bersih negatif: turun, tanda ASCII.
        [$periode, $bersih] = $ambil(['rentang' => '7_hari', 'jenis_transaksi' => 'tarik']);
        $this->assertSame('7 hari', $periode);
        $this->assertSame('-Rp 200.000', trim($bersih->textContent));
        $this->assertContains('text-turun', explode(' ', $bersih->getAttribute('class')));

        // Bersih nol: tanpa tanda, stone-900.
        [$periode, $bersih] = $ambil(['rentang' => 'bulan_ini']);
        $this->assertSame('Bulan ini', $periode);
        $this->assertSame('Rp 0', trim($bersih->textContent));
        $this->assertContains('text-stone-900', explode(' ', $bersih->getAttribute('class')));

        // Rentang kustom: tanggal yang dipilih.
        [$periode] = $ambil(['rentang' => 'kustom', 'dari' => '2026-11-01', 'sampai' => '2026-11-12']);
        $this->assertSame('1 Nov 2026 – 12 Nov 2026', $periode);
    }

    public function test_riwayat_kolom_cari_gabungan_dengan_pilihan_anggota(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $andi = Anggota::factory()->nonaktif()->create(['nama' => 'Andi Keluar']);

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat'))->getContent();
        $xpath = $this->xpath($html);
        $bilah = '//form[@data-filter-riwayat]';

        // Satu isian selebar penuh; pemilih anggota terpisah dan select lama hilang.
        $wadah = $xpath->query($bilah.'//*[@data-pilih-anggota]');
        $this->assertSame(1, $wadah->length);
        $this->assertSame(0, $xpath->query('//*[@data-pilih-anggota-cari]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-pemicu-anggota]')->length);
        $this->assertSame(0, $xpath->query('//select[@name="anggota_id"]')->length);
        $this->assertStringNotContainsString('grid-cols', $xpath->query('..', $wadah->item(0))->item(0)->getAttribute('class') ?? '');

        // Input cari dan anggota_id tetap; keduanya di wadah gabungan.
        $cari = $xpath->query('.//input[@type="search"][@name="cari"]', $wadah->item(0));
        $this->assertSame(1, $cari->length);
        $this->assertSame('Cari nama atau no. anggota', $cari->item(0)->getAttribute('placeholder'));
        $this->assertSame(1, $xpath->query('.//input[@type="hidden"][@name="anggota_id"]', $wadah->item(0))->length);
        $this->assertSame(1, $xpath->query($bilah.'//input[@name="cari"]')->length);
        $this->assertSame(1, $xpath->query($bilah.'//input[@name="anggota_id"]')->length);

        // Panel: "Semua anggota" lalu semua anggota (termasuk nonaktif) berformat "{no} - {nama}".
        $opsi = iterator_to_array($xpath->query('.//li[@data-opsi-anggota]', $wadah->item(0)));
        $this->assertSame(['', (string) $andi->id, (string) $budi->id], array_map(fn ($el) => $el->getAttribute('data-opsi-anggota'), $opsi));
        $this->assertSame(
            ['Semua anggota', "{$andi->no_anggota} - Andi Keluar (Nonaktif)", "{$budi->no_anggota} - Budi Santoso"],
            array_map(fn ($el) => trim($el->textContent), $opsi)
        );
        $this->assertSame(mb_strtolower("Budi Santoso {$budi->no_anggota}"), $opsi[2]->getAttribute('data-kunci'));
        $this->assertSame(1, $xpath->query('.//*[contains(@class, "max-h-72")][contains(@class, "overflow-y-auto")]', $wadah->item(0))->length);

        // Tanpa filter anggota: tidak ada teks terpilih dan tombol hapus.
        $this->assertSame(0, $xpath->query('//*[@data-anggota-terpilih]')->length);
        $this->assertSame(0, $xpath->query('//*[@data-hapus-anggota]')->length);

        // Perilaku: pilih = set anggota_id, kosongkan cari, kirim; Enter tanpa sorotan = kirim cari dan hapus anggota_id.
        $this->assertSame(1, preg_match('/<div class="relative" data-pilih-anggota.*?<\/ul>/s', $html, $komponen));
        foreach ([
            'this.$refs.anggota.value = el.dataset.opsiAnggota;',
            "this.cari = '';",
            'this.kirimForm();',
            'if (el) { event.preventDefault(); this.pilih(el); return; }',
            "this.\$refs.anggota.value = '';",
            "hapusAnggota() { this.\$refs.anggota.value = ''; this.kirimForm() }",
        ] as $potongan) {
            $this->assertStringContainsString($potongan, $komponen[0], $potongan);
        }

        // Anggota terpilih tampil di dalam isian dengan tombol hapus; input cari tetap ada.
        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.riwayat', ['anggota_id' => $andi->id, 'cari' => 'x']))->getContent());
        $wadah = $xpath->query('//*[@data-pilih-anggota]')->item(0);
        $this->assertSame((string) $andi->id, $xpath->query('.//input[@name="anggota_id"]', $wadah)->item(0)->getAttribute('value'));
        $isian = $xpath->query('.//*[@data-isian-cari]', $wadah)->item(0);
        $this->assertSame("{$andi->no_anggota} - Andi Keluar (Nonaktif)", trim($xpath->query('.//*[@data-anggota-terpilih]', $isian)->item(0)->textContent));
        $hapus = $xpath->query('.//button[@data-hapus-anggota]', $isian)->item(0);
        $this->assertSame('button', $hapus->getAttribute('type'));
        $this->assertSame('Hapus filter anggota', $hapus->getAttribute('aria-label'));
        $this->assertSame('hapusAnggota()', $hapus->getAttribute('x-on:click'));
        $this->assertSame('x', $xpath->query('.//input[@name="cari"]', $isian)->item(0)->getAttribute('value'));
    }

    public function test_pemilih_anggota_format_no_nama_di_setor_dan_tarik(): void
    {
        $budi = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $andi = Anggota::factory()->nonaktif()->create(['nama' => 'Andi Keluar']);
        $this->service->setor($budi->id, $this->sukarela->id, 150000, '2026-11-01', $this->admin->id);

        // Setor dan tarik: hanya anggota aktif, "{no} - {nama}"; di tarik saldo sukarela di kanan opsi yang sama.
        foreach (['transaksi.setor.create' => null, 'transaksi.tarik.create' => 'Rp 150.000'] as $route => $saldo) {
            $xpath = $this->xpath($this->actingAs($this->admin)->get(route($route))->getContent());
            $opsi = $xpath->query('//*[@data-pilih-anggota-cari]//li[@data-opsi-anggota]');
            $this->assertSame(1, $opsi->length, $route);
            $this->assertSame("{$budi->no_anggota} - Budi Santoso", trim($xpath->query('./*[@data-label-opsi]', $opsi->item(0))->item(0)->textContent));
            $this->assertSame("{$budi->no_anggota} - Budi Santoso", $opsi->item(0)->getAttribute('data-label'));
            $this->assertSame(0, $xpath->query('//*[@data-opsi-anggota="'.$andi->id.'"]')->length, $route);
            $sel = $xpath->query('./*[@data-saldo-opsi]', $opsi->item(0));
            if ($saldo === null) {
                $this->assertSame(0, $sel->length);
            } else {
                $this->assertSame($saldo, trim($sel->item(0)->textContent));
                $this->assertStringContainsString('text-right', $sel->item(0)->getAttribute('class'));
                $this->assertStringContainsString('tabular-nums', $sel->item(0)->getAttribute('class'));
                $this->assertStringNotContainsString('font-mono', $sel->item(0)->getAttribute('class'));
            }
        }
    }

    public function test_kartu_jenis_setor_menampilkan_keterangan_dari_database(): void
    {
        $this->wajib->update(['keterangan' => 'Teks uji dari database']);

        $xpath = $this->xpath($this->actingAs($this->admin)->get(route('transaksi.setor.create'))->getContent());
        $kartu = $xpath->query('//fieldset[@data-pilihan-jenis]//*[@data-kartu-jenis]');

        $this->assertSame(3, $kartu->length);
        $this->assertSame(
            ['Setoran awal saat jadi anggota', 'Teks uji dari database', 'Bisa disetor kapan saja'],
            array_map(fn ($el) => trim($el->textContent), iterator_to_array($xpath->query('//*[@data-keterangan-jenis]'))),
        );
        $this->assertStringContainsString('p-4', $kartu->item(0)->getAttribute('class'));
        $this->assertStringContainsString('font-semibold', $xpath->query('./span[1]', $kartu->item(0))->item(0)->getAttribute('class'));
        $this->assertStringContainsString('text-stone-500', $xpath->query('.//*[@data-keterangan-jenis]', $kartu->item(0))->item(0)->getAttribute('class'));
        $this->assertStringContainsString('peer-checked:ring-arang', $kartu->item(0)->getAttribute('class'));

        // Teks keterangan tidak ditulis di Blade.
        $blade = file_get_contents(resource_path('views/transaksi/setor.blade.php'));
        foreach (['Setoran awal saat jadi anggota', 'Setoran rutin tiap bulan', 'Bisa disetor kapan saja'] as $teks) {
            $this->assertStringNotContainsString($teks, $blade);
        }
    }

    public function test_seeder_jenis_simpanan_memakai_teks_baru_dan_aman_dijalankan_ulang(): void
    {
        // setUp sudah menjalankan seeder sekali; isi lama dari MySQL disimulasikan lalu seeder dijalankan lagi.
        $this->pokok->update(['keterangan' => 'Dibayar sekali saat pertama kali mendaftar menjadi anggota']);
        $id = JenisSimpanan::orderBy('id')->pluck('id')->all();

        $this->seed(JenisSimpananSeeder::class);
        $this->seed(JenisSimpananSeeder::class);

        $this->assertSame(3, JenisSimpanan::count());
        $this->assertSame($id, JenisSimpanan::orderBy('id')->pluck('id')->all());
        $this->assertSame([
            JenisSimpanan::POKOK => 'Setoran awal saat jadi anggota',
            JenisSimpanan::WAJIB => 'Setoran rutin tiap bulan',
            JenisSimpanan::SUKARELA => 'Bisa disetor kapan saja',
        ], JenisSimpanan::orderBy('id')->pluck('keterangan', 'nama_jenis')->all());
    }

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
