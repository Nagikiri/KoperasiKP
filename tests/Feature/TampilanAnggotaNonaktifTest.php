<?php

namespace Tests\Feature;

use App\Exports\LaporanSimpananExport;
use App\Models\Anggota;
use App\Models\JenisSimpanan;
use App\Models\User;
use App\Services\LaporanService;
use App\Services\SimpananService;
use Carbon\Carbon;
use Database\Seeders\JenisSimpananSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * BR-21 (revisi R1): anggota nonaktif tetap tampil dengan nama lengkap dan
 * penanda "(Nonaktif)" / status Nonaktif merah di semua tampilan, kecuali
 * dropdown form Setor dan Tarik (BR-05).
 */
class TampilanAnggotaNonaktifTest extends TestCase
{
    use RefreshDatabase;

    // F1 §3.12: jangkar atribut data-status-anggota (pola regex), bukan markup/gaya utuh.
    private const PENANDA = '<span[^>]*data-status-anggota="nonaktif"[^>]*>\(Nonaktif\)<\/span>';

    // F3b B.4 / H.2.6: badge status kini pil bergaris tepi dengan titik red-600 di depan teks.
    private const STATUS_MERAH = '<span[^>]*data-status-anggota="nonaktif"[^>]*><span[^>]*bg-red-600[^>]*><\/span>Nonaktif<\/span>';

    private User $admin;

    private Anggota $andi;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-12 10:00:00');

        $this->seed(JenisSimpananSeeder::class);
        $this->admin = User::factory()->create(['nama' => 'Admin Koperasi']);

        $this->andi = Anggota::factory()->create(['nama' => 'Andi Pratama Wijaya', 'tanggal_bergabung' => '2026-11-01']);
        $pokok = JenisSimpanan::where('nama_jenis', JenisSimpanan::POKOK)->value('id');
        app(SimpananService::class)->setor($this->andi->id, $pokok, 100000, '2026-11-01', $this->admin->id);
        $this->andi->update(['status' => Anggota::STATUS_NONAKTIF]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function html(string $url): string
    {
        return $this->actingAs($this->admin)->get($url)->assertOk()->getContent();
    }

    public function test_daftar_dan_detail_anggota_menampilkan_status_nonaktif_merah(): void
    {
        foreach ([route('anggota.index'), route('anggota.show', $this->andi)] as $url) {
            $html = $this->html($url);

            $this->assertStringContainsString('Andi Pratama Wijaya', $html);
            $this->assertMatchesRegularExpression('/'.self::STATUS_MERAH.'/', $html);
        }
    }

    public function test_riwayat_tabel_dan_dropdown_filter_menandai_nonaktif(): void
    {
        $html = $this->html(route('transaksi.riwayat'));

        // Revisi 2 pasca-F4b: di tabel Riwayat penanda nonaktif berada di baris nomor anggota ("KSP-… · ● Nonaktif",
        // titik red-600, teks stone-600, tanpa pil); tidak ada badge x-status-anggota maupun x-nama-anggota di tabel.
        $this->assertSame(1, preg_match('/<tbody>.*?<\/tbody>/s', $html, $tbody));
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($this->andi->no_anggota, '/').'<\/span>\s*<span aria-hidden="true">·<\/span>\s*<span[^>]*text-stone-600[^>]*data-penanda-nonaktif><span[^>]*bg-red-600[^>]*><\/span>Nonaktif<\/span>/',
            $tbody[0]
        );
        $this->assertDoesNotMatchRegularExpression('/'.self::STATUS_MERAH.'/', $tbody[0]);
        $this->assertDoesNotMatchRegularExpression('/'.self::PENANDA.'/', $tbody[0]);
        // Kolom cari gabungan (revisi 2 pasca-F4b): opsi teks polos "{no} - {nama} (Nonaktif)".
        $this->assertMatchesRegularExpression(
            '/<li[^>]*data-opsi-anggota="'.$this->andi->id.'"[^>]*>\s*<span[^>]*data-label-opsi>'.preg_quote("{$this->andi->no_anggota} - Andi Pratama Wijaya (Nonaktif)", '/').'<\/span>/',
            $html
        );
    }

