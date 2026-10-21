<?php

use App\Enums\Rol;
use App\Models\User;

test('el registro público ya no existe', function () {
    $this->get('/register')->assertNotFound();
    $this->post('/register', [])->assertStatus(404);
});

test('un vendedor no puede entrar a la gestión de usuarios ni al catálogo', function () {
    $vendedor = User::factory()->vendedor()->create();

    $this->actingAs($vendedor)->get(route('usuarios.index'))->assertForbidden();
    $this->actingAs($vendedor)->post(route('usuarios.store'), [])->assertForbidden();
    $this->actingAs($vendedor)->get(route('productos.index'))->assertForbidden();
    $this->actingAs($vendedor)->get(route('reportes.ventas_por_periodo'))->assertForbidden();
});

test('un vendedor sí puede vender y ver clientes y cierre de caja', function () {
    $vendedor = User::factory()->vendedor()->create();

    $this->actingAs($vendedor)->get(route('ventas.index'))->assertOk();
    $this->actingAs($vendedor)->get(route('clientes.index'))->assertOk();
    $this->actingAs($vendedor)->get(route('cierres_caja.index'))->assertOk();
});

test('el admin crea un usuario con usuario sin formato de correo, en minúsculas', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('usuarios.store'), [
            'name' => 'María Pérez',
            'email' => 'Maria.Perez',
            'rol' => 'vendedor',
            'password' => 'abcd',
        ])
        ->assertRedirect(route('usuarios.index'));

    $usuario = User::where('email', 'maria.perez')->firstOrFail();
    expect($usuario->rol)->toBe(Rol::Vendedor);
});

test('la contraseña puede tener 4 caracteres pero no 3', function () {
    $admin = User::factory()->create();
    $datos = ['name' => 'Ana', 'email' => 'ana', 'rol' => 'vendedor'];

    $this->actingAs($admin)->post(route('usuarios.store'), [...$datos, 'password' => 'abc'])
        ->assertSessionHasErrors('password');

    $this->actingAs($admin)->post(route('usuarios.store'), [...$datos, 'password' => '1234'])
        ->assertSessionDoesntHaveErrors();
});

test('el usuario es único sin distinguir mayúsculas', function () {
    User::factory()->create(['email' => 'carlos']);

    $this->actingAs(User::factory()->create())
        ->post(route('usuarios.store'), [
            'name' => 'Otro', 'email' => 'CARLOS', 'rol' => 'vendedor', 'password' => '1234',
        ])
        ->assertSessionHasErrors('email');
});

test('se puede iniciar sesión con el usuario en mayúsculas', function () {
    User::factory()->create(['email' => 'luis', 'password' => '1234']);

    $this->post(route('login.store'), ['email' => 'LUIS', 'password' => '1234']);

    $this->assertAuthenticated();
});

test('editar sin contraseña la conserva', function () {
    $admin = User::factory()->create();
    $vendedor = User::factory()->vendedor()->create(['password' => '1234']);

    $this->actingAs($admin)->put(route('usuarios.update', $vendedor), [
        'name' => 'Nuevo nombre', 'email' => $vendedor->email, 'rol' => 'vendedor', 'password' => '',
    ])->assertSessionDoesntHaveErrors();

    expect($vendedor->fresh()->name)->toBe('Nuevo nombre')
        ->and(Hash::check('1234', $vendedor->fresh()->password))->toBeTrue();
});

test('no se puede quitar el rol ni eliminar al último admin', function () {
    $admin = User::factory()->create();

    $this->actingAs($admin)->put(route('usuarios.update', $admin), [
        'name' => $admin->name, 'email' => $admin->email, 'rol' => 'vendedor',
    ]);
    expect($admin->fresh()->rol)->toBe(Rol::Admin);

    $this->actingAs($admin)->delete(route('usuarios.destroy', $admin));
    expect(User::find($admin->id))->not->toBeNull();
});

test('el admin puede eliminar a un vendedor', function () {
    $vendedor = User::factory()->vendedor()->create();

    $this->actingAs(User::factory()->create())->delete(route('usuarios.destroy', $vendedor));

    expect(User::find($vendedor->id))->toBeNull();
});
