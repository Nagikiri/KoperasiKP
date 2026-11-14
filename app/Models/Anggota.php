<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    /**
     * Keterangan untuk anggota nonaktif yang saldonya masih > 0 (revisi R5).
     */
    public const KETERANGAN_SALDO_NONAKTIF = 'Anggota nonaktif. Saldo simpanan masih tercatat dan belum dikembalikan.';

    public static function perluKeteranganSaldo(string $status, float $totalSaldo): bool
    {
        return $status !== self::STATUS_AKTIF && $totalSaldo > 0;
    }

    // WAJIB dideklarasikan agar Eloquent tidak mencari tabel 'anggotas'
    protected $table = 'anggota';

    protected $fillable = [
        'kode_referensi',
        'no_anggota',
        'nama',
        'nik',
        'alamat',
        'no_telepon',
        'tanggal_bergabung',
        'status',
    ];

    protected $casts = [
        'tanggal_bergabung' => 'date',
    ];

    public function transaksiSimpanan()
    {
        return $this->hasMany(TransaksiSimpanan::class, 'anggota_id');
    }

    public function scopeAktif($query)
    {
        return $query->where('status', self::STATUS_AKTIF);
    }

    public function isAktif(): bool
    {
        return $this->status === self::STATUS_AKTIF;
    }

    public function saldoPerJenis(int $jenisSimpananId, ?string $sampaiTanggal = null): float
    {
        $query = $this->transaksiSimpanan()->where('jenis_simpanan_id', $jenisSimpananId);

        if ($sampaiTanggal) {
            $query->whereDate('tanggal_transaksi', '<=', $sampaiTanggal);
        }

        $hasil = $query->selectRaw(
            "COALESCE(SUM(CASE WHEN jenis_transaksi = 'setor' THEN jumlah ELSE 0 END), 0)
           - COALESCE(SUM(CASE WHEN jenis_transaksi = 'tarik' THEN jumlah ELSE 0 END), 0) AS saldo"
        )->value('saldo');

        return round((float) $hasil, 2);
    }
}
