<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('ingest_protocol')->default('rtmp');
            $table->string('ingest_endpoint')->nullable();
            $table->string('stream_key')->unique();
            $table->json('output_protocols_json')->nullable();
            $table->integer('ingest_port')->nullable();
            $table->boolean('is_live_streaming')->default(false);
            $table->timestamp('last_live_timestamp')->nullable();
            $table->boolean('failover_active')->default(false);
            $table->string('failover_ffmpeg_pid')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('channels');
    }
};
