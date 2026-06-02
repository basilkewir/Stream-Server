<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->string('playlist_mode')->default('sequential')->after('failover_ffmpeg_pid');
            $table->boolean('playlist_loop')->default(true)->after('playlist_mode');
            $table->string('playlist_fill_action')->default('black')->after('playlist_loop');
        });
    }

    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropColumn(['playlist_mode', 'playlist_loop', 'playlist_fill_action']);
        });
    }
};
