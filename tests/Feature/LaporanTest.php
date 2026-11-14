<?php

namespace Tests\Feature;

use App\Exports\LaporanSimpananExport;
use App\Exports\Sheets\LembarLaporan;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\LaporanService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Tests\TestCase;

/**
 * Laporan per anggota (FR-16, FR-17; revisi R6–R11).
 */
class LaporanTest extends TestCase
{
    use RefreshDatabase;

    private const NIK = ['6471000000000001', '6471000000000002', '6471000000000003'];

    private User $admin;

    private SimpananService $service;

    private JenisSimpanan $pokok;

    private JenisSimpanan $wajib;

    private JenisSimpanan $sukarela;

    private Anggota $budi;

    private Anggota $siti;

    private Anggota $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 14:30:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);
        $this->service = app(SimpananService::class);

        $this->pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->firstOrFail();
        $this->wajib = JenisSimpanan::where('nama_jenis', JenisSimpanan::WAJIB)->firstOrFail();
        $this->sukarela = JenisSimpanan::sukarela();

        $this->skenario();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * Skenario uji akhir file 04 (semua bergabung 1 Nov 2026, Andi nonaktif).
     */
    private function skenario(): void
    {
        $this->budi = Anggota::factory()->create(['nama' => 'Budi', 'nik' => self::NIK[0], 'tanggal_bergabung' => '2026-11-01']);
        $this->siti = Anggota::factory()->create(['nama' => 'Siti', 'nik' => self::NIK[1], 'tanggal_bergabung' => '2026-11-01']);
        $this->andi = Anggota::factory()->create(['nama' => 'Andi', 'nik' => self::NIK[2], 'tanggal_bergabung' => '2026-11-01']);

        $this->service->setor($this->budi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->wajib->id, 50000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->budi->id, $this->sukarela->id, 200000, '2026-11-02', $this->admin->id, null, 'Setoran awal');
        $this->service->tarik($this->budi->id, 75000, '2026-11-05', $this->admin->id, null, 'Biaya sekolah');

        $this->service->setor($this->siti->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->service->setor($this->siti->id, $this->sukarela->id, 50000, '2026-11-02', $this->admin->id);

        $this->service->setor($this->andi->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    /**
     * @param  array<string, string>  $query
     * @return array<string, mixed>
     */
    private function tampilkan(array $query = []): array
    {
        return $this->actingAs($this->admin)
            ->get(route('laporan.anggota', $query + ['tampilkan' => '1']))
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->viewData('laporan');
    }

    /**
     * @param  array<string, mixed>  $laporan
     * @return array<string, mixed>
     */
    private function item(array $laporan, string $nama): array
    {
        return $laporan['anggota']->firstWhere('nama', $nama);
    }

    /**
     * @param  array<string, mixed>  $item
     * @return array<string, float>
     */
    private function saldo(array $item): array
    {
        return array_column($item['saldo_per_jenis'], 'saldo', 'nama_jenis') + ['total' => $item['total_saldo']];
    }

    // ---------------------------------------------------------------
    // Pratinjau (R6, R7)
    // ---------------------------------------------------------------

    public function test_halaman_tanpa_tampilkan_hanya_menampilkan_form(): void
    {
        $kosong = $this->actingAs($this->admin)->get(route('laporan.anggota'));

        $kosong->assertOk();
        $kosong->assertSee('name="tampilkan" value="1"', false);
        $kosong->assertSee('Semua anggota');
        $kosong->assertDontSee('LAPORAN SIMPANAN PER ANGGOTA');
        $this->assertNull($kosong->viewData('laporan'));
        $this->assertSame(
            ['anggota_id' => null, 'status_anggota' => null, 'tanggal_mulai' => null, 'tanggal_selesai' => null],
            $kosong->viewData('filter'),
        );

        // Parameter tanpa `tampilkan`: tetap hanya form, nilai filter dikembalikan, tanpa validasi.
        $denganParameter = $this->actingAs($this->admin)->get(route('laporan.anggota', [
            'anggota_id' => (string) $this->budi->id,
            'tanggal_mulai' => '2026-11-05',
            'tanggal_selesai' => '2026-11-01',
        ]));

        $denganParameter->assertOk()->assertSessionHasNoErrors();
        $this->assertNull($denganParameter->viewData('laporan'));
        $this->assertSame('2026-11-05', $denganParameter->viewData('filter')['tanggal_mulai']);
    }

    public function test_semua_anggota_saat_anggota_kosong(): void
    {
        $laporan = $this->tampilkan();

        $this->assertSame(
            [$this->budi->no_anggota, $this->siti->no_anggota, $this->andi->no_anggota],
            $laporan['anggota']->pluck('no_anggota')->all(),
        );
        $this->assertSame([4, 2, 1], $laporan['anggota']->map(fn ($a) => $a['transaksi']->count())->all());
        $this->assertSame('Anggota: Semua; Status: Semua', $laporan['filter_label']);
        $this->assertSame('Seluruh periode', $laporan['periode']['label']);
        $this->assertSame('Admin Koperasi', $laporan['dicetak_oleh']);
        $this->assertSame([JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA], $laporan['jenis_simpanan']);
    }

    public function test_satu_anggota_dengan_transaksi_urut_kronologis(): void
    {
        $laporan = $this->tampilkan(['anggota_id' => (string) $this->budi->id]);

        $this->assertCount(1, $laporan['anggota']);
        $budi = $laporan['anggota']->first();

        $this->assertSame('Budi', $budi['nama']);
        $this->assertSame('aktif', $budi['status']);
        $this->assertSame('2026-11-01', $budi['tanggal_bergabung']->format('Y-m-d'));
        $this->assertSame("Anggota: {$this->budi->no_anggota} - Budi", $laporan['filter_label']);

        $ids = $budi['transaksi']->pluck('id')->all();
        $urut = $ids;
        sort($urut);
        $this->assertSame($urut, $ids);
        $this->assertSame(
            ['2026-11-01', '2026-11-01', '2026-11-02', '2026-11-05'],
            $budi['transaksi']->map(fn ($t) => $t->tanggal_transaksi->format('Y-m-d'))->all(),
        );
        $this->assertSame([null, null, 'Setoran awal', 'Biaya sekolah'], $budi['transaksi']->pluck('keterangan')->all());
    }

    public function test_filter_status_hanya_berlaku_saat_semua_anggota(): void
    {
        $nonaktif = $this->tampilkan(['status_anggota' => 'nonaktif']);
        $this->assertSame(['Andi'], $nonaktif['anggota']->pluck('nama')->all());
        $this->assertSame('Anggota: Semua; Status: Nonaktif', $nonaktif['filter_label']);

        $aktif = $this->tampilkan(['status_anggota' => 'aktif']);
        $this->assertSame(['Budi', 'Siti'], $aktif['anggota']->pluck('nama')->all());

        // Saat satu anggota dipilih, status diabaikan.
        $budi = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'status_anggota' => 'nonaktif']);
        $this->assertSame(['Budi'], $budi['anggota']->pluck('nama')->all());
    }

    public function test_filter_tanggal_inklusif(): void
    {
        $laporan = $this->tampilkan([
            'anggota_id' => (string) $this->budi->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-03',
        ]);

        $this->assertCount(3, $this->item($laporan, 'Budi')['transaksi']);
        $this->assertSame('1 November 2026 s.d. 3 November 2026', $laporan['periode']['label']);

        $satuHari = $this->tampilkan(['tanggal_mulai' => '2026-11-05', 'tanggal_selesai' => '2026-11-05']);
        $this->assertSame([1, 0, 0], $satuHari['anggota']->map(fn ($a) => $a['transaksi']->count())->all());
    }

    public function test_saldo_per_akhir_periode_mengabaikan_transaksi_setelah_tanggal_selesai(): void
    {
        $sampai = $this->tampilkan(['tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-03']);

        $this->assertSame([
            JenisSimpanan::POKOK => 100000.0,
            JenisSimpanan::WAJIB => 50000.0,
            JenisSimpanan::SUKARELA => 200000.0,
            'total' => 350000.0,
        ], $this->saldo($this->item($sampai, 'Budi')));

        $penuh = $this->tampilkan();

        $this->assertSame(125000.0, $this->saldo($this->item($penuh, 'Budi'))[JenisSimpanan::SUKARELA]);
        $this->assertSame(275000.0, $this->saldo($this->item($penuh, 'Budi'))['total']);
        $this->assertSame(150000.0, $this->saldo($this->item($penuh, 'Siti'))['total']);
        $this->assertSame(100000.0, $this->saldo($this->item($penuh, 'Andi'))['total']);
    }

    public function test_label_periode_sejak_dan_sampai(): void
    {
        $sejak = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'tanggal_mulai' => '2026-11-02']);
        $this->assertSame('Sejak 2 November 2026', $sejak['periode']['label']);
        $this->assertCount(2, $this->item($sejak, 'Budi')['transaksi']);

        $sampai = $this->tampilkan(['anggota_id' => (string) $this->budi->id, 'tanggal_selesai' => '2026-11-01']);
        $this->assertSame('Sampai 1 November 2026', $sampai['periode']['label']);
        $this->assertCount(2, $this->item($sampai, 'Budi')['transaksi']);
    }

    public function test_tanggal_selesai_sebelum_tanggal_mulai_ditolak(): void
    {
        $this->actingAs($this->admin)
            ->from(route('laporan.anggota'))
            ->get(route('laporan.anggota', [
                'tampilkan' => '1',
                'tanggal_mulai' => '2026-11-05',
                'tanggal_selesai' => '2026-11-01',
            ]))
            ->assertRedirect(route('laporan.anggota'))
            ->assertSessionHasErrors(['tanggal_selesai' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.']);
    }

    public function test_nilai_filter_tidak_valid_ditolak(): void
    {
        foreach (['anggota_id' => '999', 'status_anggota' => 'keluar', 'tanggal_mulai' => '05-11-2026'] as $field => $nilai) {
            $this->actingAs($this->admin)
                ->from(route('laporan.anggota'))
                ->get(route('laporan.anggota', ['tampilkan' => '1', $field => $nilai]))
                ->assertRedirect(route('laporan.anggota'))
                ->assertSessionHasErrors($field);
        }

        $this->actingAs($this->admin)
            ->get(route('laporan.anggota.pdf', ['anggota_id' => '999']))
            ->assertSessionHasErrors('anggota_id');
    }

    public function test_anggota_nonaktif_tetap_bisa_dilaporkan(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => (string) $this->andi->id]));

        $response->assertOk();
        $this->assertSame(['Andi'], $response->viewData('laporan')['anggota']->pluck('nama')->all());
        $this->assertTrue($response->viewData('anggotaList')->contains('id', $this->andi->id));
    }

    // ---------------------------------------------------------------
    // Tampilan (R8)
    // ---------------------------------------------------------------

    public function test_pratinjau_memakai_tata_letak_blok_per_anggota(): void
    {
        $response = $this->actingAs($this->admin)->get(route('laporan.anggota', [
            'tampilkan' => '1',
            'tanggal_mulai' => '2026-11-05',
        ]));

        $response->assertSeeInOrder([
            'LAPORAN SIMPANAN PER ANGGOTA',
            'Periode : Sejak 5 November 2026',
            'Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama',
            // Blok 1: Budi
            "1. {$this->budi->no_anggota} — Budi", 'Bergabung: 1 November 2026', 'Status:', 'Aktif',
            JenisSimpanan::POKOK, JenisSimpanan::WAJIB, JenisSimpanan::SUKARELA, 'Total Saldo',
            'Rp 100.000', 'Rp 50.000', 'Rp 125.000', 'Rp 275.000',
            "RIWAYAT TRANSAKSI — Budi ({$this->budi->no_anggota})",
            'No', 'Tanggal', 'Jenis Simpanan', 'Jenis Transaksi', 'Jumlah', 'Saldo Akhir', 'Dicatat Oleh', 'Catatan',
            'Biaya sekolah',
            'Total setor periode: Rp 0', 'Total tarik periode: Rp 75.000',
            // Blok 2: Siti, tanpa transaksi pada periode
            "2. {$this->siti->no_anggota} — Siti",
            "RIWAYAT TRANSAKSI — Siti ({$this->siti->no_anggota})",
            'Tidak ada transaksi pada periode ini.',
            'Total setor periode: Rp 0',
            // Blok 3: Andi (nonaktif)
            "3. {$this->andi->no_anggota} — Andi", 'Nonaktif',
            'Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi',
        ], false);

        $html = $response->getContent();
        $this->assertSame(3, substr_count($html, '<div class="lap-blok"'));
        $this->assertStringContainsString('<span class="lap-hijau">Aktif</span>', $html);
        $this->assertStringContainsString('<span class="lap-merah">Nonaktif</span>', $html);
        $this->assertStringContainsString('<td class="lap-merah">Tarik</td>', $html);
        // Kisi kolom bersama lama sudah tidak ada.
        $this->assertStringNotContainsString('Nama Anggota', $html);

        $semua = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent();
        $this->assertStringContainsString('<td class="lap-hijau">Setor</td>', $semua);
    }

    public function test_struktur_blok_setiap_riwayat_berada_di_blok_anggotanya(): void
    {
        $laporan = app(LaporanService::class)->perAnggota([]) + ['dicetak_oleh' => 'Admin Koperasi'];

        $dokumen = [
            'pratinjau' => $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent(),
            'pdf' => view('laporan.pdf', $laporan)->render(),
        ];

        $harapan = [];
        foreach ([$this->budi, $this->siti, $this->andi] as $anggota) {
            $harapan[$anggota->no_anggota] = [
                'nama' => $anggota->nama,
                'transaksi' => $anggota->transaksiSimpanan()->orderBy('tanggal_transaksi')->orderBy('id')->pluck('id')->map(fn ($id) => (string) $id)->all(),
            ];
        }

        foreach ($dokumen as $tempat => $html) {
            $dom = new \DOMDocument;
            @$dom->loadHTML('<?xml encoding="UTF-8">'.$html);
            $xpath = new \DOMXPath($dom);

            $blok = $xpath->query('//div[@class="lap-blok"]');
            $this->assertSame(3, $blok->length, "Jumlah blok di {$tempat}.");

            $nomor = 0;
            foreach ($blok as $elemen) {
                $nomor++;
                $noAnggota = $elemen->getAttribute('data-anggota');
                $this->assertArrayHasKey($noAnggota, $harapan);

                $pita = trim($xpath->query('.//td[@data-pita-judul]', $elemen)->item(0)->textContent);
                $this->assertStringStartsWith("{$nomor}. {$noAnggota} — {$harapan[$noAnggota]['nama']}", $pita, "Pita judul di {$tempat}.");

                // Setiap blok punya tabelnya sendiri, masing-masing satu.
                foreach (['lap-pita-judul', 'lap-saldo', 'lap-pita-riwayat', 'lap-riwayat', 'lap-ringkasan-anggota'] as $kelas) {
                    $this->assertSame(1, $xpath->query(".//table[contains(@class, '{$kelas}')]", $elemen)->length, "{$kelas} di blok {$noAnggota} ({$tempat}).");
                }
                $this->assertStringContainsString(
                    "RIWAYAT TRANSAKSI — {$harapan[$noAnggota]['nama']} ({$noAnggota})",
                    $xpath->query(".//table[contains(@class, 'lap-pita-riwayat')]", $elemen)->item(0)->textContent,
                );

                // Semua baris riwayat anggota ini ada di dalam bloknya, dan hanya miliknya.
                $idDiBlok = [];
                foreach ($xpath->query('.//tr[@data-transaksi]', $elemen) as $tr) {
                    $idDiBlok[] = $tr->getAttribute('data-transaksi');
                }
                $this->assertSame($harapan[$noAnggota]['transaksi'], $idDiBlok, "Riwayat blok {$noAnggota} di {$tempat}.");
            }

            // Tidak ada baris riwayat di luar blok.
            $this->assertSame(0, $xpath->query('//tr[@data-transaksi][not(ancestor::div[@class="lap-blok"])]')->length);
        }
    }

    public function test_ringkasan_total_setor_dan_tarik_per_anggota_sesuai_periode(): void
    {
        $periode = $this->tampilkan(['tanggal_mulai' => '2026-11-02']);

        $this->assertSame([200000.0, 75000.0], [$this->item($periode, 'Budi')['total_setor_periode'], $this->item($periode, 'Budi')['total_tarik_periode']]);
        $this->assertSame([50000.0, 0.0], [$this->item($periode, 'Siti')['total_setor_periode'], $this->item($periode, 'Siti')['total_tarik_periode']]);
        $this->assertSame([0.0, 0.0], [$this->item($periode, 'Andi')['total_setor_periode'], $this->item($periode, 'Andi')['total_tarik_periode']]);

        $penuh = $this->tampilkan();
        $this->assertSame([350000.0, 75000.0], [$this->item($penuh, 'Budi')['total_setor_periode'], $this->item($penuh, 'Budi')['total_tarik_periode']]);

        $html = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1', 'tanggal_mulai' => '2026-11-02']))->getContent();
        $this->assertStringContainsString('<td data-total-setor-periode>Total setor periode: Rp 200.000</td>', $html);
        $this->assertStringContainsString('<td data-total-tarik-periode>Total tarik periode: Rp 75.000</td>', $html);
    }

    public function test_pratinjau_menampilkan_tombol_unduh_dengan_filter_sama(): void
    {
        $query = ['anggota_id' => (string) $this->budi->id, 'tanggal_mulai' => '2026-11-01', 'tanggal_selesai' => '2026-11-03'];

        $this->actingAs($this->admin)
            ->get(route('laporan.anggota', $query + ['tampilkan' => '1']))
            ->assertSee(route('laporan.anggota.pdf', $query))
            ->assertSee(route('laporan.anggota.excel', $query));
    }

    public function test_tombol_unduh_tampil_sebagai_tombol_breeze(): void
    {
        $html = $this->actingAs($this->admin)
            ->get(route('laporan.anggota', ['tampilkan' => '1', 'anggota_id' => (string) $this->budi->id]))
            ->getContent();

        // Kelas dasar x-primary-button (PDF) dan x-secondary-button (Excel).
        $this->assertMatchesRegularExpression('/<a href="[^"]+" data-tombol-unduh="pdf"\s+class="inline-flex items-center px-4 py-2 bg-gray-800[^"]*">Unduh PDF<\/a>/', $html);
        $this->assertMatchesRegularExpression('/<a href="[^"]+" data-tombol-unduh="excel"\s+class="inline-flex items-center px-4 py-2 bg-white border border-gray-300[^"]*">Unduh Excel<\/a>/', $html);
    }

    public function test_view_pdf_struktur_sama_dan_kepala_berulang(): void
    {
        $laporan = app(LaporanService::class)->perAnggota(['tanggal_selesai' => '2026-11-03']);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';

        $html = view('laporan.pdf', $laporan)->render();

        // Kepala fixed (diulang tiap halaman), area kop kosong, thead berulang, baris tidak terpotong.
        $this->assertStringContainsString('header { position: fixed;', $html);
        $this->assertStringContainsString('data-area-kop', $html);
        $this->assertStringContainsString('display: table-header-group', $html);
        $this->assertStringContainsString('page-break-inside: avoid', $html);

        $this->assertStringContainsString('LAPORAN SIMPANAN PER ANGGOTA', $html);
        $this->assertStringContainsString('Periode : Sampai 3 November 2026', $html);
        $this->assertStringContainsString('Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama', $html);
        $this->assertStringContainsString('RIWAYAT TRANSAKSI', $html);
        $this->assertStringContainsString('Setoran awal', $html);
        $this->assertStringContainsString('Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi', $html);
    }

    // ---------------------------------------------------------------
    // Ekspor (R8, R9)
    // ---------------------------------------------------------------

    public function test_unduh_pdf_nama_file_sesuai_mode(): void
    {
        $satu = $this->actingAs($this->admin)->get(route('laporan.anggota.pdf', ['anggota_id' => $this->budi->id]));

        $satu->assertOk();
        $satu->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('attachment;', $satu->headers->get('content-disposition'));
        $this->assertStringContainsString("Laporan-Simpanan-{$this->budi->no_anggota}-20261112.pdf", $satu->headers->get('content-disposition'));
        $this->assertStringStartsWith('%PDF', $satu->getContent());

        $semua = $this->actingAs($this->admin)->get(route('laporan.anggota.pdf', ['status_anggota' => 'aktif']));

        $semua->assertOk();
        $this->assertStringContainsString('Laporan-Simpanan-Semua-Anggota-20261112.pdf', $semua->headers->get('content-disposition'));
    }

    /**
     * Baris lembar menurut penanda metadata blok: 'pita-judul', 'pita-riwayat',
     * 'saldo', 'riwayat', 'ringkasan', atau teks baris penuh.
     *
     * @return array<int, array<int, mixed>>
     */
    private function barisLembar(LembarLaporan $lembar, string $jenis): array
    {
        $baris = $lembar->array();
        $hasil = [];

        foreach ($lembar->meta() as $nomor => $meta) {
            $cocok = match ($jenis) {
                'pita-judul' => ($meta['pita'] ?? null) === 'judul',
                'pita-riwayat' => ($meta['pita'] ?? null) === 'riwayat',
                'saldo', 'riwayat', 'ringkasan' => ! empty($meta[$jenis]),
                default => $meta['jenis'] === 'penuh' && $baris[$nomor - 1] === [$jenis],
            };

            if ($cocok) {
                $hasil[] = $baris[$nomor - 1];
            }
        }

        return $hasil;
    }

    public function test_unduh_excel_satu_anggota(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', [
            'anggota_id' => $this->budi->id,
            'tanggal_mulai' => '2026-11-01',
            'tanggal_selesai' => '2026-11-03',
        ]))->assertOk();

        Excel::assertDownloaded("Laporan-Simpanan-{$this->budi->no_anggota}-20261112.xlsx", function (LaporanSimpananExport $export) {
            $lembar = $export->lembar();
            $baris = $lembar->array();
            $tanggal = fn (string $ymd) => ExcelDate::PHPToExcel(Carbon::parse($ymd));
            $riwayat = $this->barisLembar($lembar, 'riwayat');

            return count($export->sheets()) === 1
                && $lembar->title() === 'Laporan per Anggota'
                && $lembar->jumlahKolom() === 8
                && $baris[1] === ['KSP Karya Duta Utama']
                && $baris[2] === ['LAPORAN SIMPANAN PER ANGGOTA']
                && $baris[3] === ['Periode : 1 November 2026 s.d. 3 November 2026']
                && $this->barisLembar($lembar, 'pita-judul') === [["1. {$this->budi->no_anggota} — Budi   |   Bergabung: 1 November 2026   |   Status: Aktif"]]
                && $this->barisLembar($lembar, 'saldo') === [[100000.0, null, 50000.0, null, 200000.0, null, 350000.0, null]]
                && $this->barisLembar($lembar, 'pita-riwayat') === [["RIWAYAT TRANSAKSI — Budi ({$this->budi->no_anggota})"]]
                && count($riwayat) === 3
                && $riwayat[2] === [3, $tanggal('2026-11-02'), JenisSimpanan::SUKARELA, 'Setor', 200000.0, 200000.0, 'Admin Koperasi', 'Setoran awal']
                && $this->barisLembar($lembar, 'ringkasan') === [[350000.0, null, null, null, 0.0, null, null, null]]
                && end($baris) === ['Dicetak pada 12 November 2026 14.30 WITA oleh Admin Koperasi'];
        });
    }

    public function test_unduh_excel_semua_anggota(): void
    {
        Excel::fake();

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', ['tanggal_mulai' => '2026-11-05']))->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Semua-Anggota-20261112.xlsx', function (LaporanSimpananExport $export) {
            $lembar = $export->lembar();

            return count($this->barisLembar($lembar, 'pita-judul')) === 3
                && count($this->barisLembar($lembar, 'pita-riwayat')) === 3
                && count($this->barisLembar($lembar, 'saldo')) === 3
                && count($this->barisLembar($lembar, 'ringkasan')) === 3
                && count($this->barisLembar($lembar, 'riwayat')) === 1
                && count($this->barisLembar($lembar, 'Tidak ada transaksi pada periode ini.')) === 2;
        });
    }

    public function test_unduh_excel_tanpa_data_menampilkan_keterangan(): void
    {
        Excel::fake();
        Anggota::query()->update(['status' => Anggota::STATUS_AKTIF]);

        $this->actingAs($this->admin)->get(route('laporan.anggota.excel', ['status_anggota' => 'nonaktif']))->assertOk();

        Excel::assertDownloaded('Laporan-Simpanan-Semua-Anggota-20261112.xlsx', function (LaporanSimpananExport $export) {
            return count($this->barisLembar($export->lembar(), 'Tidak ada data sesuai filter.')) === 1;
        });
    }

    public function test_nik_tidak_muncul_di_pratinjau_pdf_dan_excel(): void
    {
        $html = $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->getContent();

        $laporan = app(LaporanService::class)->perAnggota([]);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';
        $pdf = view('laporan.pdf', $laporan)->render();
        $excel = json_encode((new LaporanSimpananExport($laporan))->lembar()->array());

        foreach (self::NIK as $nik) {
            $this->assertStringNotContainsString($nik, $html);
            $this->assertStringNotContainsString($nik, $pdf);
            $this->assertStringNotContainsString($nik, $excel);
        }
    }

    public function test_tidak_ada_n_plus_satu(): void
    {
        $this->assertTrue(Model::preventsLazyLoading());

        $hitungQuery = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();

            $this->actingAs($this->admin)->get(route('laporan.anggota', ['tampilkan' => '1']))->assertOk();
            $this->actingAs($this->admin)->get(route('laporan.anggota.pdf'))->assertOk();

            $jumlah = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $jumlah;
        };

        $sebelum = $hitungQuery();

        $staffLain = User::factory()->create();
        foreach (Anggota::factory()->count(5)->create(['tanggal_bergabung' => '2026-11-01']) as $anggota) {
            $this->service->setor($anggota->id, $this->pokok->id, 100000, '2026-11-01', $this->admin->id);
            $this->service->setor($anggota->id, $this->sukarela->id, 20000, '2026-11-02', $staffLain->id, null, 'Catatan');
        }

        $this->assertSame($sebelum, $hitungQuery());
    }

    public function test_tamu_tidak_bisa_membuka_maupun_mengunduh(): void
    {
        $this->get(route('laporan.anggota'))->assertRedirect(route('login'));
        $this->get(route('laporan.anggota.pdf', ['anggota_id' => $this->budi->id]))->assertRedirect(route('login'));
        $this->get(route('laporan.anggota.excel'))->assertRedirect(route('login'));
    }
}
