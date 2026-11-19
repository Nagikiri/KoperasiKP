<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransaksiSimpanan extends Model
{
    use HasFactory;

    public const SETOR = 'setor';

    public const TARIK = 'tarik';

    // WAJIB dideklarasikan agar Eloquent tidak mencari tabel 'transaksi_simpanans'
    protected $table = 'transaksi_simpanan';

    protected $fillable = [
        'kode_referensi',
        'anggota_id',
        'jenis_simpanan_id',
        'staff_id',
        'jenis_transaksi',
        'jumlah',
        'tanggal_transaksi',
        'saldo_akhir',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'jumlah' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
    ];

    /** Nilai `rentang` yang diterima Dashboard (F2c D.1). Sengaja tidak diubah di F3b (H.2.1) maupun F4. */
    public const RENTANG = ['hari_ini', '7_hari', '30_hari', 'bulan_ini'];

    /** Nilai `rentang` yang diterima riwayat di detail anggota (F3b E.1, H.2.1). */
    public const RENTANG_RIWAYAT_ANGGOTA = ['bulan_ini', 'bulan_lalu', '3_bulan', 'tahun_ini', 'kustom'];

    /**
     * Nilai `rentang` yang diterima halaman Riwayat transaksi (F4 H.3.1): gabungan RENTANG (supaya tautan
     * "Lihat semua" dari dashboard tetap berlaku) dan RENTANG_RIWAYAT_ANGGOTA.
     */
    public const RENTANG_RIWAYAT = ['hari_ini', '7_hari', '30_hari', 'bulan_ini', 'bulan_lalu', '3_bulan', 'tahun_ini', 'kustom'];

    /**
     * Filter transaksi (F2c D.1; dipakai dashboard, Riwayat, dan detail anggota sejak F3b).
     * Kunci: cari, jenis_transaksi, jenis_simpanan_id, rentang, dan dari/sampai (hanya untuk rentang=kustom).
     * Scope mengenali semua nilai RENTANG_RIWAYAT; tiap controller membatasi sendiri rentang yang
     * diterimanya (dashboard RENTANG, detail anggota RENTANG_RIWAYAT_ANGGOTA, Riwayat RENTANG_RIWAYAT).
     * Nilai kosong atau tidak valid diabaikan diam-diam. Rentang yang berjalan dihitung dari now() dan
     * termasuk hari ini.
     *
     * @param  array<string, mixed>  $filter
     */
    public function scopeFilter(Builder $query, array $filter): Builder
    {
        $teks = fn (string $kunci): string => is_scalar($filter[$kunci] ?? null) ? trim((string) $filter[$kunci]) : '';

        $cari = $teks('cari');
        $jenisTransaksi = $teks('jenis_transaksi');
        $jenisSimpananId = $teks('jenis_simpanan_id');
        $rentang = $teks('rentang');

        $kustom = $rentang === 'kustom' ? self::rentangKustom($filter['dari'] ?? null, $filter['sampai'] ?? null) : null;

        [$mulai, $sampai] = match ($rentang) {
            'hari_ini' => [now()->toDateString(), now()->toDateString()],
            '7_hari' => [now()->startOfDay()->subDays(6)->toDateString(), now()->toDateString()],
            '30_hari' => [now()->startOfDay()->subDays(29)->toDateString(), now()->toDateString()],
            'bulan_ini' => [now()->startOfMonth()->toDateString(), now()->toDateString()],
            'bulan_lalu' => [now()->startOfMonth()->subMonthNoOverflow()->toDateString(), now()->startOfMonth()->subDay()->toDateString()],
            '3_bulan' => [now()->startOfDay()->subMonthsNoOverflow(3)->toDateString(), now()->toDateString()],
            'tahun_ini' => [now()->startOfYear()->toDateString(), now()->toDateString()],
            'kustom' => $kustom !== null ? [$kustom['dari'], $kustom['sampai']] : [null, null],
            default => [null, null],
        };

        return $query
            ->when($cari !== '', fn (Builder $q) => $q->whereHas('anggota', fn (Builder $anggota) => $anggota
                ->where('nama', 'like', '%'.$cari.'%')
                ->orWhere('no_anggota', 'like', '%'.$cari.'%')))
            ->when(in_array($jenisTransaksi, [self::SETOR, self::TARIK], true), fn (Builder $q) => $q->where('jenis_transaksi', $jenisTransaksi))
            ->when(ctype_digit($jenisSimpananId), fn (Builder $q) => $q->where('jenis_simpanan_id', (int) $jenisSimpananId))
            ->when($mulai !== null, fn (Builder $q) => $q
                ->whereDate('tanggal_transaksi', '>=', $mulai)
                ->whereDate('tanggal_transaksi', '<=', $sampai));
    }

    /**
     * Rentang kustom (F3b E.2): `dari` dan `sampai` harus tanggal valid berformat Y-m-d. Jika salah satu
     * tidak valid → null (filter tanggal diabaikan). Jika dari > sampai, keduanya ditukar.
     *
     * @return array{dari: string, sampai: string}|null
     */
    public static function rentangKustom(mixed $dari, mixed $sampai): ?array
    {
        $tanggal = function (mixed $nilai): ?string {
            if (! is_string($nilai) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $nilai) !== 1) {
                return null;
            }

            [$tahun, $bulan, $hari] = array_map('intval', explode('-', $nilai));

            return checkdate($bulan, $hari, $tahun) ? $nilai : null;
        };

        $dari = $tanggal($dari);
        $sampai = $tanggal($sampai);

        if ($dari === null || $sampai === null) {
            return null;
        }

        return $dari <= $sampai
            ? ['dari' => $dari, 'sampai' => $sampai]
            : ['dari' => $sampai, 'sampai' => $dari];
    }

    public function anggota()
    {
        return $this->belongsTo(Anggota::class, 'anggota_id');
    }

    public function jenisSimpanan()
    {
        return $this->belongsTo(JenisSimpanan::class, 'jenis_simpanan_id');
    }

    public function staff()
    {
        return $this->belongsTo(User::class, 'staff_id');
    }
}
