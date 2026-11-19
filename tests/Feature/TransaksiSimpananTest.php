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
        $this->actingAs($this->admin)->get(route('transaksi.tarik.create'))
            ->assertSee('Penarikan hanya untuk Simpanan Sukarela. Simpanan Pokok dan Wajib dikembalikan saat anggota keluar dari koperasi.');
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

    public function test_form_tarik_data_saldo_hanya_anggota_aktif_badge_dan_tanpa_field_jenis(): void
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

        $this->assertSame('Simpanan Sukarela', trim($xpath->query('//*[@data-jenis-tarik]')->item(0)->textContent));
        $this->assertStringNotContainsString('bg-emas', $xpath->query('//*[@data-jenis-tarik]')->item(0)->getAttribute('class'));
        $this->assertSame(0, $xpath->query('//*[@name="jenis_simpanan_id"]')->length);
        $this->assertStringNotContainsString('(Saldo Sukarela:', $html);

        // Tanpa anggota terpilih, kotak saldo tersembunyi; peringatan tersedia tetapi tersembunyi.
        $this->assertTrue($xpath->query('//*[@data-kotak-saldo]')->item(0)->hasAttribute('hidden'));
        $peringatan = $xpath->query('//*[@data-peringatan-saldo]')->item(0);
        $this->assertTrue($peringatan->hasAttribute('hidden'));
        $this->assertSame('Melebihi saldo tersedia.', trim($peringatan->textContent));
    }

    public function test_form_tarik_kotak_saldo_langsung_tampil_untuk_anggota_terpilih(): void
    {
        $budi = Anggota::factory()->create();
        $this->service->setor($budi->id, $this->sukarela->id, 125000, '2026-11-01', $this->admin->id);
        $baru = Anggota::factory()->create();

        $kotak = fn (array $query, array $old = []) => $this->xpath(
            $this->actingAs($this->admin)->withSession(['_old_input' => $old])->get(route('transaksi.tarik.create', $query))->getContent()
        );

        $xpath = $kotak(['anggota_id' => $budi->id]);
        $this->assertFalse($xpath->query('//*[@data-kotak-saldo]')->item(0)->hasAttribute('hidden'));
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

        $this->assertSame([['total_setor' => 330000.0, 'total_tarik' => 50000.0], '4 transaksi · Setor Rp 330.000 · Tarik Rp 50.000'], $ringkasan([]));
        $this->assertSame([['total_setor' => 300000.0, 'total_tarik' => 50000.0], '3 transaksi · Setor Rp 300.000 · Tarik Rp 50.000'], $ringkasan(['anggota_id' => $budi->id]));
        $this->assertSame([['total_setor' => 230000.0, 'total_tarik' => 50000.0], '3 transaksi · Setor Rp 230.000 · Tarik Rp 50.000'], $ringkasan(['rentang' => 'bulan_ini']));
        $this->assertSame([['total_setor' => 0.0, 'total_tarik' => 50000.0], '1 transaksi · Setor Rp 0 · Tarik Rp 50.000'], $ringkasan(['jenis_transaksi' => 'tarik']));
        $this->assertSame([['total_setor' => 30000.0, 'total_tarik' => 0.0], '1 transaksi · Setor Rp 30.000 · Tarik Rp 0'], $ringkasan(['cari' => 'siti']));
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
        $this->assertSame(['Tanggal', 'Anggota', 'Transaksi', 'Jumlah', 'Saldo jenis ini', 'Dicatat oleh', 'Catatan'], $kepala);
        $this->assertSame(1, $xpath->query('//th[@title="Saldo jenis simpanan ini setelah transaksi"]')->length);

        $this->assertSame('+Rp 150.000', trim($xpath->query('//tr[@data-transaksi="setor"]//*[@data-nominal]')->item(0)->textContent));
        $this->assertSame('-Rp 50.000', trim($xpath->query('//tr[@data-transaksi="tarik"]//*[@data-nominal]')->item(0)->textContent));
        $this->assertStringContainsString('text-setor', $xpath->query('//tr[@data-transaksi="setor"]//*[@data-nominal]')->item(0)->getAttribute('class'));
        $this->assertStringContainsString('text-tarik', $xpath->query('//tr[@data-transaksi="tarik"]//*[@data-nominal]')->item(0)->getAttribute('class'));
        $this->assertStringNotContainsString('−', $html);

        // Nama sekali lewat x-nama-anggota, no. anggota di bawahnya; tanpa avatar dan tanpa badge success/danger.
        $baris = $xpath->query('//tr[@data-transaksi="setor"]')->item(0);
        $this->assertSame(1, substr_count($baris->textContent, 'Budi Santoso'));
        $this->assertStringContainsString($anggota->no_anggota, $baris->textContent);
        $this->assertStringNotContainsString('bg-emerald-50', $html);

        $sel = $xpath->query('//*[@data-catatan]')->item(0);
        $this->assertSame($catatan, $sel->getAttribute('title'));
        $this->assertSame(Str::limit($catatan, 40), trim($sel->textContent));
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

    private function xpath(string $html): \DOMXPath
    {
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($dom);
    }
}
