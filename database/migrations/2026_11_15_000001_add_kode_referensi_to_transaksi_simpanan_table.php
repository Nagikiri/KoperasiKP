<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * BR-19: kode referensi unik dari form untuk mencegah transaksi tercatat dobel.
 * Pengecualian kedua dari larangan mengubah struktur tabel inti (00 §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_simpanan', function (Blueprint $table) {
            $table->string('kode_referensi', 36)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_simpanan', function (Blueprint $table) {
            $table->dropUnique(['kode_referensi']);
            $table->dropColumn('kode_referensi');
        });
    }
};
