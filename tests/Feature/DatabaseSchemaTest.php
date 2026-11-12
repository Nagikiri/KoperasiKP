<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_membuat_tepat_satu_admin_dengan_password_benar(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(1, User::count());

        $admin = User::where('username', 'admin')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $admin->password));
    }

    public function test_seeder_membuat_tepat_tiga_jenis_simpanan(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(3, JenisSimpanan::count());
        $this->assertEqualsCanonicalizing(
            [JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA],
            JenisSimpanan::pluck('nama_jenis')->all()
        );
    }

    public function test_password_plain_disimpan_dalam_bentuk_hash(): void
    {
        $user = User::create([
            'nama' => 'Staff Uji',
            'username' => 'staffuji',
            'password' => 'rahasia123',
        ]);

        $tersimpan = User::whereKey($user->id)->value('password');

        $this->assertNotSame('rahasia123', $tersimpan);
        $this->assertTrue(Hash::check('rahasia123', $tersimpan));
    }

    public function test_anggota_yang_punya_transaksi_tidak_bisa_dihapus(): void
    {
        $this->seed(JenisSimpananSeeder::class);
        $anggota = Anggota::factory()->create();
        $this->buatTransaksi($anggota, JenisSimpanan::POKOK, TransaksiSimpanan::SETOR, 100000, '2026-01-10');

        $this->expectException(QueryException::class);

        $anggota->delete();
    }

    public function test_saldo_per_jenis_dihitung_dari_setor_dikurangi_tarik(): void
    {
        $this->seed(JenisSimpananSeeder::class);
        $anggota = Anggota::factory()->create();
        $sukarela = JenisSimpanan::sukarela();
        $pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->firstOrFail();

        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 100000, '2026-01-10');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::SETOR, 50000, '2026-01-15');
        $this->buatTransaksi($anggota, JenisSimpanan::SUKARELA, TransaksiSimpanan::TARIK, 30000, '2026-01-20');

        $this->assertSame(120000.0, $anggota->saldoPerJenis($sukarela->id));
        $this->assertSame(150000.0, $anggota->saldoPerJenis($sukarela->id, '2026-01-19'));
        $this->assertSame(0.0, $anggota->saldoPerJenis($pokok->id));
    }

    private function buatTransaksi(Anggota $anggota, string $namaJenis, string $jenisTransaksi, int $jumlah, string $tanggal): TransaksiSimpanan
    {
        $staff = User::first() ?? User::factory()->create();
        $jenis = JenisSimpanan::where('nama_jenis', $namaJenis)->firstOrFail();

        return TransaksiSimpanan::create([
            'anggota_id' => $anggota->id,
            'jenis_simpanan_id' => $jenis->id,
            'staff_id' => $staff->id,
            'jenis_transaksi' => $jenisTransaksi,
            'jumlah' => $jumlah,
            'tanggal_transaksi' => $tanggal,
            'saldo_akhir' => 0,
        ]);
    }
}
