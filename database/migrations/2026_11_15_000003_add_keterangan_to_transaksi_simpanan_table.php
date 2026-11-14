<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan opsional per transaksi (revisi 04 R10).
 * Pengecualian keempat dari larangan mengubah struktur tabel inti (00 §1).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaksi_simpanan', function (Blueprint $table) {
            $table->string('keterangan', 255)->nullable()->after('saldo_akhir');
        });
    }

    public function down(): void
    {
        Schema::table('transaksi_simpanan', function (Blueprint $table) {
            $table->dropColumn('keterangan');
        });
    }
};
