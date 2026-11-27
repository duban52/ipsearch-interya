<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mikrotik_routers', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->string('host', 255);
            $table->unsignedSmallInteger('api_port')->default(8729);
            $table->string('api_user', 100);
            $table->text('api_password'); // encrypted
            $table->boolean('use_ssl')->default(true);
            $table->boolean('verify_ssl')->default(true);
            $table->string('location', 150)->nullable();
            $table->string('municipality', 100)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedTinyInteger('priority')->default(5); // 1 high .. 10 low
            $table->unsignedSmallInteger('connect_timeout_ms')->default(3000);
            $table->unsignedSmallInteger('read_timeout_ms')->default(8000);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_checked_at')->nullable();
            $table->string('connectivity_status', 20)->default('unknown'); // ok|fail|unknown
            $table->timestamps();

            $table->index('is_active');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mikrotik_routers');
    }
};