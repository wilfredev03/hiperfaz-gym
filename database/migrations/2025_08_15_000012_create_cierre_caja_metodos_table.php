<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierre_caja_metodos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cierre_caja_id')->constrained('cierres_caja')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            $table->decimal('monto_esperado', 12, 4)->default(0);
            $table->decimal('monto_contado', 12, 4)->nullable();
            $table->decimal('diferencia', 12, 4)->nullable();
            $table->timestamps();

            $table->unique(['cierre_caja_id', 'metodo_pago_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierre_caja_metodos');
    }
};
