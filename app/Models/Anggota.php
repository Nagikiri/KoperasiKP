<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Anggota extends Model
{
    use HasFactory;

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

    // Helper untuk hitung saldo per jenis simpanan (dipakai di detail anggota & validasi penarikan)
    public function saldoPerJenis($jenisSimpananId): float
    {
        $setor = $this->transaksiSimpanan()
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->where('jenis_transaksi', 'setor')
            ->sum('jumlah');

        $tarik = $this->transaksiSimpanan()
            ->where('jenis_simpanan_id', $jenisSimpananId)
            ->where('jenis_transaksi', 'tarik')
            ->sum('jumlah');

        return (float) ($setor - $tarik);
    }
}
