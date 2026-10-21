<?php

use App\Models\Categoria;
use App\Models\Producto;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('categorias.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of categorias', function () {
    $usuario = User::factory()->create();
    Categoria::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('categorias.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/categorias/Index', false)
        ->has('categorias.data', 3)
    );
});

test('creates a categoria and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('categorias.store'), [
        'nombre' => 'Enlatados',
        'activo' => true,
    ]);

    $response->assertRedirect(route('categorias.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Categoría creada.']);
    $this->assertDatabaseHas('categorias', [
        'nombre' => 'Enlatados',
        'activo' => true,
    ]);
});

test('requires a nombre to create a categoria', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('categorias.store'), [
        'activo' => true,
    ]);

    $response->assertSessionHasErrors(['nombre']);
    $this->assertDatabaseCount('categorias', 0);
});

test('updates a categoria and redirects to the index', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create(['nombre' => 'Original', 'activo' => true]);

    $response = $this->actingAs($usuario)->put(route('categorias.update', $categoria), [
        'nombre' => 'Actualizada',
        'activo' => false,
    ]);

    $response->assertRedirect(route('categorias.index'));
    $this->assertDatabaseHas('categorias', [
        'id' => $categoria->id,
        'nombre' => 'Actualizada',
        'activo' => false,
    ]);
});

test('deletes a categoria and redirects to the index', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('categorias.destroy', $categoria));

    $response->assertRedirect(route('categorias.index'));
    $this->assertModelMissing($categoria);
});

test('does not delete a categoria that has productos associated', function () {
    $usuario = User::factory()->create();
    $categoria = Categoria::factory()->create();
    Producto::factory()->create(['categoria_id' => $categoria->id]);

    $response = $this->actingAs($usuario)->delete(route('categorias.destroy', $categoria));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se puede eliminar: tiene productos asociados.']);
    $this->assertModelExists($categoria);
});
