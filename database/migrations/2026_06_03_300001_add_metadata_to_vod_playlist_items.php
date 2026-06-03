<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_playlist_items', function (Blueprint $table) {
            $table->json('metadata_json')->nullable()->after('transition');
        });
    }

    public function down(): void
    {
        Schema::table('vod_playlist_items', function (Blueprint $table) {
            $table->dropColumn('metadata_json');
        });
    }
};
