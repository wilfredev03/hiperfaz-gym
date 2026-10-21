<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cierres_caja', function (Blueprint $table) {
            $table->id();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('tasa_cambio_id')->nullable()->constrained('tasas_cambio')->nullOnDelete();
            $table->date('fecha');
            $table->timestamp('hora_apertura')->nullable();
            $table->timestamp('hora_cierre')->nullable();
            $table->decimal('monto_inicial', 12, 4)->default(0);
            $table->decimal('total_ventas', 12, 4)->default(0);
            $table->decimal('monto_esperado', 12, 4)->nullable();
            $table->decimal('monto_contado', 12, 4)->nullable();
            $table->decimal('diferencia', 12, 4)->nullable();
            $table->enum('estado', ['abierto', 'cerrado'])->default('abierto');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cierres_caja');
    }
};
