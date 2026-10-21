<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            // cómo se le llama a una orden/pedido en el punto de venta de
            // esta sucursal (ej. "Orden", "Mesa", "Pedido"); configurable
            // porque el mismo sistema genérico sirve a distintos rubros
            $table->string('termino_orden')->default('Orden');
            $table->string('direccion')->nullable();
            $table->string('telefono')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sucursales');
    }
};
