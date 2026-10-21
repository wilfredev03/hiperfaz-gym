<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // quién anuló la venta, cuándo y por qué; la venta en sí no se edita
        // más allá de pasar su estado a `anulada` (principio #1)
        Schema::table('ventas', function (Blueprint $table) {
            $table->timestamp('anulada_en')->nullable();
            $table->foreignId('anulada_por_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('motivo_anulacion')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('anulada_por_id');
            $table->dropColumn(['anulada_en', 'motivo_anulacion']);
        });
    }
};
