<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->string('numero_venta')->unique();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('usuario_id')->constrained('users');
            $table->foreignId('tasa_cambio_id')->nullable()->constrained('tasas_cambio')->nullOnDelete();
            // cierre en el que se FACTURÓ la venta (puede diferir del cierre en que se cobra si es a crédito)
            $table->foreignId('cierre_caja_id')->nullable()->constrained('cierres_caja')->nullOnDelete();
            $table->decimal('subtotal', 12, 4);
            $table->decimal('descuento', 12, 4)->default(0);
            $table->decimal('total', 12, 4);
            $table->boolean('es_credito')->default(false);
            $table->decimal('monto_recibido', 12, 4)->nullable();
            $table->decimal('vuelto', 12, 4)->nullable();
            $table->enum('estado', ['pendiente', 'parcial', 'pagada', 'anulada'])->default('pagada');
            $table->date('fecha');
            $table->timestamps();

            $table->index('fecha');
            $table->index('cliente_id');
            $table->index('cierre_caja_id');
        });

        // red de seguridad a nivel de base de datos: toda venta a crédito debe tener cliente
        DB::statement('ALTER TABLE ventas ADD CONSTRAINT chk_credito_requiere_cliente CHECK (es_credito = false OR cliente_id IS NOT NULL)');
    }

    public function down(): void
    {
        Schema::dropIfExists('ventas');
    }
};
