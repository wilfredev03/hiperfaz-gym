<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tasas_cambio', function (Blueprint $table) {
            $table->id();
            $table->date('fecha');
            $table->decimal('tasa', 12, 4);
            $table->enum('tipo', ['automatica', 'manual']);
            $table->foreignId('usuario_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index('fecha');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasas_cambio');
    }
};
