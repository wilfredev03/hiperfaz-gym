<?php

namespace App\Models;

use Database\Factories\VentaDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VentaDetalle extends Model
{
    /** @use HasFactory<VentaDetalleFactory> */
    use HasFactory;

    protected $table = 'venta_detalle';

    protected $fillable = [
        'venta_id', 'producto_id', 'cantidad', 'precio_unitario',
        'costo_unitario_momento', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'precio_unitario' => 'decimal:4',
        'costo_unitario_momento' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function suscripcion()
    {
        return $this->hasOne(Suscripcion::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
