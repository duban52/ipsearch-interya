<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('type', 20)->default('string'); // string|bool|int|json
            $table->timestamps();
        });

        Schema::create('mail_settings', function (Blueprint $table) {
            $table->id();
            $table->string('host', 255);
            $table->unsignedSmallInteger('port')->default(587);
            $table->string('username', 150)->nullable();
            $table->text('password')->nullable(); // encrypted
            $table->string('encryption', 20)->default('tls'); // tls|ssl|none
            $table->string('from_address', 150);
            $table->string('from_name', 150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('mail_settings');
    }
};