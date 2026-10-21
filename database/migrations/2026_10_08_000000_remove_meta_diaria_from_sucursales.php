<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La meta diaria por sucursal ya no forma parte del sistema: se elimina la
     * tabla pivote de categorías y las columnas de `sucursales`.
     */
    public function up(): void
    {
        Schema::dropIfExists('sucursal_meta_categorias');

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['meta_diaria_cantidad', 'meta_diaria_etiqueta']);
        });
    }

    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->decimal('meta_diaria_cantidad', 12, 3)->nullable()->after('activa');
            $table->string('meta_diaria_etiqueta', 50)->nullable()->after('meta_diaria_cantidad');
        });

        Schema::create('sucursal_meta_categorias', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();
            $table->primary(['sucursal_id', 'categoria_id']);
        });
    }
};
