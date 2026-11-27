<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Guarda el municipio del router junto al resultado, igual que router_name,
     * para que el historial sea estable aunque el router se renombre o elimine.
     */
    public function up(): void
    {
        Schema::table('mac_search_results', function (Blueprint $table) {
            $table->string('municipality', 100)->nullable()->after('router_name');
        });
    }

    public function down(): void
    {
        Schema::table('mac_search_results', function (Blueprint $table) {
            $table->dropColumn('municipality');
        });
    }
};
