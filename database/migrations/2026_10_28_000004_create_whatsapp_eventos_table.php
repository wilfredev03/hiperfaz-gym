<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Bitácora de la sesión de WhatsApp, solo inserción (como `asistencias`):
     * cuándo se generó un QR, se vinculó un número, se cayó la conexión y se
     * recuperó. Casi todo lo reporta el bot (`evento_id` es su contador); los
     * cierres hechos desde el panel los escribe Laravel con el usuario.
     */
    public function up(): void
    {
        Schema::create('whatsapp_eventos', function (Blueprint $table) {
            $table->id();
            // contador del bot; NULL en los eventos que escribe Laravel
            $table->unsignedBigInteger('evento_id')->nullable();
            $table->string('tipo');
            $table->text('detalle')->nullable();
            // número vinculado en ese momento (58414XXXXXXX)
            $table->string('numero')->nullable();
            // código de desconexión de WhatsApp, si lo hubo
            $table->unsignedSmallInteger('codigo')->nullable();
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('ocurrido_en');
            $table->timestamp('created_at')->nullable();

            // el mismo evento del bot no se guarda dos veces (se consulta repetidamente)
            $table->unique(['evento_id', 'ocurrido_en']);
            $table->index('ocurrido_en');
        });

        DB::statement("ALTER TABLE whatsapp_eventos ADD CONSTRAINT chk_whatsapp_evento_tipo CHECK (tipo IN ('servicio_iniciado', 'qr_pendiente', 'conectado', 'conexion_perdida', 'reconectado', 'sesion_cerrada', 'cierre_manual'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_eventos');
    }
};
