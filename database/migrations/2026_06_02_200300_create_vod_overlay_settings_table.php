<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vod_overlay_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('channel_id')->constrained()->cascadeOnDelete();
            $table->string('logo_path')->nullable();
            $table->string('logo_position')->default('top-left');
            $table->integer('logo_width')->default(150);
            $table->string('ticker_text')->nullable();
            $table->integer('ticker_speed')->default(50);
            $table->string('ticker_direction')->default('left');
            $table->string('ticker_background_color')->default('#00000080');
            $table->string('ticker_font_color')->default('#FFFFFF');
            $table->integer('ticker_font_size')->default(24);
            $table->boolean('show_clock')->default(false);
            $table->string('clock_position')->default('top-right');
            $table->boolean('enabled')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vod_overlay_settings');
    }
};
