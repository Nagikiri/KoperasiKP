<?php

namespace Tests\Feature;

use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\TransaksiSimpanan;
use App\Services\NomorAnggotaGenerator;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\DemoSeeder;
use Database\Seeders\JenisSimpananSeeder;
use Database\Seeders\StaffSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * F2b A.4: DemoSeeder (data demo, tidak dipanggil dari DatabaseSeeder).
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const HARI_INI = '2026-11-12';

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(self::HARI_INI.' 10:00:00');

        $this->seed([StaffSeeder::class, JenisSimpananSeeder::class]);

        DemoSeeder::$jumlahAnggota = 6;
        DemoSeeder::$jumlahTahun = 2;
    }

    protected function tearDown(): void
    {
        DemoSeeder::$jumlahAnggota = 24;
        DemoSeeder::$jumlahTahun = 5;
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_seeder_mengisi_anggota_dan_transaksi_yang_konsisten(): void
    {
        $this->seed(DemoSeeder::class);

        // Anggota: jumlah, 3 nonaktif, nomor KSP-{tahun hari ini}-xxxx.
        $anggota = Anggota::orderBy('no_anggota')->get();
        $this->assertCount(6, $anggota);
        $this->assertSame(3, $anggota->where('status', Anggota::STATUS_NONAKTIF)->count());
        foreach ($anggota as $i => $a) {
            $this->assertSame(sprintf('KSP-2026-%04d', $i + 1), $a->no_anggota);
            $this->assertMatchesRegularExpression('/^\d{16}$/', $a->nik);
            $this->assertTrue($a->tanggal_bergabung->lte(Carbon::parse(self::HARI_INI)));
            $this->assertTrue($a->tanggal_bergabung->gte(Carbon::parse(self::HARI_INI)->subYears(2)));
        }
        $this->assertTrue($anggota->contains(fn (Anggota $a) => $a->tanggal_bergabung->isSameMonth(Carbon::parse(self::HARI_INI))), 'Ada anggota yang bergabung bulan ini.');

        $transaksi = TransaksiSimpanan::with('anggota')->orderBy('id')->get();
        $this->assertGreaterThan(0, $transaksi->count());

        // Tanggal transaksi di antara tanggal bergabung dan hari ini.
        foreach ($transaksi as $t) {
            $this->assertTrue($t->tanggal_transaksi->gte($t->anggota->tanggal_bergabung), "Transaksi {$t->id} sebelum tanggal bergabung.");
            $this->assertTrue($t->tanggal_transaksi->lte(Carbon::parse(self::HARI_INI)), "Transaksi {$t->id} di masa depan.");
        }

        // saldo_akhir konsisten dengan urutan kronologis, dan tidak pernah minus per anggota per jenis.
        $saldo = [];
        $tanggalTerakhir = [];
        foreach ($transaksi as $t) {
            $kunci = $t->anggota_id.'-'.$t->jenis_simpanan_id;
            $saldo[$kunci] = ($saldo[$kunci] ?? 0) + ($t->jenis_transaksi === TransaksiSimpanan::SETOR ? 1 : -1) * (float) $t->jumlah;

            $this->assertGreaterThanOrEqual(0, $saldo[$kunci], "Saldo minus pada {$kunci}.");
            $this->assertEquals($saldo[$kunci], (float) $t->saldo_akhir, "saldo_akhir transaksi {$t->id} tidak konsisten.");
            if (isset($tanggalTerakhir[$kunci])) {
                $this->assertTrue($t->tanggal_transaksi->gte($tanggalTerakhir[$kunci]), "Transaksi {$t->id} tidak kronologis.");
            }
            $tanggalTerakhir[$kunci] = $t->tanggal_transaksi;
        }

        // Tarik hanya Sukarela; setiap anggota punya tepat satu Simpanan Pokok.
        $sukarelaId = JenisSimpanan::sukarela()->id;
        $this->assertTrue($transaksi->where('jenis_transaksi', TransaksiSimpanan::TARIK)->every(fn ($t) => $t->jenis_simpanan_id === $sukarelaId));
        $pokokId = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->value('id');
        foreach ($anggota as $a) {
            $this->assertSame(1, $transaksi->where('anggota_id', $a->id)->where('jenis_simpanan_id', $pokokId)->count());
        }

        // Ada anggota aktif yang belum setor wajib bulan ini.
        $wajibId = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->value('id');
        $belumWajib = Anggota::aktif()
            ->whereDoesntHave('transaksiSimpanan', fn ($q) => $q
                ->where('jenis_simpanan_id', $wajibId)
                ->whereDate('tanggal_transaksi', '>=', '2026-11-01'))
            ->count();
        $this->assertGreaterThan(0, $belumWajib);
    }

    public function test_hasil_deterministik_untuk_hari_yang_sama(): void
    {
        $this->seed(DemoSeeder::class);
        $pertama = TransaksiSimpanan::orderBy('id')->get(['tanggal_transaksi', 'jumlah', 'jenis_transaksi'])->toArray();
        $nama = Anggota::orderBy('id')->pluck('nama')->all();

        TransaksiSimpanan::query()->delete();
        Anggota::query()->delete();

        $this->seed(DemoSeeder::class);

        $this->assertSame($pertama, TransaksiSimpanan::orderBy('id')->get(['tanggal_transaksi', 'jumlah', 'jenis_transaksi'])->toArray());
        $this->assertSame($nama, Anggota::orderBy('id')->pluck('nama')->all());
    }

    public function test_menjalankan_kedua_kalinya_ditolak_tanpa_menambah_data(): void
    {
        $this->seed(DemoSeeder::class);
        $jumlahAnggota = Anggota::count();
        $jumlahTransaksi = TransaksiSimpanan::count();

        $this->artisan('db:seed', ['--class' => DemoSeeder::class])
            ->expectsOutputToContain('DemoSeeder hanya untuk database kosong. Jalankan migrate:fresh --seed dulu.');

        $this->assertSame($jumlahAnggota, Anggota::count());
        $this->assertSame($jumlahTransaksi, TransaksiSimpanan::count());
    }

    public function test_ditolak_di_produksi(): void
    {
        $this->app['env'] = 'production';

        (new DemoSeeder)->run(app(NomorAnggotaGenerator::class), app(SimpananService::class));

        $this->assertSame(0, Anggota::count());
        $this->assertSame(0, TransaksiSimpanan::count());
    }

    public function test_tidak_dipanggil_dari_database_seeder(): void
    {
        $this->seed();

        $this->assertSame(0, Anggota::count());
    }
}
