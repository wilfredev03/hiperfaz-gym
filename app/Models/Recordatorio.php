<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recordatorio extends Model
{
    protected $table = 'recordatorios';

    protected $fillable = [
        'cliente_id', 'suscripcion_id', 'plantilla_id', 'tipo', 'telefono', 'mensaje',
        'variables', 'estado', 'fecha_aviso', 'intentos', 'enviado_en',
        'id_mensaje_whatsapp', 'detalle',
    ];

    protected $casts = [
        'variables' => 'array',
        'fecha_aviso' => 'date',
        'enviado_en' => 'datetime',
        'intentos' => 'integer',
    ];

    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    public function suscripcion(): BelongsTo
    {
        return $this->belongsTo(Suscripcion::class);
    }

    public function plantilla(): BelongsTo
    {
        return $this->belongsTo(RecordatorioPlantilla::class, 'plantilla_id');
    }
}
