<?php

namespace App\Models;

use Database\Factories\VentaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Venta extends Model
{
    /** @use HasFactory<VentaFactory> */
    use HasFactory;

    protected $fillable = [
        'numero_venta', 'cliente_id', 'usuario_id', 'tasa_cambio_id', 'cierre_caja_id',
        'subtotal', 'descuento', 'total', 'es_credito', 'monto_recibido', 'vuelto',
        'estado', 'fecha', 'anulada_en', 'anulada_por_id', 'motivo_anulacion',
    ];

    protected $casts = [
        'fecha' => 'date',
        'anulada_en' => 'datetime',
        'subtotal' => 'decimal:4',
        'descuento' => 'decimal:4',
        'total' => 'decimal:4',
        'monto_recibido' => 'decimal:4',
        'vuelto' => 'decimal:4',
        'es_credito' => 'boolean',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function anuladaPor()
    {
        return $this->belongsTo(User::class, 'anulada_por_id');
    }

    public function tasaCambio()
    {
        return $this->belongsTo(TasaCambio::class);
    }

    public function cierreCaja()
    {
        return $this->belongsTo(CierreCaja::class);
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class);
    }

    public function getSaldoPendienteAttribute(): float
    {
        return (float) $this->total - (float) $this->pagos()->sum('monto');
    }
}
