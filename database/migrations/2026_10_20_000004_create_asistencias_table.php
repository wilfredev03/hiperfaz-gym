<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // tabla de solo inserción (append-only): sin updated_at
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            // null cuando el dispositivo reporta un acceso que no se pudo asociar a un socio
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->timestamp('fecha_hora');
            $table->string('dispositivo')->nullable();
            $table->string('resultado');
            $table->string('origen');
            // quien lo registró cuando el origen es manual
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('fecha_hora');
            $table->index(['cliente_id', 'fecha_hora']);
        });

        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencia_resultado CHECK (resultado IN ('permitido', 'denegado'))");
        DB::statement("ALTER TABLE asistencias ADD CONSTRAINT chk_asistencia_origen CHECK (origen IN ('manual', 'terminal'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