    public function test_dashboard_transaksi_terbaru_menandai_nonaktif(): void
    {
        // R1 E.1: sel anggota seperti Riwayat; nama lengkap tetap tampil, penanda di baris nomor ("KSP-… · ● Nonaktif").
        $html = $this->html(route('dashboard'));
        $this->assertSame(1, preg_match('/id="transaksi-terbaru".*?id="belum-setor-wajib"/s', $html, $kartu));
        $this->assertMatchesRegularExpression('/data-tautan-detail[^>]*>Andi Pratama Wijaya<\/a>/', $kartu[0]);
        $this->assertMatchesRegularExpression(
            '/'.preg_quote($this->andi->no_anggota, '/').'<\/span>\s*<span aria-hidden="true">·<\/span>\s*<span[^>]*text-stone-600[^>]*data-penanda-nonaktif><span[^>]*bg-red-600[^>]*><\/span>Nonaktif<\/span>/',
            $kartu[0]
        );
        $this->assertDoesNotMatchRegularExpression('/'.self::PENANDA.'/', $kartu[0]);
    }

    public function test_laporan_keseluruhan_pratinjau_dan_pdf_menandai_nonaktif(): void
    {
        $pratinjau = $this->html(route('laporan.keseluruhan'));

        $laporan = app(LaporanService::class)->keseluruhan([]);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';
        $pdf = view('laporan.pdf-keseluruhan', $laporan)->render();

        foreach ([$pratinjau, $pdf] as $html) {
            // Tabel saldo anggota: kolom status merah (kelas laporan seragam). Tabel transaksi: nama + penanda.
            $this->assertMatchesRegularExpression('/<td>Andi Pratama Wijaya<\/td>\s*<td class="lap-tengah lap-merah">Nonaktif<\/td>/', $html);
            $this->assertMatchesRegularExpression('/Andi Pratama Wijaya '.self::PENANDA.'/', $html);
        }
    }

    public function test_laporan_per_anggota_dropdown_pratinjau_dan_pdf_menandai_nonaktif(): void
    {
        $form = $this->html(route('laporan.anggota'));
        $this->assertStringContainsString("{$this->andi->no_anggota} - Andi Pratama Wijaya (Nonaktif)", $form);

        $pratinjau = $this->html(route('laporan.anggota', ['tampilkan' => '1']));

        $laporan = app(LaporanService::class)->perAnggota([]);
        $laporan['dicetak_oleh'] = 'Admin Koperasi';
        $pdf = view('laporan.pdf', $laporan)->render();

        foreach ([$pratinjau, $pdf] as $html) {
            // Pita judul blok: nama lengkap + status Nonaktif merah (revisi R6).
            $this->assertMatchesRegularExpression('/<td data-pita-judul>\d+\. '.preg_quote($this->andi->no_anggota, '/').' — Andi Pratama Wijaya.*Status: <span class="lap-merah">Nonaktif<\/span><\/td>/', $html);
        }
    }

    /**
     * F1 §7.1c: PDF dirender dompdf tanpa Tailwind, jadi penanda nonaktif wajib tetap merah lewat style inline.
     */
    public function test_penanda_nonaktif_di_pdf_tetap_inline_merah(): void
    {
        // PDF laporan keseluruhan: penanda dari x-nama-anggota di tabel transaksi.
        $keseluruhan = app(LaporanService::class)->keseluruhan([]) + ['dicetak_oleh' => 'Admin Koperasi'];
        $dom = new \DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.view('laporan.pdf-keseluruhan', $keseluruhan)->render());
        libxml_clear_errors();
        $xpath = new \DOMXPath($dom);

        $penanda = $xpath->query('//*[@data-status-anggota="nonaktif"]');
        $this->assertGreaterThan(0, $penanda->length);

        $cocok = 0;
        foreach ($penanda as $elemen) {
            $style = $elemen->getAttribute('style');
            $this->assertStringContainsString('color: #b91c1c', $style, 'Setiap penanda nonaktif di PDF wajib merah inline.');
            $this->assertStringContainsString('font-weight: bold', $style);

            $baris = $xpath->query('ancestor::tr[1]', $elemen)->item(0);
            if (str_contains($elemen->textContent, '(Nonaktif)')
                && $baris !== null
                && str_contains($baris->textContent, 'Andi Pratama Wijaya')) {
                $cocok++;
            }
        }
        $this->assertGreaterThan(0, $cocok, 'Penanda "(Nonaktif)" harus berada di baris yang sama dengan nama Andi.');

