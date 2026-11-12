<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

    public const STATUS_AKTIF = 'aktif';

    public const STATUS_NONAKTIF = 'nonaktif';

    // WAJIB dideklarasikan agar Eloquent tidak mencari tabel 'anggotas'
    protected $table = 'anggota';

    protected $fillable = [
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
