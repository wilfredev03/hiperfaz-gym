<?php

namespace App\Models;

use Database\Factories\MetodoPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    /** @use HasFactory<MetodoPagoFactory> */
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $fillable = ['nombre', 'moneda', 'requiere_referencia', 'activo', 'orden'];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'activo' => 'boolean',
    ];
}
