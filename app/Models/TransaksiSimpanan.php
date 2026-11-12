<?php

namespace App\Models;

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
        'anggota_id',
        'jenis_simpanan_id',
        'staff_id',
        'jenis_transaksi',
        'jumlah',
        'tanggal_transaksi',
        'saldo_akhir',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'jumlah' => 'decimal:2',
        'saldo_akhir' => 'decimal:2',
    ];

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
