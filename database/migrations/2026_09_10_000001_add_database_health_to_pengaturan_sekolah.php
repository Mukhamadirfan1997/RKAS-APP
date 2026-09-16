<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->timestamp('db_last_integrity_check_at')->nullable()->after('provinsi');
            $table->timestamp('db_corrupt_detected_at')->nullable()->after('db_last_integrity_check_at');
            $table->text('db_corrupt_message')->nullable()->after('db_corrupt_detected_at');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->dropColumn(['db_last_integrity_check_at', 'db_corrupt_detected_at', 'db_corrupt_message']);
        });
    }
};
