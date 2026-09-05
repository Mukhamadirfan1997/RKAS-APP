<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tahun_anggaran', function (Blueprint $table) {
            // Nullable supaya bisa bedakan "belum diisi" vs "sengaja 0"
            $table->decimal('target_barjas', 15, 2)->nullable()->after('pagu_tahap2');
            $table->decimal('target_modal_mesin', 15, 2)->nullable()->after('target_barjas');
            $table->decimal('target_modal_aset', 15, 2)->nullable()->after('target_modal_mesin');
        });
    }

    public function down(): void
    {
        Schema::table('tahun_anggaran', function (Blueprint $table) {
            $table->dropColumn(['target_barjas', 'target_modal_mesin', 'target_modal_aset']);
        });
    }
};
