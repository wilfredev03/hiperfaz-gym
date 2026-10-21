<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('metodo_pago_id')->constrained('metodos_pago');
            // cierre en el que ENTRÓ el dinero (distinto al cierre de la venta si es un abono posterior)
            $table->foreignId('cierre_caja_id')->constrained('cierres_caja');
            $table->decimal('monto', 12, 4);
            $table->string('referencia')->nullable();
            $table->dateTime('fecha_pago');
            $table->timestamps();

            $table->index('venta_id');
            $table->index('cierre_caja_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
    }
};
