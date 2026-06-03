<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vod_overlay_settings', function (Blueprint $table) {
            $table->string('lower_third_title')->nullable()->after('ticker_font_size');
            $table->string('lower_third_subtitle')->nullable()->after('lower_third_title');
            $table->string('lower_third_position')->default('bottom-left')->after('lower_third_subtitle');
            $table->string('lower_third_bg_color')->default('#1a1a1aCC')->after('lower_third_position');
            $table->string('lower_third_text_color')->default('#FFFFFF')->after('lower_third_bg_color');
            $table->integer('lower_third_font_size')->default(32)->after('lower_third_text_color');
            $table->integer('lower_third_duration')->default(5)->after('lower_third_font_size')->comment('seconds to show');
            $table->boolean('show_lower_third')->default(false)->after('lower_third_duration');
            $table->string('crawl_text')->nullable()->after('show_lower_third');
            $table->integer('crawl_speed')->default(80)->after('crawl_text');
            $table->string('crawl_bg_color')->default('#000000CC')->after('crawl_speed');
            $table->string('crawl_text_color')->default('#FFFF00')->after('crawl_bg_color');
            $table->integer('crawl_font_size')->default(28)->after('crawl_text_color');
            $table->boolean('show_crawl')->default(false)->after('crawl_font_size');
        });
    }

    public function down(): void
    {
        Schema::table('vod_overlay_settings', function (Blueprint $table) {
            $table->dropColumn([
                'lower_third_title', 'lower_third_subtitle', 'lower_third_position',
                'lower_third_bg_color', 'lower_third_text_color', 'lower_third_font_size',
                'lower_third_duration', 'show_lower_third',
                'crawl_text', 'crawl_speed', 'crawl_bg_color', 'crawl_text_color',
                'crawl_font_size', 'show_crawl',
            ]);
        });
    }
};
