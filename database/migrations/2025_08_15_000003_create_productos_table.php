<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categoria_id')->constrained('categorias');
            $table->foreignId('unidad_medida_id')->constrained('unidades_medida');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->decimal('precio_venta', 12, 4);
            $table->decimal('costo_promedio', 12, 4)->default(0);
            $table->decimal('stock_actual', 12, 3)->default(0);
            $table->boolean('controla_inventario')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index('categoria_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('productos');
    }
};
