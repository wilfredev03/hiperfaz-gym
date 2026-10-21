<?php

namespace App\Models;

use Database\Factories\SucursalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Sucursal extends Model
{
    /** @use HasFactory<SucursalFactory> */
    use HasFactory;

    protected $table = 'sucursales';

    protected $fillable = [
        'nombre', 'termino_orden', 'direccion', 'telefono', 'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
    ];
}
