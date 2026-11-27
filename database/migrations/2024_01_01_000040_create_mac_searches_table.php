<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mac_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained();
            $table->string('mac_normalized', 17);
            $table->string('mac_input', 32);
            $table->unsignedTinyInteger('total_routers')->default(0);
            $table->unsignedTinyInteger('reachable_routers')->default(0);
            $table->unsignedTinyInteger('result_count')->default(0);
            $table->string('status', 20)->default('completed'); // completed|partial|error|no_results
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('ip_address', 45)->nullable();
            $table->string('request_ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('mac_normalized');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mac_searches');
    }
};