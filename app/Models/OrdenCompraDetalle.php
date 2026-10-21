<?php

namespace App\Models;

use Database\Factories\OrdenCompraDetalleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCompraDetalle extends Model
{
    /** @use HasFactory<OrdenCompraDetalleFactory> */
    use HasFactory;

    protected $table = 'orden_compra_detalle';

    protected $fillable = [
        'orden_compra_id', 'producto_id', 'cantidad', 'cantidad_recibida',
        'costo_unitario', 'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'cantidad_recibida' => 'decimal:3',
        'costo_unitario' => 'decimal:4',
        'subtotal' => 'decimal:4',
    ];

    public function ordenCompra()
    {
        return $this->belongsTo(OrdenCompra::class);
    }

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
