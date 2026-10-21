<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('suscripciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes');
            $table->foreignId('plan_id')->constrained('planes');
            // la línea de venta que originó la suscripción (una suscripción por línea)
            $table->foreignId('venta_detalle_id')->unique()->constrained('venta_detalle');
            $table->date('fecha_inicio');
            // último día con acceso (inclusive)
            $table->date('fecha_vencimiento');
            $table->string('estado')->default('activa');
            $table->timestamps();

            $table->index(['cliente_id', 'fecha_vencimiento']);
        });

        DB::statement("ALTER TABLE suscripciones ADD CONSTRAINT chk_suscripcion_estado CHECK (estado IN ('activa', 'vencida', 'congelada', 'cancelada'))");
        DB::statement('ALTER TABLE suscripciones ADD CONSTRAINT chk_suscripcion_fechas CHECK (fecha_vencimiento >= fecha_inicio)');
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripciones');
    }
};
