<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 50);
            $table->string('auditable_type', 100);
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->text('description')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamps();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });

        Schema::create('kategori_juknis', function (Blueprint $table) {
            $table->id();
            $table->string('nama', 100);
            $table->string('arah', 20)->default('maksimal');
            $table->decimal('batas_persen', 5, 2)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('kode_rekening_kategori_juknis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('kategori_juknis_id')->constrained('kategori_juknis')->cascadeOnDelete();
            $table->foreignId('master_kode_rekening_id')->constrained('master_kode_rekening')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['kategori_juknis_id', 'master_kode_rekening_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kode_rekening_kategori_juknis');
        Schema::dropIfExists('kategori_juknis');
        Schema::dropIfExists('audit_logs');
    }
};