        // PDF laporan per anggota: partial tidak memakai komponen; penanda lama tetap utuh.
        $perAnggota = app(LaporanService::class)->perAnggota([]) + ['dicetak_oleh' => 'Admin Koperasi'];
        $this->assertMatchesRegularExpression(
            '/<td data-pita-judul>[^<]*Andi Pratama Wijaya.*Status: <span class="lap-merah">Nonaktif<\/span><\/td>/',
            view('laporan.pdf', $perAnggota)->render(),
        );
        $this->assertStringContainsString('.lap-merah { color: #b91c1c; font-weight: bold; }', view('laporan.partials.gaya')->render());
    }

    // ---------------------------------------------------------------
    // Revisi R2 — kepala, gaya, dan kaki laporan seragam
    // ---------------------------------------------------------------

    public function test_kepala_gaya_dan_kaki_seragam_di_kedua_pdf_dan_pratinjau(): void
    {
        $perAnggota = app(LaporanService::class)->perAnggota([]) + ['dicetak_oleh' => 'Admin Koperasi'];
        $keseluruhan = app(LaporanService::class)->keseluruhan([]) + ['dicetak_oleh' => 'Admin Koperasi'];

        $dokumen = [
            'LAPORAN SIMPANAN PER ANGGOTA' => [
                view('laporan.pdf', $perAnggota)->render(),
                $this->html(route('laporan.anggota', ['tampilkan' => '1'])),
            ],
            'LAPORAN SIMPANAN KESELURUHAN' => [
                view('laporan.pdf-keseluruhan', $keseluruhan)->render(),
                $this->html(route('laporan.keseluruhan')),
            ],
        ];

        $gaya = view('laporan.partials.gaya')->render();

        foreach ($dokumen as $judul => $daftar) {
            foreach ($daftar as $html) {
                $this->assertMatchesRegularExpression(
                    '/<div class="lap-kop" data-area-kop>\s*<span class="lap-kop-nama">KSP Karya Duta Utama<\/span>\s*<\/div>\s*'
                    .'<p class="lap-judul">'.$judul.'<\/p>\s*'
                    .'<p class="lap-periode">Periode : Seluruh periode<\/p>\s*'
                    .'<p class="lap-periode">Filter : [^<]+<\/p>\s*'
                    .'<p class="lap-keterangan">Laporan ini diekspor dari Sistem Informasi KSP Karya Duta Utama<\/p>/',
                    $html,
                );
                $this->assertStringContainsString($gaya, $html);
                $this->assertStringContainsString('<p class="lap-kaki">Dicetak pada 12 November 2026 10.00 WITA oleh Admin Koperasi</p>', $html);
                $this->assertStringContainsString('class="lap-tabel', $html);
            }
        }

        // Kedua PDF memakai kerangka yang sama: kepala dan kaki fixed.
        foreach (['laporan.pdf' => $perAnggota, 'laporan.pdf-keseluruhan' => $keseluruhan] as $view => $data) {
            $pdf = view($view, $data)->render();
            $this->assertStringContainsString('header { position: fixed;', $pdf);
            $this->assertStringContainsString('footer { position: fixed;', $pdf);
        }
    }

    // ---------------------------------------------------------------
    // Revisi R5 — keterangan saldo anggota nonaktif
    // ---------------------------------------------------------------

    /**
     * @param  array<string, string>  $filter
     * @return array<string, string> [nama tempat => isi]
     */
    private function semuaTampilanLaporan(array $filter = []): array
    {
        $laporan = app(LaporanService::class)->perAnggota($filter) + ['dicetak_oleh' => 'Admin Koperasi'];

        return [
            'pratinjau' => $this->html(route('laporan.anggota', $filter + ['tampilkan' => '1'])),
            'pdf' => view('laporan.pdf', $laporan)->render(),
            'excel' => json_encode((new LaporanSimpananExport($laporan))->lembar()->array(), JSON_UNESCAPED_UNICODE),
        ];
    }

    public function test_keterangan_tampil_untuk_nonaktif_dengan_saldo_lebih_dari_nol(): void
    {
        // Pembanding: anggota aktif bersaldo dan anggota nonaktif bersaldo 0 tidak mendapat keterangan.
        $budi = Anggota::factory()->create(['nama' => 'Budi', 'tanggal_bergabung' => '2026-11-01']);
        app(SimpananService::class)->setor($budi->id, JenisSimpanan::sukarela()->id, 50000, '2026-11-02', $this->admin->id);
        Anggota::factory()->nonaktif()->create(['nama' => 'Dedi Tanpa Saldo']);

        foreach ($this->semuaTampilanLaporan() as $tempat => $isi) {
            $this->assertSame(1, substr_count($isi, Anggota::KETERANGAN_SALDO_NONAKTIF), "Keterangan di {$tempat} harus tepat satu (hanya Andi).");
        }

        // Di pratinjau/PDF, keterangan berada tepat di bawah baris Andi dan di-merge selebar tabel.
        $this->assertMatchesRegularExpression(
            '/<td data-pita-judul>[^<]*Andi Pratama Wijaya.*?<\/td>\s*<\/tr>\s*<tr class="lap-catatan-nonaktif">\s*<td data-keterangan-saldo-nonaktif>'.preg_quote(Anggota::KETERANGAN_SALDO_NONAKTIF, '/').'/s',
            $this->html(route('laporan.anggota', ['tampilkan' => '1'])),
        );

        $detail = $this->html(route('anggota.show', $this->andi));
        // F3 H.1.2: di detail anggota keterangan menjadi banner [data-keterangan-saldo-nonaktif], teks tepat satu kali.
        $this->assertMatchesRegularExpression(
            '/<div data-keterangan-saldo-nonaktif\b[^>]*>.*?'.preg_quote(Anggota::KETERANGAN_SALDO_NONAKTIF, '/').'.*?<\/div>/s',
            $detail,
        );
        $this->assertSame(1, substr_count($detail, Anggota::KETERANGAN_SALDO_NONAKTIF));

        $this->assertStringNotContainsString(Anggota::KETERANGAN_SALDO_NONAKTIF, $this->html(route('anggota.show', $budi)));
    }

    public function test_keterangan_tidak_tampil_jika_saldo_nonaktif_nol(): void
    {
        $dedi = Anggota::factory()->nonaktif()->create(['nama' => 'Dedi Tanpa Saldo']);

        $this->assertStringNotContainsString(Anggota::KETERANGAN_SALDO_NONAKTIF, $this->html(route('anggota.show', $dedi)));

        foreach ($this->semuaTampilanLaporan(['anggota_id' => (string) $dedi->id]) as $tempat => $isi) {
            $this->assertStringContainsString('Dedi Tanpa Saldo', $isi);
            $this->assertStringNotContainsString(Anggota::KETERANGAN_SALDO_NONAKTIF, $isi, "Keterangan tidak boleh tampil di {$tempat}.");
        }

        // Saldo dihitung per akhir periode: sebelum setoran Andi, saldonya 0 → tanpa keterangan.
        foreach ($this->semuaTampilanLaporan(['tanggal_selesai' => '2026-11-01']) as $isi) {
            $this->assertSame(1, substr_count($isi, Anggota::KETERANGAN_SALDO_NONAKTIF));
        }
        foreach ($this->semuaTampilanLaporan(['tanggal_mulai' => '2026-10-01', 'tanggal_selesai' => '2026-10-31']) as $tempat => $isi) {
            $this->assertStringNotContainsString(Anggota::KETERANGAN_SALDO_NONAKTIF, $isi, "Saldo per 31 Oktober 0, keterangan tidak boleh tampil di {$tempat}.");
        }
    }

    public function test_dropdown_setor_dan_tarik_tetap_tanpa_anggota_nonaktif(): void
    {
        foreach (['transaksi.setor.create', 'transaksi.tarik.create'] as $route) {
            $this->assertStringNotContainsString('Andi Pratama Wijaya', $this->html(route($route)));
        }
    }
}
