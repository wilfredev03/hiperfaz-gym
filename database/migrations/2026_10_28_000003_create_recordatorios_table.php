<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La cola y, a la vez, el registro de a quién se le avisó. El `mensaje`
     * queda ya armado (con el nombre, plan y días de ese momento) y no se
     * edita: es lo que realmente se le mandó al cliente (principio #1). Solo
     * cambian `estado`, los intentos y el resultado del envío.
     */
    public function up(): void
    {
        Schema::create('recordatorios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cliente_id')->constrained('clientes')->restrictOnDelete();
            $table->foreignId('suscripcion_id')->constrained('suscripciones')->restrictOnDelete();
            $table->foreignId('plantilla_id')->nullable()->constrained('recordatorio_plantillas')->nullOnDelete();
            $table->string('tipo');
            $table->string('telefono');
            $table->text('mensaje');
            // variables con las que se armó el mensaje (nombre, plan, dias, fecha)
            $table->json('variables')->nullable();
            $table->string('estado')->default('pendiente');
            // día para el que se propuso: pasado ese día ya no se envía (el "vence en 3 días" quedaría falso)
            $table->date('fecha_aviso');
            $table->unsignedSmallInteger('intentos')->default(0);
            $table->timestamp('enviado_en')->nullable();
            $table->string('id_mensaje_whatsapp')->nullable();
            // por qué falló o se canceló
            $table->text('detalle')->nullable();
            $table->timestamps();

            // un solo aviso de cada tipo por suscripción, aunque se proponga dos veces
            $table->unique(['suscripcion_id', 'tipo']);
            $table->index(['estado', 'id']);
            $table->index('cliente_id');
        });

        DB::statement("ALTER TABLE recordatorios ADD CONSTRAINT chk_recordatorio_tipo CHECK (tipo IN ('por_vencer', 'vence_hoy'))");
        DB::statement("ALTER TABLE recordatorios ADD CONSTRAINT chk_recordatorio_estado CHECK (estado IN ('pendiente', 'enviando', 'enviado', 'fallido', 'cancelado'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorios');
    }
};
