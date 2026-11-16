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

    /** Nilai `rentang` yang dikenali scopeFilter (F2c D.1). */
    public const RENTANG = ['hari_ini', '7_hari', '30_hari', 'bulan_ini'];

    /**
     * Filter transaksi (F2c D.1; dipakai dashboard, nanti juga Riwayat di F4).
     * Kunci: cari, jenis_transaksi, jenis_simpanan_id, rentang. Nilai kosong atau
     * tidak valid diabaikan diam-diam. Rentang dihitung dari now() dan termasuk hari ini.
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

        $mulai = match ($rentang) {
            'hari_ini' => now()->startOfDay(),
            '7_hari' => now()->startOfDay()->subDays(6),
            '30_hari' => now()->startOfDay()->subDays(29),
            'bulan_ini' => now()->startOfMonth(),
            default => null,
        };

        return $query
            ->when($cari !== '', fn (Builder $q) => $q->whereHas('anggota', fn (Builder $anggota) => $anggota
                ->where('nama', 'like', '%'.$cari.'%')
                ->orWhere('no_anggota', 'like', '%'.$cari.'%')))
            ->when(in_array($jenisTransaksi, [self::SETOR, self::TARIK], true), fn (Builder $q) => $q->where('jenis_transaksi', $jenisTransaksi))
            ->when(ctype_digit($jenisSimpananId), fn (Builder $q) => $q->where('jenis_simpanan_id', (int) $jenisSimpananId))
            ->when($mulai !== null, fn (Builder $q) => $q
                ->whereDate('tanggal_transaksi', '>=', $mulai->toDateString())
                ->whereDate('tanggal_transaksi', '<=', now()->toDateString()));
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
