<?php

namespace App\Models;

use Database\Factories\MovimientoInventarioFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoInventario extends Model
{
    /** @use HasFactory<MovimientoInventarioFactory> */
    use HasFactory;

    protected $table = 'movimientos_inventario';

    protected $fillable = [
        'producto_id', 'tipo', 'cantidad', 'stock_anterior', 'stock_nuevo',
        'referencia_tipo', 'referencia_id', 'usuario_id', 'notas',
    ];

    protected $casts = [
        'cantidad' => 'decimal:3',
        'stock_anterior' => 'decimal:3',
        'stock_nuevo' => 'decimal:3',
    ];

    public function producto()
    {
        return $this->belongsTo(Producto::class);
    }
}
