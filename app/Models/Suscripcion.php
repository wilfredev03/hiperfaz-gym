<?php

namespace App\Models;

use Database\Factories\SuscripcionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

/**
 * Una suscripción nunca se edita (principio #10): renovar crea una fila
 * nueva. Lo único que cambia es `estado`; cliente, plan, venta y fechas
 * quedan fijos desde que se crea. La única excepción es `corregirFechas()`,
 * para arreglar un error de digitación: exige motivo y deja una fila en
 * `suscripcion_correcciones`.
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
     * Corrige las fechas (inicio y último día con acceso) de una suscripción
     * mal cargada. No toca la venta ni los pagos. Deja constancia del cambio y,
     * si la suscripción estaba activa o vencida, vuelve a ordenar su `estado`
     * según las fechas nuevas.
     */
    public function corregirFechas(string $inicio, string $vencimiento, string $motivo, int $usuarioId): void
    {
        if ($this->estado === 'cancelada') {
            throw ValidationException::withMessages(['fecha_inicio' => 'Una membresía cancelada no se corrige.']);
        }

        $inicio = substr($inicio, 0, 10);
        $vencimiento = substr($vencimiento, 0, 10);

        if ($vencimiento < $inicio) {
            throw ValidationException::withMessages(['fecha_vencimiento' => 'El último día no puede ser anterior al inicio.']);
        }

        if ($inicio === $this->fecha_inicio->toDateString() && $vencimiento === $this->fecha_vencimiento->toDateString()) {
            throw ValidationException::withMessages(['fecha_inicio' => 'Las fechas son las mismas: no hay nada que corregir.']);
        }

        DB::transaction(function () use ($inicio, $vencimiento, $motivo, $usuarioId) {
            $this->correcciones()->create([
                'usuario_id' => $usuarioId,
                'fecha_inicio_anterior' => $this->fecha_inicio,
                'fecha_vencimiento_anterior' => $this->fecha_vencimiento,
                'fecha_inicio_nueva' => $inicio,
                'fecha_vencimiento_nueva' => $vencimiento,
                'motivo' => $motivo,
            ]);

            $estado = in_array($this->estado, ['activa', 'vencida'], true)
                ? ($vencimiento < now()->toDateString() ? 'vencida' : 'activa')
                : $this->estado;

            // update directo a la tabla: el guard de `booted()` bloquea cambiar fechas
            // desde el modelo, y esta es la única ruta autorizada para hacerlo
            DB::table($this->getTable())->where('id', $this->id)->update([
                'fecha_inicio' => $inicio,
                'fecha_vencimiento' => $vencimiento,
                'estado' => $estado,
                'updated_at' => now(),
            ]);
        });

        $this->refresh();
    }

    /**
     * @return HasMany<SuscripcionCorreccion, $this>
     */
    public function correcciones(): HasMany
    {
        return $this->hasMany(SuscripcionCorreccion::class);
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
