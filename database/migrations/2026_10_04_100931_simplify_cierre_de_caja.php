<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Simplifica el cierre de caja: ya no hay fondo inicial, notas, ni
     * conteo físico (esperado/contado/diferencia). El cierre de hoy se abre
     * solo con la primera venta del día y se cierra sin que nadie digite
     * montos — cierre_caja_metodos pasa a guardar un único total por método.
     */
    public function up(): void
    {
        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->dropColumn(['monto_inicial', 'monto_esperado', 'monto_contado', 'diferencia', 'notas']);
        });

        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->dropColumn(['monto_contado', 'diferencia']);
        });

        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->renameColumn('monto_esperado', 'monto');
        });
    }

    public function down(): void
    {
        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->renameColumn('monto', 'monto_esperado');
        });

        Schema::table('cierre_caja_metodos', function (Blueprint $table) {
            $table->decimal('monto_contado', 12, 4)->nullable();
            $table->decimal('diferencia', 12, 4)->nullable();
        });

        Schema::table('cierres_caja', function (Blueprint $table) {
            $table->decimal('monto_inicial', 12, 4)->default(0);
            $table->decimal('monto_esperado', 12, 4)->nullable();
            $table->decimal('monto_contado', 12, 4)->nullable();
            $table->decimal('diferencia', 12, 4)->nullable();
            $table->text('notas')->nullable();
        });
    }
};
