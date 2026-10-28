<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Una sola fila por instalación (hay un solo número de WhatsApp por
     * negocio, no uno por sucursal). Guarda tanto lo que configura el dueño
     * como el estado interno del motor de envío (contador, próxima hora).
     */
    public function up(): void
    {
        Schema::create('recordatorio_ajustes', function (Blueprint $table) {
            $table->id();
            // el interruptor Iniciar / Detener del dashboard
            $table->boolean('activo')->default(false);

            $table->string('hora_inicio', 5)->default('08:00');
            $table->string('hora_fin', 5)->default('18:00');

            // espera aleatoria entre un mensaje y el siguiente
            $table->unsignedSmallInteger('espera_min_seg')->default(60);
            $table->unsignedSmallInteger('espera_max_seg')->default(180);

            // cada cuántos mensajes se hace un descanso, y cuánto dura (minutos)
            $table->unsignedSmallInteger('mensajes_descanso_min')->default(8);
            $table->unsignedSmallInteger('mensajes_descanso_max')->default(12);
            $table->unsignedSmallInteger('descanso_min')->default(10);
            $table->unsignedSmallInteger('descanso_max')->default(15);

            // a quién se avisa: con cuántos días de anticipación, y solo planes largos
            $table->unsignedSmallInteger('dias_aviso')->default(3);
            $table->unsignedSmallInteger('duracion_minima_dias')->default(7);

            // estado del motor
            $table->unsignedSmallInteger('enviados_desde_descanso')->default(0);
            $table->unsignedSmallInteger('mensajes_para_descanso')->nullable();
            $table->timestamp('proximo_envio_at')->nullable();
            $table->timestamp('descanso_hasta')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorio_ajustes');
    }
};
