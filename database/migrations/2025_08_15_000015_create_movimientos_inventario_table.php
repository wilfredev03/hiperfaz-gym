<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimientos_inventario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos');
            $table->string('tipo'); // entrada_compra, salida_venta, ajuste_positivo, ajuste_negativo, devolucion
            $table->decimal('cantidad', 12, 3);
            $table->decimal('stock_anterior', 12, 3);
            $table->decimal('stock_nuevo', 12, 3);
            // referencia polimórfica: a qué operación pertenece este movimiento
            $table->string('referencia_tipo')->nullable(); // venta, orden_compra, ajuste
            $table->unsignedBigInteger('referencia_id')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['producto_id', 'created_at']);
            $table->index(['referencia_tipo', 'referencia_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_inventario');
    }
};
