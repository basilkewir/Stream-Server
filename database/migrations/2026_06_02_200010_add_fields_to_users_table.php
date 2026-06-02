<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('channel_user')->after('password');
            $table->foreignId('subscription_plan_id')->nullable()->after('role')->constrained()->nullOnDelete();
            $table->decimal('storage_used_mb', 10, 2)->default(0)->after('subscription_plan_id');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subscription_plan_id']);
            $table->dropColumn(['role', 'subscription_plan_id', 'storage_used_mb']);
        });
    }
};
