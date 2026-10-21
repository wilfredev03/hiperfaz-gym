<?php

namespace App\Models;

use Database\Factories\CierreCajaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CierreCaja extends Model
{
    /** @use HasFactory<CierreCajaFactory> */
    use HasFactory;

    protected $table = 'cierres_caja';

    protected $fillable = [
        'usuario_id', 'tasa_cambio_id', 'fecha', 'hora_apertura', 'hora_cierre',
        'total_ventas', 'total_ventas_bs', 'total_membresias', 'total_membresias_bs',
        'estado', 'correo_enviado_at',
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora_apertura' => 'datetime',
        'hora_cierre' => 'datetime',
        'total_ventas' => 'decimal:4',
        'total_ventas_bs' => 'decimal:4',
        'total_membresias' => 'decimal:4',
        'total_membresias_bs' => 'decimal:4',
        'correo_enviado_at' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function tasaCambio()
    {
        return $this->belongsTo(TasaCambio::class);
    }

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function pagosRecibidos()
    {
        return $this->hasMany(VentaPago::class);
    }

    public function detalleMetodos()
    {
        return $this->hasMany(CierreCajaMetodo::class);
    }

    public function ventasCredito()
    {
        return $this->hasMany(Venta::class)->where('es_credito', true)->where('estado', '!=', 'anulada');
    }

    public static function abiertoDeHoy(): ?self
    {
        return static::where('estado', 'abierto')->whereDate('fecha', now()->toDateString())->first();
    }

    /**
     * Abre automáticamente la caja del día cuando todavía no existe una
     * para hoy. No hay apertura manual: la primera venta del día crea el
     * cierre, así una venta nunca queda bloqueada esperando que alguien
     * abra caja.
     */
    public static function abrirAutomaticaDeHoy(?int $usuarioId): self
    {
        return static::create([
            'usuario_id' => $usuarioId,
            'tasa_cambio_id' => TasaCambio::actual()?->id,
            'fecha' => now()->toDateString(),
            'hora_apertura' => now(),
            'estado' => 'abierto',
        ]);
    }
}
