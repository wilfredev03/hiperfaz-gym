<?php

use App\Models\Producto;
use App\Models\UnidadMedida;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('unidades_medida.index'));

    $response->assertRedirect(route('login'));
});

test('displays a list of unidades de medida', function () {
    $usuario = User::factory()->create();
    UnidadMedida::factory()->count(3)->create();

    $response = $this->actingAs($usuario)->get(route('unidades_medida.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('settings/unidades_medida/Index', false)
        ->has('unidadesMedida.data', 3)
    );
});

test('creates a unidad de medida and redirects to the index', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('unidades_medida.store'), [
        'nombre' => 'Docena',
        'abreviatura' => 'dcna',
    ]);

    $response->assertRedirect(route('unidades_medida.index'));
    $response->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Unidad de medida creada.']);
    $this->assertDatabaseHas('unidades_medida', [
        'nombre' => 'Docena',
        'abreviatura' => 'dcna',
    ]);
});

test('requires a nombre and abreviatura to create a unidad de medida', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->post(route('unidades_medida.store'), []);

    $response->assertSessionHasErrors(['nombre', 'abreviatura']);
    $this->assertDatabaseCount('unidades_medida', 0);
});

test('updates a unidad de medida and redirects to the index', function () {
    $usuario = User::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create(['nombre' => 'Original', 'abreviatura' => 'or']);

    $response = $this->actingAs($usuario)->put(route('unidades_medida.update', $unidadMedida), [
        'nombre' => 'Actualizada',
        'abreviatura' => 'act',
    ]);

    $response->assertRedirect(route('unidades_medida.index'));
    $this->assertDatabaseHas('unidades_medida', [
        'id' => $unidadMedida->id,
        'nombre' => 'Actualizada',
        'abreviatura' => 'act',
    ]);
});

test('deletes a unidad de medida and redirects to the index', function () {
    $usuario = User::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();

    $response = $this->actingAs($usuario)->delete(route('unidades_medida.destroy', $unidadMedida));

    $response->assertRedirect(route('unidades_medida.index'));
    $this->assertModelMissing($unidadMedida);
});

test('does not delete a unidad de medida that has productos associated', function () {
    $usuario = User::factory()->create();
    $unidadMedida = UnidadMedida::factory()->create();
    Producto::factory()->create(['unidad_medida_id' => $unidadMedida->id]);

    $response = $this->actingAs($usuario)->delete(route('unidades_medida.destroy', $unidadMedida));

    $response->assertRedirect();
    $response->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'No se puede eliminar: tiene productos asociados.']);
    $this->assertModelExists($unidadMedida);
});
