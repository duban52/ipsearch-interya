<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un resultado = una IP en un router. Los distintos mecanismos que la
     * confirman (DHCP + ARP, por ejemplo) se guardan juntos en vez de duplicar
     * la fila y confundir al usuario.
     */
    public function up(): void
    {
        Schema::table('mac_search_results', function (Blueprint $table) {
            $table->json('mechanisms')->nullable()->after('mechanism');
        });
    }

    public function down(): void
    {
        Schema::table('mac_search_results', function (Blueprint $table) {
            $table->dropColumn('mechanisms');
        });
    }
};
