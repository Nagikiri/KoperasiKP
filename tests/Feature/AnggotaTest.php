<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnggotaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_tamu_diarahkan_ke_login(): void
    {
        $this->get(route('anggota.index'))->assertRedirect(route('login'));
        $this->get(route('anggota.create'))->assertRedirect(route('login'));
        $this->post(route('anggota.store'), $this->dataValid())->assertRedirect(route('login'));

        $this->assertSame(0, Anggota::count());
    }

    public function test_halaman_daftar_tambah_dan_ubah_tampil(): void
    {
        $anggota = Anggota::factory()->create();

        $this->actingAs($this->admin)->get(route('anggota.index'))->assertOk();
        $this->actingAs($this->admin)->get(route('anggota.create'))
            ->assertOk()
            ->assertSee('Nomor anggota dibuat otomatis oleh sistem.');
        $this->actingAs($this->admin)->get(route('anggota.edit', $anggota))
            ->assertOk()
            ->assertViewHas('anggota', fn ($a) => $a->is($anggota));
    }

    public function test_tambah_anggota_sukses_dengan_nomor_otomatis_dan_status_aktif(): void
    {
        $response = $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid());

        $anggota = Anggota::firstOrFail();

        $response->assertRedirect(route('anggota.show', $anggota));
        $response->assertSessionHas('success', 'Anggota Budi Santoso berhasil ditambahkan dengan nomor KSP-2026-0001.');

        $this->assertSame('KSP-2026-0001', $anggota->no_anggota);
        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->status);
        $this->assertSame('3201234567890001', $anggota->nik);
    }

    public function test_anggota_kedua_mendapat_nomor_urut_berikutnya(): void
    {
        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid());
        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid([
            'nama' => 'Siti Aminah',
            'nik' => '3201234567890002',
        ]));

        $this->assertSame(
            ['KSP-2026-0001', 'KSP-2026-0002'],
            Anggota::orderBy('id')->pluck('no_anggota')->all()
        );
    }

    public function test_nomor_urut_kembali_ke_0001_di_tahun_berikutnya(): void
    {
        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid());
        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid([
            'nik' => '3201234567890002',
        ]));

        Carbon::setTestNow('2027-01-02 09:00:00');

        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid([
            'nik' => '3201234567890003',
            'tanggal_bergabung' => '2027-01-02',
        ]));

        $this->assertSame(
            ['KSP-2026-0001', 'KSP-2026-0002', 'KSP-2027-0001'],
            Anggota::orderBy('id')->pluck('no_anggota')->all()
        );
    }

    public function test_no_anggota_dan_status_dari_request_diabaikan_saat_tambah(): void
    {
        $this->actingAs($this->admin)->post(route('anggota.store'), $this->dataValid([
            'no_anggota' => 'KSP-1999-9999',
            'status' => Anggota::STATUS_NONAKTIF,
        ]));

        $anggota = Anggota::firstOrFail();
        $this->assertSame('KSP-2026-0001', $anggota->no_anggota);
        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->status);
    }

    public function test_no_anggota_dan_status_dari_request_diabaikan_saat_ubah(): void
    {
        $anggota = Anggota::factory()->create();
        $nomorAsli = $anggota->no_anggota;

        $this->actingAs($this->admin)->put(route('anggota.update', $anggota), $this->dataValid([
            'nik' => $anggota->nik,
            'no_anggota' => 'KSP-1999-9999',
            'status' => Anggota::STATUS_NONAKTIF,
        ]))->assertSessionHasNoErrors();

        $anggota->refresh();
        $this->assertSame($nomorAsli, $anggota->no_anggota);
        $this->assertSame(Anggota::STATUS_AKTIF, $anggota->status);
    }

    public function test_nik_15_digit_ditolak(): void
    {
        $this->assertTambahDitolak(['nik' => '320123456789000'], 'nik');

        $this->assertSame(
            'NIK harus terdiri dari tepat 16 digit angka.',
            session('errors')->first('nik')
        );
    }

    public function test_nik_berisi_huruf_ditolak(): void
    {
        $this->assertTambahDitolak(['nik' => '32012345678900AB'], 'nik');
    }

    public function test_nik_duplikat_ditolak(): void
    {
        Anggota::factory()->create(['nik' => '3201234567890001']);

        $this->assertTambahDitolak(['nik' => '3201234567890001'], 'nik');

        $this->assertSame(
            'NIK ini sudah terdaftar atas anggota lain.',
            session('errors')->first('nik')
        );
    }

    public function test_nik_milik_anggota_nonaktif_tetap_dianggap_duplikat(): void
    {
        Anggota::factory()->nonaktif()->create(['nik' => '3201234567890001']);

        $this->assertTambahDitolak(['nik' => '3201234567890001'], 'nik');
    }

    public function test_tanggal_bergabung_di_masa_depan_ditolak(): void
    {
        $this->assertTambahDitolak(['tanggal_bergabung' => '2026-11-13'], 'tanggal_bergabung');

        $this->assertSame(
            'Tanggal bergabung tidak boleh melebihi hari ini.',
            session('errors')->first('tanggal_bergabung')
        );
    }

    public function test_tanggal_bergabung_hari_ini_diterima(): void
    {
        $this->actingAs($this->admin)
            ->post(route('anggota.store'), $this->dataValid(['tanggal_bergabung' => '2026-11-12']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Anggota::count());
    }

    public function test_nomor_telepon_tidak_valid_ditolak(): void
    {
        $this->assertTambahDitolak(['no_telepon' => '12345'], 'no_telepon');

        $this->assertSame(
            'Nomor telepon tidak valid. Contoh: 081234567890.',
            session('errors')->first('no_telepon')
        );
    }

    public function test_ubah_anggota_sukses_dan_nik_milik_sendiri_tidak_dianggap_duplikat(): void
    {
        $anggota = Anggota::factory()->create(['nik' => '3201234567890001']);

        $response = $this->actingAs($this->admin)
            ->from(route('anggota.edit', $anggota))
            ->put(route('anggota.update', $anggota), $this->dataValid([
                'nama' => 'Nama Baru',
                'nik' => '3201234567890001',
                'alamat' => 'Jl. Baru No. 2',
                'no_telepon' => '+6281234567890',
                'tanggal_bergabung' => '2025-05-05',
            ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('anggota.show', $anggota));
        $response->assertSessionHas('success', 'Data anggota Nama Baru berhasil diperbarui.');

        $anggota->refresh();
        $this->assertSame('Nama Baru', $anggota->nama);
        $this->assertSame('Jl. Baru No. 2', $anggota->alamat);
        $this->assertSame('+6281234567890', $anggota->no_telepon);
        $this->assertSame('2025-05-05', $anggota->tanggal_bergabung->format('Y-m-d'));
    }

    public function test_ubah_dengan_nik_milik_anggota_lain_ditolak(): void
    {
        Anggota::factory()->create(['nik' => '3201234567890001']);
        $anggota = Anggota::factory()->create(['nik' => '3201234567890002']);

        $this->actingAs($this->admin)
            ->from(route('anggota.edit', $anggota))
            ->put(route('anggota.update', $anggota), $this->dataValid(['nik' => '3201234567890001']))
            ->assertRedirect(route('anggota.edit', $anggota))
            ->assertSessionHasErrors('nik');

        $this->assertSame('3201234567890002', $anggota->refresh()->nik);
    }

    public function test_ubah_tanggal_bergabung_melewati_transaksi_pertama_ditolak(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->buatTransaksi($anggota, '2026-03-01');
        $this->buatTransaksi($anggota, '2026-04-01');

        $response = $this->actingAs($this->admin)
            ->from(route('anggota.edit', $anggota))
            ->put(route('anggota.update', $anggota), $this->dataValid([
                'nik' => $anggota->nik,
                'tanggal_bergabung' => '2026-03-02',
            ]));

        $response->assertRedirect(route('anggota.edit', $anggota));
        $response->assertSessionHasErrors('tanggal_bergabung');
        $this->assertSame(
            'Tanggal bergabung tidak boleh setelah transaksi pertama anggota (01 Maret 2026).',
            session('errors')->first('tanggal_bergabung')
        );
        $this->assertSame('2026-01-01', $anggota->refresh()->tanggal_bergabung->format('Y-m-d'));
    }

    public function test_ubah_tanggal_bergabung_sama_dengan_transaksi_pertama_diterima(): void
    {
        $anggota = Anggota::factory()->create(['tanggal_bergabung' => '2026-01-01']);
        $this->buatTransaksi($anggota, '2026-03-01');

        $this->actingAs($this->admin)
            ->put(route('anggota.update', $anggota), $this->dataValid([
                'nik' => $anggota->nik,
                'tanggal_bergabung' => '2026-03-01',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-03-01', $anggota->refresh()->tanggal_bergabung->format('Y-m-d'));
    }

    public function test_nonaktifkan_mengubah_status_tanpa_menghapus_data(): void
    {
        $anggota = Anggota::factory()->create(['nama' => 'Budi Santoso']);
        $transaksi = $this->buatTransaksi($anggota, '2026-03-01');

        $this->actingAs($this->admin)
            ->patch(route('anggota.nonaktifkan', $anggota))
            ->assertRedirect(route('anggota.index'))
            ->assertSessionHas('success', 'Anggota Budi Santoso berhasil dinonaktifkan.');

        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->refresh()->status);
        $this->assertDatabaseHas('anggota', ['id' => $anggota->id]);
        $this->assertDatabaseHas('transaksi_simpanan', ['id' => $transaksi->id]);
    }

    public function test_nonaktifkan_anggota_yang_sudah_nonaktif_memberi_info(): void
    {
        $anggota = Anggota::factory()->nonaktif()->create(['nama' => 'Budi Santoso']);

        $this->actingAs($this->admin)
            ->patch(route('anggota.nonaktifkan', $anggota))
            ->assertRedirect(route('anggota.index'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('info', 'Anggota Budi Santoso sudah berstatus nonaktif.')
            ->assertSessionMissing('success');

        $this->assertSame(Anggota::STATUS_NONAKTIF, $anggota->refresh()->status);
    }

    public function test_pencarian_berdasarkan_nama(): void
    {
        Anggota::factory()->create(['nama' => 'Budi Santoso']);
        Anggota::factory()->create(['nama' => 'Siti Aminah']);

        $response = $this->actingAs($this->admin)->get(route('anggota.index', ['search' => 'budi']));

        $response->assertOk();
        $response->assertViewHas('search', 'budi');
        $this->assertSame(['Budi Santoso'], $response->viewData('anggota')->pluck('nama')->all());
    }

    public function test_pencarian_berdasarkan_nomor_anggota(): void
    {
        Anggota::factory()->create(['nama' => 'Budi Santoso', 'no_anggota' => 'KSP-2026-0001']);
        Anggota::factory()->create(['nama' => 'Siti Aminah', 'no_anggota' => 'KSP-2026-0002']);

        $response = $this->actingAs($this->admin)->get(route('anggota.index', ['search' => 'KSP-2026-0002']));

        $response->assertOk();
        $this->assertSame(['Siti Aminah'], $response->viewData('anggota')->pluck('nama')->all());
    }

    public function test_daftar_tanpa_pencarian_urut_nama(): void
    {
        Anggota::factory()->create(['nama' => 'Citra']);
        Anggota::factory()->create(['nama' => 'Andi']);
        Anggota::factory()->nonaktif()->create(['nama' => 'Bayu']);

        $response = $this->actingAs($this->admin)->get(route('anggota.index'));

        $response->assertOk();
        $response->assertViewHas('search', null);
        $this->assertSame(['Andi', 'Bayu', 'Citra'], $response->viewData('anggota')->pluck('nama')->all());
    }

    public function test_detail_menampilkan_saldo_per_jenis_yang_benar(): void
    {
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, '2026-03-01', JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000);
        $this->buatTransaksi($anggota, '2026-03-02', JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 500000);
        $this->buatTransaksi($anggota, '2026-03-03', JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 150000);

        // Transaksi anggota lain tidak boleh ikut terhitung.
        $lain = Anggota::factory()->create();
        $this->buatTransaksi($lain, '2026-03-01', JenisSimpanan::WAJIB, TransaksiSimpanan::SETOR, 999000);

        $response = $this->actingAs($this->admin)->get(route('anggota.show', $anggota));

        $response->assertOk();
        $response->assertViewHas('anggota', fn ($a) => $a->is($anggota));

        $this->assertSame(
            [
                ['nama_jenis' => JenisSimpanan::POKOK, 'saldo' => 100000.0],
                ['nama_jenis' => JenisSimpanan::WAJIB, 'saldo' => 0.0],
                ['nama_jenis' => JenisSimpanan::SUKARELA, 'saldo' => 350000.0],
            ],
            $response->viewData('saldoPerJenis')->all()
        );
        $response->assertViewHas('totalSaldo', 450000.0);

        $riwayat = $response->viewData('riwayatTransaksi');
        $this->assertSame(3, $riwayat->total());
        $this->assertSame('2026-03-03', $riwayat->first()->tanggal_transaksi->format('Y-m-d'));
    }

    public function test_detail_riwayat_dipaginasi_sepuluh(): void
    {
        $anggota = Anggota::factory()->create();

        for ($hari = 1; $hari <= 12; $hari++) {
            $this->buatTransaksi($anggota, sprintf('2026-03-%02d', $hari));
        }

        $response = $this->actingAs($this->admin)->get(route('anggota.show', $anggota));

        $response->assertOk();
        $riwayat = $response->viewData('riwayatTransaksi');
        $this->assertCount(10, $riwayat->items());
        $this->assertSame(12, $riwayat->total());
    }

    public function test_parameter_anggota_bukan_angka_menghasilkan_404(): void
    {
        $this->actingAs($this->admin)->get('/anggota/abc')->assertNotFound();
    }

    public function test_anggota_yang_tidak_ada_menghasilkan_404(): void
    {
        $this->actingAs($this->admin)->get('/anggota/9999')->assertNotFound();
    }

    /**
     * @param  array<string, mixed>  $override
     * @return array<string, mixed>
     */
    private function dataValid(array $override = []): array
    {
        return array_merge([
            'nama' => 'Budi Santoso',
            'nik' => '3201234567890001',
            'alamat' => 'Jl. Mawar No. 1, Balikpapan',
            'no_telepon' => '081234567890',
            'tanggal_bergabung' => '2026-11-01',
        ], $override);
    }

    /**
     * @param  array<string, mixed>  $override
     */
    private function assertTambahDitolak(array $override, string $field): void
    {
        $this->actingAs($this->admin)
            ->from(route('anggota.create'))
            ->post(route('anggota.store'), $this->dataValid($override))
            ->assertRedirect(route('anggota.create'))
            ->assertSessionHasErrors($field);

        $this->assertSame(
            0,
            Anggota::where('no_anggota', 'like', 'KSP-2026-%')->count(),
            'Anggota tidak boleh tersimpan jika validasi gagal.'
        );
    }

    private function buatTransaksi(
        Anggota $anggota,
        string $tanggal,
        string $namaJenis = JenisSimpanan::SUKARELA,
        string $jenisTransaksi = TransaksiSimpanan::SETOR,
        int $jumlah = 50000
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
