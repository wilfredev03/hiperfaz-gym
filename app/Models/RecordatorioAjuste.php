<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordatorioAjuste extends Model
{
    protected $table = 'recordatorio_ajustes';

    protected $fillable = [
        'activo', 'hora_inicio', 'hora_fin', 'espera_min_seg', 'espera_max_seg',
        'mensajes_descanso_min', 'mensajes_descanso_max', 'descanso_min', 'descanso_max',
        'dias_aviso', 'duracion_minima_dias',
        'enviados_desde_descanso', 'mensajes_para_descanso', 'proximo_envio_at', 'descanso_hasta',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'proximo_envio_at' => 'datetime',
        'descanso_hasta' => 'datetime',
    ];

    /** La fila única de ajustes; se crea con los valores por defecto la primera vez. */
    public static function actual(): self
    {
        // refresh(): create() no trae los valores por defecto que pone la base
        return static::query()->first() ?? static::create([])->refresh();
    }

    /** ¿La hora actual cae dentro del horario de trabajo del bot? */
    public function dentroDeHorario(): bool
    {
        $ahora = now()->format('H:i');

        return $ahora >= $this->hora_inicio && $ahora < $this->hora_fin;
    }
}
