<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('tour_dashboard_seen')->default(false)->after('password');
            $table->boolean('tour_rkas_seen')->default(false)->after('tour_dashboard_seen');
            $table->boolean('tour_monitoring_seen')->default(false)->after('tour_rkas_seen');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['tour_dashboard_seen', 'tour_rkas_seen', 'tour_monitoring_seen']);
        });
    }
};
