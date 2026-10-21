<?php

namespace App\Models;

use Database\Factories\VentaPagoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaPago extends Model
{
    /** @use HasFactory<VentaPagoFactory> */
    use HasFactory;

    protected $table = 'venta_pagos';

    protected $fillable = [
        'venta_id', 'metodo_pago_id', 'cierre_caja_id', 'monto', 'referencia', 'fecha_pago',
    ];

    protected $casts = [
        'monto' => 'decimal:4',
        'fecha_pago' => 'datetime',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    public function cierreCaja()
    {
        return $this->belongsTo(CierreCaja::class);
    }
}
