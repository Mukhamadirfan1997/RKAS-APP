<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_log_archives', function (Blueprint $table) {
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
            $table->timestamp('archived_at')->nullable();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index('created_at');
        });

        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->timestamp('audit_last_archive_at')->nullable()->after('db_corrupt_message');
        });
    }

    public function down(): void
    {
        Schema::table('pengaturan_sekolah', function (Blueprint $table) {
            $table->dropColumn('audit_last_archive_at');
        });
        Schema::dropIfExists('audit_log_archives');
    }
};
