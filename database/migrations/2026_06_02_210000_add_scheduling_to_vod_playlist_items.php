<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_playlist_items', function (Blueprint $table) {
            $table->timestamp('scheduled_at')->nullable()->after('order');
            $table->float('duration_override')->nullable()->after('scheduled_at');
            $table->integer('loop_count')->default(1)->after('duration_override');
            $table->string('transition')->default('cut')->after('loop_count');
            $table->string('status')->default('active')->after('transition');
        });
    }

    public function down(): void
    {
        Schema::table('vod_playlist_items', function (Blueprint $table) {
            $table->dropColumn(['scheduled_at', 'duration_override', 'loop_count', 'transition', 'status']);
        });
    }
};
