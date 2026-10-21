<?php

namespace App\Models;

use Database\Factories\UnidadMedidaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UnidadMedida extends Model
{
    /** @use HasFactory<UnidadMedidaFactory> */
    use HasFactory;

    protected $table = 'unidades_medida';

    protected $fillable = ['nombre', 'abreviatura'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
