<?php

namespace App\Models;

use Database\Factories\AsistenciaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evento de acceso, solo inserción (principio #11): no se edita ni se borra.
 * Hoy lo registra recepción (`origen = manual`); en la fase 2 lo alimentará
 * el terminal (`origen = terminal`).
 */
class Asistencia extends Model
{
    /** @use HasFactory<AsistenciaFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    protected $table = 'asistencias';

    protected $fillable = [
        'cliente_id', 'fecha_hora', 'dispositivo', 'resultado', 'origen', 'usuario_id',
    ];

    protected $casts = ['fecha_hora' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Las asistencias son de solo inserción.'));
        static::deleting(fn () => throw new LogicException('Las asistencias son de solo inserción.'));
    }

    /**
     * @return BelongsTo<Cliente, $this>
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
