<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuánto del total vendido del cierre corresponde a membresías (lo demás
     * son productos). Se guarda al cerrar, junto con su equivalente en Bs con
     * la tasa del cierre, para no recalcular un cierre ya cerrado. Es NULL en
     * los cierres anteriores a esta separación: no se inventa un desglose.
     */
    public function up(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->decimal('total_membresias', 14, 4)->nullable()->after('total_ventas_bs');
            $table->decimal('total_membresias_bs', 14, 4)->nullable()->after('total_membresias');
        });
    }

    public function down(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn(['total_membresias', 'total_membresias_bs']);
        });
    }
};
