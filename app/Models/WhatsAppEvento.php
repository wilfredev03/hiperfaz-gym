<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Evento de la sesión de WhatsApp, solo inserción: no se edita ni se borra.
 */
class WhatsAppEvento extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'whatsapp_eventos';

    protected $fillable = [
        'evento_id', 'tipo', 'detalle', 'numero', 'codigo', 'usuario_id', 'ocurrido_en',
    ];

    protected $casts = ['ocurrido_en' => 'datetime'];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Los eventos de WhatsApp son de solo inserción.'));
        static::deleting(fn () => throw new LogicException('Los eventos de WhatsApp son de solo inserción.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }
}
