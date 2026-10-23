<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    protected $table = 'planes';

    protected $fillable = ['producto_id', 'duracion_dias', 'personalizado'];

    protected $casts = ['duracion_dias' => 'integer', 'personalizado' => 'boolean'];

    /**
     * El plan que respalda las membresías de duración y precio libres. Es uno
     * solo y se crea la primera vez que hace falta; no aparece en la lista de
     * planes ni en las cards (la duración y el precio de cada venta van en la
     * propia suscripción y el detalle de la venta).
     */
    public static function personalizado(): self
    {
        return DB::transaction(function () {
            $plan = static::where('personalizado', true)->first();

            if ($plan) {
                return $plan;
            }

            $producto = Producto::create([
                'categoria_id' => static::categoriaDePlanes()->id,
                'unidad_medida_id' => UnidadMedida::firstOrCreate(['abreviatura' => 'und'], ['nombre' => 'Unidad'])->id,
                'nombre' => 'Membresía personalizada',
                'precio_venta' => 0,
                'costo_promedio' => 0,
                'stock_actual' => 0,
                'controla_inventario' => false,
                'activo' => true,
            ]);

            return static::create(['producto_id' => $producto->id, 'duracion_dias' => 1, 'personalizado' => true]);
        });
    }

    /**
     * Los planes van en la misma categoría que los existentes; si es el
     * primero, se crea una por defecto que el admin puede renombrar.
     */
    public static function categoriaDePlanes(): Categoria
    {
        $existente = static::query()->latest('id')->first()?->producto?->categoria;

        return $existente ?? Categoria::firstOrCreate(['nombre' => 'Membresías']);
    }

    /**
     * @return BelongsTo<Producto, $this>
     */
    public function producto(): BelongsTo
    {
        return $this->belongsTo(Producto::class);
    }

    /**
     * @return HasMany<Suscripcion, $this>
     */
    public function suscripciones(): HasMany
    {
        return $this->hasMany(Suscripcion::class);
    }
}
