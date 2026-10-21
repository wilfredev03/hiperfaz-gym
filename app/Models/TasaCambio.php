<?php

namespace App\Models;

use Database\Factories\TasaCambioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class TasaCambio extends Model
{
    /** @use HasFactory<TasaCambioFactory> */
    use HasFactory;

    protected $table = 'tasas_cambio';

    protected $fillable = ['fecha', 'tasa', 'tipo', 'usuario_id', 'activa'];

    protected $casts = [
        'fecha' => 'date',
        'tasa' => 'decimal:4',
        'activa' => 'boolean',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public static function actual(): ?self
    {
        return static::where('activa', true)->latest('fecha')->first();
    }

    /**
     * La tasa activa solo sirve para facturar si es la de HOY. Si nadie
     * cargó la tasa del día (ej. quedó la de ayer marcada como activa),
     * esto devuelve null y quien llama debe bloquear la operación en vez
     * de facturar con una tasa vencida.
     */
    public static function vigenteHoy(): ?self
    {
        $tasa = static::actual();

        return $tasa && $tasa->fecha->isToday() ? $tasa : null;
    }

    /**
     * Registra una nueva tasa vigente para hoy, desactivando cualquier otra
     * tasa activa. Nunca modifica ni elimina tasas pasadas: solo agrega una
     * fila nueva y mueve la bandera "activa" hacia ella.
     */
    public static function registrarNueva(float $tasa, string $tipo, ?int $usuarioId = null): self
    {
        return DB::transaction(function () use ($tasa, $tipo, $usuarioId) {
            static::where('activa', true)->update(['activa' => false]);

            return static::create([
                'fecha' => now()->toDateString(),
                'tasa' => $tasa,
                'tipo' => $tipo,
                'usuario_id' => $usuarioId,
                'activa' => true,
            ]);
        });
    }
}
