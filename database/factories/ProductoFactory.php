<?php

namespace Database\Factories;

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Producto>
 */
class ProductoFactory extends Factory
{
    protected $model = Producto::class;

    public function definition(): array
    {
        $nombre = fake()->unique()->randomElement([
            'Proteína Whey Especial',
            'Creatina Monohidratada',
            'Barra Energética',
            'Barra de Proteína',
            'Pre-entreno Cítrico',
            'Aminoácidos BCAA',
            'Shaker 700ml',
            'Toalla Deportiva',
            'Guantes de Entrenamiento',
            'Agua Mineral 600ml',
            'Bebida Isotónica',
            'Malta',
            'Refresco Cola 2L',
            'Batido de Fresa',
            'Batido de Chocolate',
            'Batido de Vainilla',
            'Café Negro',
            'Gel Energético',
            'Cinturón de Levantamiento',
            'Banda Elástica',
        ]);

        $precioVenta = fake()->randomFloat(2, 0.5, 15);

        return [
            'categoria_id' => Categoria::factory(),
            'unidad_medida_id' => UnidadMedida::factory(),
            'nombre' => $nombre,
            'descripcion' => fake()->boolean(60) ? fake()->sentence() : null,
            'precio_venta' => $precioVenta,
            'costo_promedio' => round($precioVenta * fake()->randomFloat(2, 0.5, 0.8), 4),
            'stock_actual' => fake()->randomFloat(3, 5, 100),
            'controla_inventario' => true,
            'activo' => true,
        ];
    }

    /**
     * Productos hechos al momento (ej. batidos preparados al momento, café)
     * que no descuentan inventario.
     */
    public function sinControlDeInventario(): static
    {
        return $this->state(fn (array $attributes) => [
            'controla_inventario' => false,
            'stock_actual' => 0,
        ]);
    }

    public function sinStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_actual' => 0,
        ]);
    }

    public function inactivo(): static
    {
        return $this->state(fn (array $attributes) => [
            'activo' => false,
        ]);
    }
}
