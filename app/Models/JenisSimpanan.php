<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisSimpanan extends Model
{
    use HasFactory;

    public const POKOK = 'Simpanan Pokok';

    public const WAJIB = 'Simpanan Wajib';

    public const SUKARELA = 'Simpanan Sukarela';

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

    public static function sukarela(): self
    {
        return static::where('nama_jenis', self::SUKARELA)->firstOrFail();
    }

    public function isSukarela(): bool
    {
        return $this->nama_jenis === self::SUKARELA;
    }
}
