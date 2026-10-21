<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('metodos_pago', function (Blueprint $table) {
            // orden de aparición en la pantalla de cobro; configurable por
            // método, no hardcodeado por nombre en el código
            $table->unsignedSmallInteger('orden')->default(0)->after('activo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('metodos_pago', function (Blueprint $table) {
            $table->dropColumn('orden');
        });
    }
};
