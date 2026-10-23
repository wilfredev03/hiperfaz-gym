<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Registro de una corrección de fechas de una suscripción, solo inserción:
 * deja constancia de quién la hizo, cuándo, por qué y cómo estaba antes.
 */
class SuscripcionCorreccion extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'suscripcion_correcciones';

    protected $fillable = [
        'suscripcion_id', 'usuario_id',
        'fecha_inicio_anterior', 'fecha_vencimiento_anterior',
        'fecha_inicio_nueva', 'fecha_vencimiento_nueva', 'motivo',
    ];

    protected $casts = [
        'fecha_inicio_anterior' => 'date',
        'fecha_vencimiento_anterior' => 'date',
        'fecha_inicio_nueva' => 'date',
        'fecha_vencimiento_nueva' => 'date',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las correcciones son de solo inserción.'));
        static::deleting(fn () => throw new LogicException('Las correcciones son de solo inserción.'));
    }

    /**
     * @return BelongsTo<Suscripcion, $this>
     */
    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
