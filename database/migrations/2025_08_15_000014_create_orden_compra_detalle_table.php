<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orden_compra_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('productos');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('cantidad_recibida', 12, 3)->default(0);
            $table->decimal('costo_unitario', 12, 4);
            $table->decimal('subtotal', 12, 4);
            $table->timestamps();

            $table->index('orden_compra_id');
            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orden_compra_detalle');
    }
};
