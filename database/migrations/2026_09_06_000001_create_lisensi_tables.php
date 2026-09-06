<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lisensi', function (Blueprint $table) {
            $table->id();
            $table->string('device_code')->unique();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('lisensi_aktivasi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lisensi_id')->constrained('lisensi')->cascadeOnDelete();
            $table->integer('tahun');
            $table->string('kode_aktivasi');
            $table->timestamp('activated_at')->nullable();
            $table->timestamps();
            $table->unique(['lisensi_id', 'tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lisensi_aktivasi');
        Schema::dropIfExists('lisensi');
    }
};
