<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El reporte del cierre debe mostrar también el equivalente en
     * bolívares (venta_pagos.monto siempre está en USD). Se guarda ya
     * convertido con la tasa del cierre para no recalcular un cierre
     * cerrado con una tasa distinta más adelante (principio de
     * inmutabilidad del histórico).
     */
    public function up(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->decimal('total_ventas_bs', 14, 4)->default(0)->after('total_ventas');
        });

        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->decimal('monto_bs', 14, 4)->default(0)->after('monto');
        });
    }

    public function down(): void
    {
        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->dropColumn('monto_bs');
        });

        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn('total_ventas_bs');
        });
    }
};
