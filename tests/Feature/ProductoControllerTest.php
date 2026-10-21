<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('productos.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of productos', function () {
    $usuario = User::factory()->create();
    Producto::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('productos.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 3)
    );
});

test('only shows active productos by default', function () {
    $usuario = User::factory()->create();
    Producto::factory()->count(2)->create();
    Producto::factory()->inactivo()->create();

    $response = $this->actingAs($usuario)->get(route('productos.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 2)
        ->where('filtros.estado', 'activos')
    );
});

test('filters productos by estado inactivos', function () {
    $usuario = User::factory()->create();
    Producto::factory()->count(2)->create();
    $inactivo = Producto::factory()->inactivo()->create();

    $response = $this->actingAs($usuario)->get(route('productos.index', ['estado' => 'inactivos']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 1)
        ->where('productos.data.0.id', $inactivo->id)
    );
});

test('filters productos by estado todos', function () {
    $usuario = User::factory()->create();
    Producto::factory()->count(2)->create();
    Producto::factory()->inactivo()->create();

    $response = $this->actingAs($usuario)->get(route('productos.index', ['estado' => 'todos']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 3)
    );
});

test('filters productos by nombre', function () {
    $usuario = User::factory()->create();
    Producto::factory()->create(['nombre' => 'Proteína Whey Especial']);
    Producto::factory()->create(['nombre' => 'Harina P.A.N.']);

    $response = $this->actingAs($usuario)->get(route('productos.index', ['buscar' => 'whey']));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 1)
        ->where('productos.data.0.nombre', 'Proteína Whey Especial')
    );
});

test('filters productos by categoria', function () {
    $usuario = User::factory()->create();
    $categoriaA = Categoria::factory()->create();
    $categoriaB = Categoria::factory()->create();
    Producto::factory()->create(['categoria_id' => $categoriaA->id]);
    Producto::factory()->create(['categoria_id' => $categoriaB->id]);

    $response = $this->actingAs($usuario)->get(route('productos.index', ['categoria_id' => $categoriaA->id]));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Index', false)
        ->has('productos.data', 1)
        ->where('productos.data.0.categoria_id', $categoriaA->id)
    );
});

test('renders the create form with categorias and unidades de medida', function () {
    $usuario = User::factory()->create();
    Categoria::factory()->count(2)->create();
    UnidadMedida::factory()->count(2)->create();

    $response = $this->actingAs($usuario)->get(route('productos.create'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('productos/Create', false)
        ->has('categorias', 2)
        ->has('unidadesMedida', 2)
    );
});

test('creates a producto and redirects to the index', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();

    $response = $this->actingAs($usuario)->post(route('productos.store'), [
        'categoria_id' => $categoria->id,
        'unidad_medida_id' => $unidadMedida->id,
        'nombre' => 'Proteína Whey',
        'descripcion' => 'Proteína whey especial',
        'precio_venta' => 4.5,
        'costo_promedio' => 3.2,
        'controla_inventario' => true,
        'activo' => true,
    ]);

    $response->assertRedirect(route('productos.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Producto creado.']);
    $this->assertDatabaseHas('productos', [
        'categoria_id' => $categoria->id,
        'unidad_medida_id' => $unidadMedida->id,
        'nombre' => 'Proteína Whey',
        'precio_venta' => 4.5,
    ]);
});

test('a new producto starts with zero stock regardless of what is submitted', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();

    $response = $this->actingAs($usuario)->post(route('productos.store'), [
        'categoria_id' => $categoria->id,
        'unidad_medida_id' => $unidadMedida->id,
        'nombre' => 'Proteína Whey',
        'precio_venta' => 4.5,
        'controla_inventario' => true,
        'activo' => true,
        'stock_actual' => 999,
    ]);

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseHas('productos', [
        'nombre' => 'Proteína Whey',
        'stock_actual' => 0,
    ]);
});

test('requires categoria_id, unidad_medida_id, nombre and precio_venta to create a producto', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('productos.store'), [
        'activo' => true,
        'controla_inventario' => true,
    ]);

    $response->assertSessionHasErrors(['categoria_id', 'unidad_medida_id', 'nombre', 'precio_venta']);
    $this->assertDatabaseCount('productos', 0);
});

test('rejects a precio_venta of zero or less when creating a producto', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();

    $response = $this->actingAs($usuario)->post(route('productos.store'), [
        'categoria_id' => $categoria->id,
        'unidad_medida_id' => $unidadMedida->id,
        'nombre' => 'Proteína Whey',
        'precio_venta' => 0,
        'controla_inventario' => true,
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['precio_venta']);
    $this->assertDatabaseCount('productos', 0);
});

test('rejects a categoria_id that does not exist', function () {
    $usuario = User::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();

    $response = $this->actingAs($usuario)->post(route('productos.store'), [
        'categoria_id' => 99999,
        'unidad_medida_id' => $unidadMedida->id,
        'nombre' => 'Proteína Whey',
        'precio_venta' => 4.5,
        'controla_inventario' => true,
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['categoria_id']);
    $this->assertDatabaseCount('productos', 0);
});

test('updates a producto and redirects to the index', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['nombre' => 'Original', 'precio_venta' => 5]);

    $response = $this->actingAs($usuario)->put(route('productos.update', $producto), [
        'categoria_id' => $producto->categoria_id,
        'unidad_medida_id' => $producto->unidad_medida_id,
        'nombre' => 'Actualizado',
        'precio_venta' => 7.5,
        'controla_inventario' => true,
        'activo' => true,
    ]);

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseHas('productos', [
        'id' => $producto->id,
        'nombre' => 'Actualizado',
        'precio_venta' => 7.5,
    ]);
});

test('does not change stock_actual when updating a producto', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['stock_actual' => 42]);

    $response = $this->actingAs($usuario)->put(route('productos.update', $producto), [
        'categoria_id' => $producto->categoria_id,
        'unidad_medida_id' => $producto->unidad_medida_id,
        'nombre' => $producto->nombre,
        'precio_venta' => $producto->precio_venta,
        'controla_inventario' => true,
        'activo' => true,
        'stock_actual' => 0,
    ]);

    $response->assertRedirect(route('productos.index'));
    $this->assertDatabaseHas('productos', [
        'id' => $producto->id,
        'stock_actual' => 42,
    ]);
});

test('deletes a producto and redirects to the index', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('productos.destroy', $producto));

    $response->assertRedirect(route('productos.index'));
    $this->assertSoftDeleted($producto);
});

test('toggles a producto from active to inactive', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->create(['activo' => true]);

    $response = $this->actingAs($usuario)->patch(route('productos.toggle_activo', $producto));

    $response->assertOk();
    $response->assertJson(['activo' => false]);
    $this->assertDatabaseHas('productos', ['id' => $producto->id, 'activo' => false]);
});

test('toggles a producto from inactive to active', function () {
    $usuario = User::factory()->create();
    $producto = Producto::factory()->inactivo()->create();

    $response = $this->actingAs($usuario)->patch(route('productos.toggle_activo', $producto));

    $response->assertOk();
    $response->assertJson(['activo' => true]);
    $this->assertDatabaseHas('productos', ['id' => $producto->id, 'activo' => true]);
});
