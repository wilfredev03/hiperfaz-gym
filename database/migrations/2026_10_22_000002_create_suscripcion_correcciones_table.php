<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // bitácora de solo inserción: cada vez que se corrigen las fechas de una
        // suscripción queda quién, cuándo, por qué y cómo estaba antes
        Schema::create('suscripcion_correcciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('suscripcion_id')->constrained('suscripciones');
            $table->foreignId('usuario_id')->constrained('users');
            $table->date('fecha_inicio_anterior');
            $table->date('fecha_vencimiento_anterior');
            $table->date('fecha_inicio_nueva');
            $table->date('fecha_vencimiento_nueva');
            $table->string('motivo');
            $table->timestamp('created_at')->useCurrent();

            $table->index('suscripcion_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('suscripcion_correcciones');
    }
};
