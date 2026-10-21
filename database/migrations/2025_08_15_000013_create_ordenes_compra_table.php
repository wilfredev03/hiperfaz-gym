<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ordenes_compra', function (Blueprint $table) {
            $table->id();
            $table->string('numero_orden')->unique();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('tasa_cambio_id')->nullable()->constrained('tasas_cambio')->nullOnDelete();
            $table->enum('estado', ['pendiente', 'recibida_parcial', 'recibida_completa', 'cancelada'])->default('pendiente');
            $table->date('fecha');
            $table->decimal('total', 12, 4)->default(0);
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ordenes_compra');
    }
};
