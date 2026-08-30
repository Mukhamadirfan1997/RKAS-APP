<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Pengaturan Sekolah
        Schema::create('pengaturan_sekolah', function (Blueprint $table) {
            $table->id();
            $table->string('npsn', 20)->nullable();
            $table->string('nama_sekolah', 150)->default('SD NEGERI TOYANING 1');
            $table->string('nama_kepala_sekolah', 150)->nullable();
            $table->string('nip_kepala_sekolah', 50)->nullable();
            $table->string('nama_bendahara', 150)->nullable();
            $table->string('nip_bendahara', 50)->nullable();
            $table->string('alamat', 255)->nullable();
            $table->string('desa_kelurahan', 100)->nullable();
            $table->string('kecamatan', 100)->nullable();
            $table->string('kabupaten_kota', 100)->nullable();
            $table->string('provinsi', 100)->nullable();
            $table->timestamps();
        });

        // 2. Tahun Anggaran & Pagu
        Schema::create('tahun_anggaran', function (Blueprint $table) {
            $table->id();
            $table->year('tahun')->default(2026);
            $table->string('sumber_dana', 50)->default('BOSP REGULER');
            $table->decimal('pagu_total', 15, 2)->default(180320000);
            $table->decimal('pagu_tahap1', 15, 2)->default(90160000);
            $table->decimal('pagu_tahap2', 15, 2)->default(90160000);
            $table->boolean('is_active')->default(true);
            $table->string('status_pengesahan', 50)->default('Draft');
            $table->timestamps();
        });

        // 3. Master Program / Kegiatan ARKAS (8 SNP)
        Schema::create('master_program', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->index();
            $table->string('nama', 255);
            $table->string('standar_snp', 150)->nullable();
            $table->timestamps();
        });

        // 4. Master Kode Rekening Belanja
        Schema::create('master_kode_rekening', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->index();
            $table->string('nama', 255);
            $table->string('kategori_belanja', 50)->default('BARJAS');
            $table->timestamps();
        });

        // 5. Katalog Kode Barang
        Schema::create('kode_barang', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 100)->nullable()->index();
            $table->string('nama', 255)->index();
            $table->string('satuan_default', 50)->nullable();
            $table->decimal('harga_acuan', 15, 2)->default(0);
            $table->timestamps();
        });

        // 6. RKAS Item (Item Induk Belanja)
        Schema::create('rkas_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tahun_anggaran_id')->constrained('tahun_anggaran')->cascadeOnDelete();
            $table->foreignId('master_program_id')->nullable()->constrained('master_program')->nullOnDelete();
            $table->foreignId('master_kode_rekening_id')->nullable()->constrained('master_kode_rekening')->nullOnDelete();
            $table->foreignId('kode_barang_id')->nullable()->constrained('kode_barang')->nullOnDelete();

            $table->string('uraian', 500);
            $table->string('keterangan_kustom', 255)->nullable();
            $table->decimal('volume', 12, 2)->default(0);
            $table->string('satuan', 50)->default('bulan');
            $table->decimal('harga_satuan', 15, 2)->default(0);
            $table->decimal('harga_satuan_arkas', 15, 2)->default(0);
            $table->decimal('jumlah', 15, 2)->default(0);

            $table->integer('no_urut')->default(1);
            $table->timestamps();
        });

        // 7. RKAS Item Bulan (Alokasi 12 Bulan)
        Schema::create('rkas_item_bulan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rkas_item_id')->constrained('rkas_item')->cascadeOnDelete();
            $table->unsignedTinyInteger('bulan');
            $table->decimal('volume', 12, 2)->default(0);
            $table->string('satuan', 50)->nullable();
            $table->decimal('jumlah', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['rkas_item_id', 'bulan']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rkas_item_bulan');
        Schema::dropIfExists('rkas_item');
        Schema::dropIfExists('kode_barang');
        Schema::dropIfExists('master_kode_rekening');
        Schema::dropIfExists('master_program');
        Schema::dropIfExists('tahun_anggaran');
        Schema::dropIfExists('pengaturan_sekolah');
    }
};
