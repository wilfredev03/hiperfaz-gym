<?php

use App\Models\Cliente;
use App\Models\Plan;
use App\Models\Suscripcion;
use App\Models\User;

function socioQueVenceEn(int $dias, array $socio = []): Cliente
{
    $cliente = Cliente::factory()->create($socio);

    Suscripcion::factory()->create([
        'cliente_id' => $cliente->id,
        'plan_id' => test()->plan->id,
        'fecha_inicio' => now()->addDays($dias)->subDays(29)->toDateString(),
        'fecha_vencimiento' => now()->addDays($dias)->toDateString(),
        'estado' => $dias < 0 ? 'vencida' : 'activa',
    ]);

    return $cliente;
}

beforeEach(function () {
    $this->usuario = User::factory()->vendedor()->create();
    // un solo plan compartido: la fábrica de planes crea un producto único por llamada
    $this->plan = Plan::factory()->create();
});

test('por vencer lista a quienes vencen en la ventana, el más próximo primero', function () {
    $manana = socioQueVenceEn(1);
    $hoy = socioQueVenceEn(0);
    $enSiete = socioQueVenceEn(7);
    socioQueVenceEn(8);   // aún lejos
    socioQueVenceEn(-3);  // ya vencido

    $this->actingAs($this->usuario)->get(route('vencimientos.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('vencimientos/Index', false)
            ->where('vista', 'por_vencer')
            ->where('resumen.por_vencer', 3)
            ->where('resumen.vencidos', 1)
            ->where('socios.data', fn ($filas) => collect($filas)->pluck('id')->all() === [$hoy->id, $manana->id, $enSiete->id]));
});

test('vencidos lista a quienes vencieron hace poco, el más reciente primero', function () {
    $ayer = socioQueVenceEn(-1);
    $haceDiez = socioQueVenceEn(-10);
    socioQueVenceEn(-31); // hace demasiado
    socioQueVenceEn(2);   // todavía vigente

    $this->actingAs($this->usuario)->get(route('vencimientos.index', ['vista' => 'vencidos']))
        ->assertInertia(fn ($page) => $page
            ->where('vista', 'vencidos')
            ->where('socios.data', fn ($filas) => collect($filas)->pluck('id')->all() === [$ayer->id, $haceDiez->id])
            ->where('socios.data.0.dias_vencida', 1));
});

test('quien renovó deja de aparecer', function () {
    $socio = socioQueVenceEn(-2);

    Suscripcion::factory()->create([
        'cliente_id' => $socio->id,
        'plan_id' => $this->plan->id,
        'fecha_inicio' => now()->toDateString(),
        'fecha_vencimiento' => now()->addDays(29)->toDateString(),
    ]);

    $this->actingAs($this->usuario)->get(route('vencimientos.index', ['vista' => 'vencidos']))
        ->assertInertia(fn ($page) => $page->has('socios.data', 0));
});

test('los socios inactivos y los de membresía cancelada no aparecen', function () {
    socioQueVenceEn(2, ['activo' => false]);

    $cancelado = Cliente::factory()->create();
    Suscripcion::factory()->cancelada()->create([
        'cliente_id' => $cancelado->id,
        'plan_id' => $this->plan->id,
        'fecha_vencimiento' => now()->addDays(2)->toDateString(),
    ]);

    $this->actingAs($this->usuario)->get(route('vencimientos.index'))
        ->assertInertia(fn ($page) => $page->has('socios.data', 0));
});

test('una vista desconocida cae en por vencer', function () {
    $this->actingAs($this->usuario)->get(route('vencimientos.index', ['vista' => 'cualquiera']))
        ->assertInertia(fn ($page) => $page->where('vista', 'por_vencer'));
});

test('requiere sesión', function () {
    $this->get(route('vencimientos.index'))->assertRedirect(route('login'));
});
