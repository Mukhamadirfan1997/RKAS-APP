<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kode_barang', function (Blueprint $table) {
            $table->string('id_barang_arkas', 50)->nullable()->index();
            $table->string('kode_rekening', 50)->nullable()->index();
            $table->decimal('harga_min', 15, 2)->default(0);
            $table->decimal('harga_max', 15, 2)->default(0);
            $table->string('kode_belanja', 20)->nullable();
            $table->string('kategori', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kode_barang', function (Blueprint $table) {
            $table->dropColumn(['id_barang_arkas', 'kode_rekening', 'harga_min', 'harga_max', 'kode_belanja', 'kategori']);
        });
    }
};
