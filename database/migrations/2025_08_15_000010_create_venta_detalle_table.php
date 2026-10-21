<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venta_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 4);
            $table->decimal('costo_unitario_momento', 12, 4)->nullable();
            $table->decimal('subtotal', 12, 4);
            $table->timestamps();

            $table->index('venta_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_detalle');
    }
};
