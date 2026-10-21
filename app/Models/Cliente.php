<?php

namespace App\Models;

use Database\Factories\ClienteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    /** @use HasFactory<ClienteFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombre', 'documento', 'telefono', 'email', 'direccion',
        'saldo_pendiente', 'activo',
        'fecha_nacimiento', 'foto', 'contacto_emergencia_nombre',
        'contacto_emergencia_telefono', 'id_acceso',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'saldo_pendiente' => 'decimal:4',
        'activo' => 'boolean',
    ];

    public function ventas()
    {
        return $this->hasMany(Venta::class);
    }

    public function suscripciones()
    {
        return $this->hasMany(Suscripcion::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    /** Suscripción con derecho a entrar hoy, si la hay (la que vence más tarde). */
    public function suscripcionVigente(): ?Suscripcion
    {
        return $this->suscripciones()->vigentes()->orderByDesc('fecha_vencimiento')->first();
    }

    public function ventasPendientes()
    {
        return $this->hasMany(Venta::class)->whereIn('estado', ['pendiente', 'parcial']);
    }
}
