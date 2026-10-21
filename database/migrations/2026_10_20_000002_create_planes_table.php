<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('planes', function (Blueprint $table) {
            $table->id();
            // un plan es un producto vendible (sin inventario) con una duración
            $table->foreignId('producto_id')->unique()->constrained('productos');
            $table->unsignedSmallInteger('duracion_dias');
            $table->timestamps();
        });

        DB::statement('ALTER TABLE planes ADD CONSTRAINT chk_plan_duracion_positiva CHECK (duracion_dias > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('planes');
    }
};
