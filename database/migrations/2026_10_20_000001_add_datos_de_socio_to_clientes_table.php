<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('foto')->nullable()->after('direccion');
            // identificador del socio en el terminal de acceso (fase 2); nullable
            // porque hoy no hay integración
            $table->string('id_acceso')->nullable()->unique()->after('foto');
        });
    }

    public function down(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropUnique(['id_acceso']);
            $table->dropColumn(['foto', 'id_acceso']);
        });
    }
};
