<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->date('fecha_nacimiento')->nullable()->after('direccion');
            $table->string('foto')->nullable()->after('fecha_nacimiento');
            $table->string('contacto_emergencia_nombre')->nullable()->after('foto');
            $table->string('contacto_emergencia_telefono')->nullable()->after('contacto_emergencia_nombre');
            // identificador del socio en el terminal de acceso (fase 2); nullable
            // porque hoy no hay integración
            $table->string('id_acceso')->nullable()->unique()->after('contacto_emergencia_telefono');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['id_acceso']);
            $table->dropColumn([
                'fecha_nacimiento', 'foto', 'contacto_emergencia_nombre',
                'contacto_emergencia_telefono', 'id_acceso',
            ]);
        });
    }
};
