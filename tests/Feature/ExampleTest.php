<?php

use App\Models\User;

test('la raíz redirige al login si no hay sesión', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('la raíz redirige a nueva venta si hay sesión', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('ventas.create'));
});
