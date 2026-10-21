<?php

namespace App\Models;

use Database\Factories\OrdenCompraFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrdenCompra extends Model
{
    /** @use HasFactory<OrdenCompraFactory> */
    use HasFactory;

    protected $table = 'ordenes_compra';

    protected $fillable = [
        'numero_orden', 'proveedor_id', 'usuario_id', 'tasa_cambio_id',
        'estado', 'fecha', 'total', 'notas',
    ];

    protected $casts = [
        'fecha' => 'date',
        'total' => 'decimal:4',
    ];

    public function proveedor()
    {
        return $this->belongsTo(Proveedor::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function tasaCambio()
    {
        return $this->belongsTo(TasaCambio::class);
    }

    public function detalles()
    {
        return $this->hasMany(OrdenCompraDetalle::class);
    }
}
