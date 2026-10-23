<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('planes', function (Blueprint $table) {
            // el plan "Membresía personalizada": una sola fila que respalda las
            // membresías de duración y precio libres (9 días, del X al Y...)
            $table->boolean('personalizado')->default(false)->after('duracion_dias');
        });

        DB::statement('CREATE UNIQUE INDEX uq_plan_personalizado ON planes (personalizado) WHERE personalizado');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS uq_plan_personalizado');

        Schema::table('planes', function (Blueprint $table) {
            $table->dropColumn('personalizado');
        });
    }
};
