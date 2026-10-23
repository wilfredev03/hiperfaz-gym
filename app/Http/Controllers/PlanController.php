<?php

namespace App\Http\Controllers;

use App\Http\Requests\PlanRequest;
use App\Models\Categoria;
use App\Models\Plan;
use App\Models\Producto;
use App\Models\UnidadMedida;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PlanController extends Controller
{
    public function index(): Response
    {
        $planes = Plan::query()
            ->where('personalizado', false)
            ->with('producto:id,nombre,precio_venta,activo')
            ->withCount('suscripciones')
            ->orderBy('duracion_dias')
            ->orderBy('id')
            ->paginate(15)
            ->through(fn (Plan $plan) => [
                'id' => $plan->id,
                'nombre' => $plan->producto->nombre,
                'precio_venta' => $plan->producto->precio_venta,
                'duracion_dias' => $plan->duracion_dias,
                'activo' => $plan->producto->activo,
                'suscripciones_count' => $plan->suscripciones_count,
            ]);

        return Inertia::render('settings/planes/Index', ['planes' => $planes]);
    }

    /**
     * Un plan es un producto vendible sin inventario (principio #4) más su
     * duración; se crean juntos para que el admin no tenga que pasar por el
     * catálogo.
     */
    public function store(PlanRequest $request): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data) {
            $producto = Producto::create([
                'categoria_id' => $this->categoriaDePlanes()->id,
                'unidad_medida_id' => UnidadMedida::firstOrCreate(['abreviatura' => 'und'], ['nombre' => 'Unidad'])->id,
                'nombre' => $data['nombre'],
                'precio_venta' => $data['precio_venta'],
                'costo_promedio' => 0,
                'stock_actual' => 0,
                'controla_inventario' => false,
                'activo' => $data['activo'],
            ]);

            Plan::create(['producto_id' => $producto->id, 'duracion_dias' => $data['duracion_dias']]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan creado.')]);

        return to_route('planes.index');
    }

    /**
     * Cambiar la duración solo afecta ventas futuras: cada suscripción ya
     * emitida guarda sus propias fechas.
     */
    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($plan, $data) {
            $plan->producto->update([
                'nombre' => $data['nombre'],
                'precio_venta' => $data['precio_venta'],
                'activo' => $data['activo'],
            ]);

            $plan->update(['duracion_dias' => $data['duracion_dias']]);
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan actualizado.')]);

        return to_route('planes.index');
    }

    private function categoriaDePlanes(): Categoria
    {
        return Plan::categoriaDePlanes();
    }
}
