<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_playlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('type')->default('upload');
            $table->string('title')->nullable();
            $table->string('file_path_or_url', 2048);
            $table->float('duration_sec')->nullable();
            $table->bigInteger('file_size_bytes')->nullable();
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_playlist_items');
    }
};
