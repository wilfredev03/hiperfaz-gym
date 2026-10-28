<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecordatorioPlantilla extends Model
{
    public const TIPOS = ['por_vencer', 'vence_hoy'];

    protected $table = 'recordatorio_plantillas';

    protected $fillable = ['tipo', 'nombre', 'cuerpo', 'activa'];

    protected $casts = ['activa' => 'boolean'];
}
