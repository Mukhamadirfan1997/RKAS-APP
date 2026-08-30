<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Menyelaraskan struktur master dengan SmartRKAS / ARKAS:
     * 1. Tabel `jenis_belanja` (9 jenis resmi ARKAS) pengganti kategorisasi kasar.
     * 2. `master_kode_rekening.jenis_belanja_id` relasi ke jenis belanja.
     * 3. Hierarki `master_program` (program/sub_program/level) ala SmartRKAS.
     */
    public function up(): void
    {
        // Jenis Belanja (klasifikasi resmi ARKAS, bukan BARJAS/MODAL/HONOR)
        Schema::create('jenis_belanja', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100)->unique();
            $table->timestamps();
        });

        // Tambah relasi jenis belanja ke kode rekening
        Schema::table('master_kode_rekening', function (Blueprint $table) {
            $table->foreignId('jenis_belanja_id')->nullable()->after('kategori_belanja')
                ->constrained('jenis_belanja')->nullOnDelete();
        });

        // Kolom `berlaku_untuk` pada kategori_juknis (kesesuaian SmartRKAS)
        Schema::table('kategori_juknis', function (Blueprint $table) {
            $table->string('berlaku_untuk', 30)->nullable()->after('batas_persen');
        });

        // Tambah hierarki program (ala SmartRKAS: program & sub_program sebagai tekstual,
        // parent_id & level untuk struktur pohon)
        Schema::table('master_program', function (Blueprint $table) {
            $table->string('program', 150)->nullable()->after('kode');
            $table->string('sub_program', 150)->nullable()->after('program');
            $table->unsignedBigInteger('parent_id')->nullable()->after('sub_program');
            $table->unsignedTinyInteger('level')->default(1)->after('parent_id');
            $table->foreign('parent_id')->references('id')->on('master_program')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('kategori_juknis', function (Blueprint $table) {
            $table->dropColumn('berlaku_untuk');
        });

        Schema::table('master_program', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropColumn(['program', 'sub_program', 'parent_id', 'level']);
        });

        Schema::table('master_kode_rekening', function (Blueprint $table) {
            $table->dropForeign(['jenis_belanja_id']);
            $table->dropColumn('jenis_belanja_id');
        });

        Schema::dropIfExists('jenis_belanja');
    }
};
