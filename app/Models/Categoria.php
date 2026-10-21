<?php

namespace App\Models;

use Database\Factories\CategoriaFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Categoria extends Model
{
    /** @use HasFactory<CategoriaFactory> */
    use HasFactory;

    protected $fillable = ['nombre', 'activo'];

    public function productos()
    {
        return $this->hasMany(Producto::class);
    }
}
