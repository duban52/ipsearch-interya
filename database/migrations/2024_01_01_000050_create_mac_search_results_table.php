<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mac_search_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('mac_search_id')->constrained()->cascadeOnDelete();
            $table->foreignId('router_id')->nullable()->constrained('mikrotik_routers')->nullOnDelete();
            $table->string('router_name', 150);
            $table->string('ip_address', 45);
            $table->string('mac_address', 17);
            $table->string('mechanism', 20); // pppoe|dhcp_active|arp_complete|dhcp_static|other
            $table->string('status_label', 30)->nullable(); // bound|active|complete|static...
            $table->string('hostname', 255)->nullable();
            $table->string('server', 150)->nullable();
            $table->string('interface', 150)->nullable();
            $table->string('comment', 255)->nullable();
            $table->boolean('is_active_match')->default(false);
            $table->unsignedInteger('response_ms')->default(0);
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index('mac_search_id');
            $table->index('ip_address');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mac_search_results');
    }
};