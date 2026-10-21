<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Meta diaria de venta de la sucursal, expresada en cantidad de unidades
     * de una o varias categorías (ej. 50 empanadas al día, contando tanto
     * "Empanadas" como "Empanadas Gourmet"). Son varias y no una sola porque
     * lo que el negocio cuenta como "una empanada" casi nunca coincide con
     * una única categoría del catálogo.
     *
     * Vive en `sucursales` y no en el código porque cada negocio se pone su
     * propia meta sobre sus propias categorías: la empanadería suma sus dos
     * categorías de empanadas, la carnicería podría apuntar a "Carnes" en
     * kg, y quien no quiera meta deja los campos vacíos y el panel no la
     * muestra. Es configuración por sucursal, igual que `termino_orden`
     * (ver principio #9 del CLAUDE.md).
     */
    public function up(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            // decimal y no entero: una meta también puede ser "20 kg al día"
            $table->decimal('meta_diaria_cantidad', 12, 3)->nullable()->after('activa');
            // cómo llamar a la meta en el panel ("Empanadas"); si se deja
            // vacía se arma con los nombres de las categorías elegidas
            $table->string('meta_diaria_etiqueta', 50)->nullable()->after('meta_diaria_cantidad');
        });

        Schema::create('sucursal_meta_categorias', function (Blueprint $table) {
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('categoria_id')->constrained('categorias')->cascadeOnDelete();

            // la clave compuesta evita categorías repetidas y ya sirve de
            // índice para buscar las categorías de una sucursal
            $table->primary(['sucursal_id', 'categoria_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursal_meta_categorias');

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['meta_diaria_cantidad', 'meta_diaria_etiqueta']);
        });
    }
};
