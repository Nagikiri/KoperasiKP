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
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null]);
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
            ->assertViewHas('filter', ['anggota_id' => (string) $a->id, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null]);

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
    // F2d H.2.1 — riwayat menerima filter dashboard (cari, jenis_transaksi, rentang)
    // ---------------------------------------------------------------

    public function test_riwayat_menerapkan_filter_dashboard_dan_membawanya_di_form(): void
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
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => 'budi', 'jenis_transaksi' => 'setor', 'rentang' => '30_hari'])
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$baru->id]);

        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['cari' => 'budi']))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$tarik->id, $baru->id, $lama->id]);
        $this->actingAs($this->admin)->get(route('transaksi.riwayat', ['jenis_transaksi' => 'tarik']))
            ->assertViewHas('riwayat', fn ($r) => $ids($r) === [$tarik->id]);

        // Form membawa filter dashboard sebagai input tersembunyi; baris "Juga difilter" tampil.
        $html = $response->getContent();
        $this->assertStringContainsString('<input type="hidden" name="cari" value="budi">', $html);
        $this->assertStringContainsString('<input type="hidden" name="jenis_transaksi" value="setor">', $html);
        $this->assertStringContainsString('<input type="hidden" name="rentang" value="30_hari">', $html);
        $this->assertMatchesRegularExpression('/<p data-filter-dashboard>\s*Juga difilter: Setoran · 30 hari · &quot;budi&quot;/', $html);
        // "Reset Filter" tetap hanya bergantung pada anggota_id/jenis_simpanan_id.
        $response->assertDontSee('Reset Filter');
    }

    public function test_riwayat_tautan_hapus_filter_dashboard_mempertahankan_anggota_dan_jenis(): void
    {
        $anggota = Anggota::factory()->create();

        $html = $this->actingAs($this->admin)->get(route('transaksi.riwayat', [
            'anggota_id' => $anggota->id, 'jenis_simpanan_id' => $this->sukarela->id, 'rentang' => 'hari_ini',
        ]))->getContent();

        preg_match('/<p data-filter-dashboard>.*?<a href="([^"]+)">Hapus<\/a>/s', $html, $cocok);
        $this->assertSame(route('transaksi.riwayat', ['anggota_id' => $anggota->id, 'jenis_simpanan_id' => $this->sukarela->id]), html_entity_decode($cocok[1]));

        $this->actingAs($this->admin)->get(route('transaksi.riwayat'))
            ->assertDontSee('data-filter-dashboard', false)
            ->assertDontSee('<input type="hidden" name="cari"', false);
    }

    public function test_riwayat_nilai_filter_dashboard_tidak_valid_diabaikan(): void
    {
        $anggota = Anggota::factory()->create();
        $this->service->setor($anggota->id, $this->sukarela->id, 50000, '2026-11-10', $this->admin->id);

        $response = $this->actingAs($this->admin)->get(route('transaksi.riwayat', [
            'cari' => '   ', 'jenis_transaksi' => 'pinjam', 'rentang' => 'kemarin',
        ]));

        $response->assertOk()
            ->assertViewHas('filter', ['anggota_id' => null, 'jenis_simpanan_id' => null, 'cari' => null, 'jenis_transaksi' => null, 'rentang' => null])
            ->assertViewHas('riwayat', fn ($r) => $r->count() === 1)
            ->assertDontSee('data-filter-dashboard', false);
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
}
