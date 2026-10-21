<?php

namespace App\Models;

use Database\Factories\ProductoFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Producto extends Model
{
    /** @use HasFactory<ProductoFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'categoria_id', 'unidad_medida_id', 'nombre', 'descripcion', 'imagen',
        'precio_venta', 'costo_promedio', 'stock_actual',
        'controla_inventario', 'activo',
    ];

    protected $appends = ['imagen_url'];

    protected $casts = [
        'precio_venta' => 'decimal:4',
        'costo_promedio' => 'decimal:4',
        'stock_actual' => 'decimal:3',
        'controla_inventario' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Productos de mostrador: excluye los planes de membresía, que se
     * administran en Configuración > Membresías y no son parte del catálogo.
     *
     * @param  Builder<Producto>  $query
     */
    public function scopeSinPlan(Builder $query): void
    {
        $query->whereDoesntHave('plan');
    }

    public function getImagenUrlAttribute(): ?string
    {
        return $this->imagen ? Storage::disk('public')->url($this->imagen) : null;
    }

    /**
     * @return BelongsTo<Categoria, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(Categoria::class);
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class);
    }

    /** Presente solo si el producto es un plan de membresía. */
    public function plan(): HasOne
    {
        return $this->hasOne(Plan::class);
    }

    public function movimientosInventario()
    {
        return $this->hasMany(MovimientoInventario::class);
    }
}
