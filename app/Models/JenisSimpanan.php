<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSimpanan extends Model
{
    use HasFactory;

    // WAJIB dideklarasikan agar Eloquent tidak mencari tabel 'jenis_simpanans'
    protected $table = 'jenis_simpanan';

    protected $fillable = [
        'nama_jenis',
        'keterangan',
    ];

    public function transaksiSimpanan()
    {
        return $this->hasMany(TransaksiSimpanan::class, 'jenis_simpanan_id');
    }
}
