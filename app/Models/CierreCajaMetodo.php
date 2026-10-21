<?php

namespace App\Models;

use Database\Factories\CierreCajaMetodoFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CierreCajaMetodo extends Model
{
    /** @use HasFactory<CierreCajaMetodoFactory> */
    use HasFactory;

    protected $table = 'cierre_caja_metodos';

    protected $fillable = ['cierre_caja_id', 'metodo_pago_id', 'monto', 'monto_bs'];

    protected $casts = [
        'monto' => 'decimal:4',
        'monto_bs' => 'decimal:4',
    ];

    public function cierreCaja()
    {
        return $this->belongsTo(CierreCaja::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }
}
