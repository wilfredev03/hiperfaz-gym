<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductoRequest;
use App\Models\Categoria;
use App\Models\Producto;
use App\Models\TasaCambio;
use App\Models\UnidadMedida;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductoController extends Controller
{
    private const ESTADOS = ['activos', 'inactivos', 'todos'];

    /**
     * Display a listing of the resource, filtrable por nombre, categoría y
     * estado (activos/inactivos/todos).
     */
    public function index(Request $request): Response
    {
        $data = $request->validate([
            'buscar' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['nullable', 'integer', 'exists:categorias,id'],
            'estado' => ['nullable', 'in:'.implode(',', self::ESTADOS)],
        ]);

        $estado = $data['estado'] ?? 'activos';

        $productos = Producto::query()
            ->sinPlan()
            ->select('productos.*')
            ->join('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->with(['categoria', 'unidadMedida'])
            ->when(
                $data['buscar'] ?? null,
                fn ($query, $buscar) => $query->where('productos.nombre', 'ilike', '%'.$buscar.'%')
            )
            ->when(
                $data['categoria_id'] ?? null,
                fn ($query, $categoriaId) => $query->where('productos.categoria_id', $categoriaId)
            )
            ->when($estado === 'activos', fn ($query) => $query->where('productos.activo', true))
            ->when($estado === 'inactivos', fn ($query) => $query->where('productos.activo', false))
            ->orderBy('categorias.nombre')
            ->orderBy('productos.nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('productos/Index', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'unidadesMedida' => UnidadMedida::orderBy('nombre')->get(),
            'tasaCambio' => TasaCambio::actual(),
            'filtros' => [
                'buscar' => $data['buscar'] ?? '',
                'categoria_id' => $data['categoria_id'] ?? null,
                'estado' => $estado,
            ],
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): Response
    {
        return Inertia::render('productos/Create', [
            'categorias' => Categoria::orderBy('nombre')->get(),
            'unidadesMedida' => UnidadMedida::orderBy('nombre')->get(),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(ProductoRequest $request): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        } else {
            unset($data['imagen']);
        }

        Producto::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Producto creado.')]);

        return to_route('productos.index');
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(ProductoRequest $request, Producto $producto): RedirectResponse
    {
        $data = $request->validated();

        if ($request->hasFile('imagen')) {
            if ($producto->imagen) {
                Storage::disk('public')->delete($producto->imagen);
            }
            $data['imagen'] = $request->file('imagen')->store('productos', 'public');
        } else {
            unset($data['imagen']);
        }

        $producto->update($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Producto actualizado.')]);

        return to_route('productos.index');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Producto $producto): RedirectResponse
    {
        $producto->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Producto eliminado.')]);

        return to_route('productos.index');
    }

    /**
     * Activa o desactiva un producto sin pasar por la ficha de edición.
     * Endpoint standalone (no Inertia) pensado para consumirse con useHttp
     * desde el catálogo y refrescar el listado con un partial reload.
     */
    public function toggleActivo(Producto $producto): JsonResponse
    {
        $producto->update(['activo' => ! $producto->activo]);

        return response()->json(['activo' => $producto->activo]);
    }
}
