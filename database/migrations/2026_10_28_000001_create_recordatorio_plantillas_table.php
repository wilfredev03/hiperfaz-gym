<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recordatorio_plantillas', function (Blueprint $table) {
            $table->id();
            $table->string('tipo');
            $table->string('nombre');
            // texto con variables {nombre} {plan} {dias} {fecha} y alternativas [a|b|c]
            $table->text('cuerpo');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('tipo');
        });

        DB::statement("ALTER TABLE recordatorio_plantillas ADD CONSTRAINT chk_recordatorio_plantilla_tipo CHECK (tipo IN ('por_vencer', 'vence_hoy'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('recordatorio_plantillas');
    }
};
