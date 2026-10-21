<?php

namespace App\Models;

use Database\Factories\SuscripcionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Una suscripción nunca se edita (principio #10): renovar crea una fila
 * nueva. Lo único que cambia es `estado`; cliente, plan, venta y fechas
 * quedan fijos desde que se crea.
 */
class Suscripcion extends Model
{
    /** @use HasFactory<SuscripcionFactory> */
    use HasFactory;

    protected $table = 'suscripciones';

    protected $fillable = [
        'cliente_id', 'plan_id', 'venta_detalle_id',
        'fecha_inicio', 'fecha_vencimiento', 'estado',
    ];

    protected $casts = [
        'fecha_inicio' => 'date',
        'fecha_vencimiento' => 'date',
    ];

    private const INMUTABLES = ['cliente_id', 'plan_id', 'venta_detalle_id', 'fecha_inicio', 'fecha_vencimiento'];

    protected static function booted(): void
    {
        static::updating(function (Suscripcion $suscripcion) {
            if ($suscripcion->isDirty(self::INMUTABLES)) {
                throw new LogicException('Una suscripción no se edita: una renovación crea una suscripción nueva.');
            }
        });
    }

    /**
     * Con derecho a entrar en la fecha dada (por defecto hoy): activa y
     * dentro de su rango de fechas.
     *
     * @param  Builder<Suscripcion>  $query
     */
    public function scopeVigentes(Builder $query, $fecha = null): void
    {
        $fecha = ($fecha ?? now())->toDateString();

        $query->where('estado', 'activa')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_vencimiento', '>=', $fecha);
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /**
     * @return BelongsTo<VentaDetalle, $this>
     */
    public function ventaDetalle(): BelongsTo
    {
        return $this->belongsTo(VentaDetalle::class);
    }
}
