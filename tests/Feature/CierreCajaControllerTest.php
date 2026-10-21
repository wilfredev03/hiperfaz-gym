<?php

use App\Mail\CierreCajaCerrado;
use App\Models\CierreCaja;
use App\Models\Cliente;
use App\Models\MetodoPago;
use App\Models\TasaCambio;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use Illuminate\Support\Facades\Mail;

test('cierra la caja calculando el total y el desglose por metodo, en usd y su equivalente en bs', function () {
    $usuario = User::factory()->create();
    $tasa = TasaCambio::factory()->create(['tasa' => 40]);
    $cierre = CierreCaja::factory()->create(['estado' => 'abierto', 'tasa_cambio_id' => $tasa->id]);
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();
    $pagoMovil = MetodoPago::factory()->pagoMovil()->create();

    $venta = Venta::factory()->for($cierre, 'cierreCaja')->create(['total' => 45]);
    VentaPago::factory()->create([
        'venta_id' => $venta->id,
        'cierre_caja_id' => $cierre->id,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 30,
    ]);
    VentaPago::factory()->create([
        'venta_id' => $venta->id,
        'cierre_caja_id' => $cierre->id,
        'metodo_pago_id' => $pagoMovil->id,
        'monto' => 15,
    ]);

    $response = $this->actingAs($usuario)->postJson(route('cierres_caja.cerrar', $cierre));

    $response->assertOk();
    $response->assertJsonPath('estado', 'cerrado');
    $response->assertJsonPath('total_ventas', '45.0000');
    $response->assertJsonPath('total_ventas_bs', '1800.0000');

    $this->assertDatabaseHas('cierre_caja_metodos', [
        'cierre_caja_id' => $cierre->id,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 30,
        'monto_bs' => 1200,
    ]);
    $this->assertDatabaseHas('cierre_caja_metodos', [
        'cierre_caja_id' => $cierre->id,
        'metodo_pago_id' => $pagoMovil->id,
        'monto' => 15,
        'monto_bs' => 600,
    ]);
});

test('un abono de hoy a una venta a credito de dias anteriores suma al desglose del cierre de hoy', function () {
    $usuario = User::factory()->create();
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();
    $cliente = Cliente::factory()->create();

    // el cierre de ayer ya está cerrado; la venta a crédito se facturó ese día
    $cierreAyer = CierreCaja::factory()->cerrado()->create([
        'fecha' => now()->subDay()->toDateString(),
    ]);
    $ventaCredito = Venta::factory()->credito()->create([
        'cliente_id' => $cliente->id,
        'cierre_caja_id' => $cierreAyer->id,
        'fecha' => now()->subDay()->toDateString(),
        'total' => 50,
    ]);

    // hoy se abre un cierre nuevo con su propia tasa y el cliente abona la venta de ayer
    $tasaHoy = TasaCambio::factory()->create(['tasa' => 50]);
    $cierreHoy = CierreCaja::factory()->create(['estado' => 'abierto', 'tasa_cambio_id' => $tasaHoy->id]);
    VentaPago::factory()->create([
        'venta_id' => $ventaCredito->id,
        'cierre_caja_id' => $cierreHoy->id,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 50,
        'fecha_pago' => now(),
    ]);

    $response = $this->actingAs($usuario)->postJson(route('cierres_caja.cerrar', $cierreHoy));

    $response->assertOk();
    // el abono no tiene ventas propias de hoy, así que el total de ventas
    // facturadas hoy es 0 aunque el desglose de pagos reciba el abono
    $response->assertJsonPath('total_ventas', '0.0000');
    $response->assertJsonPath('total_ventas_bs', '0.0000');

    // el cierre de ayer, ya cerrado, no debe verse afectado por el abono de hoy
    expect($cierreAyer->fresh()->total_ventas)->not->toBe('50.0000');

    // el equivalente en Bs del abono usa la tasa de HOY (cuando entró el
    // dinero), nunca la tasa con la que se facturó la venta de ayer
    $this->assertDatabaseHas('cierre_caja_metodos', [
        'cierre_caja_id' => $cierreHoy->id,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 50,
        'monto_bs' => 2500,
    ]);
});

test('rechaza cerrar un cierre de caja que ya esta cerrado', function () {
    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->cerrado()->create();

    $response = $this->actingAs($usuario)->postJson(route('cierres_caja.cerrar', $cierre));

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['cierre_caja']);
});

test('envia el reporte por correo al cerrar la caja cuando hay un destinatario configurado', function () {
    config(['services.cierre_caja.email_notificacion' => 'dueno@example.com']);
    Mail::fake();

    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create(['estado' => 'abierto']);

    $response = $this->actingAs($usuario)->postJson(route('cierres_caja.cerrar', $cierre));

    $response->assertOk();
    $response->assertJsonPath('correo_enviado_at', fn ($valor) => $valor !== null);

    Mail::assertSent(CierreCajaCerrado::class, function (CierreCajaCerrado $mail) use ($cierre) {
        return $mail->hasTo('dueno@example.com') && $mail->cierreCaja->id === $cierre->id;
    });

    expect($cierre->fresh()->correo_enviado_at)->not->toBeNull();
});

test('no intenta enviar correo al cerrar la caja si no hay destinatario configurado', function () {
    config(['services.cierre_caja.email_notificacion' => null]);
    Mail::fake();

    $usuario = User::factory()->create();
    $cierre = CierreCaja::factory()->create(['estado' => 'abierto']);

    $response = $this->actingAs($usuario)->postJson(route('cierres_caja.cerrar', $cierre));

    $response->assertOk();
    $response->assertJsonPath('correo_enviado_at', null);
    Mail::assertNothingSent();
});

test('muestra la pantalla de cierre de caja con el cierre abierto, su resumen en vivo (usd y bs) y el historial', function () {
    $usuario = User::factory()->create();
    $tasa = TasaCambio::factory()->create(['tasa' => 40]);
    $abierto = CierreCaja::factory()->create([
        'estado' => 'abierto',
        'usuario_id' => $usuario->id,
        'tasa_cambio_id' => $tasa->id,
    ]);
    $cerrado = CierreCaja::factory()->cerrado()->create();
    $efectivo = MetodoPago::factory()->efectivoUsd()->create();

    $venta = Venta::factory()->for($abierto, 'cierreCaja')->create(['total' => 20]);
    VentaPago::factory()->create([
        'venta_id' => $venta->id,
        'cierre_caja_id' => $abierto->id,
        'metodo_pago_id' => $efectivo->id,
        'monto' => 20,
    ]);

    $response = $this->actingAs($usuario)->get(route('cierres_caja.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('cierres_caja/Index', false)
        ->where('cierreAbierto.id', $abierto->id)
        ->where('resumenAbierto.total_ventas', 20)
        ->where('resumenAbierto.total_ventas_bs', 800)
        ->has('historial.data', 1)
        ->where('historial.data.0.id', $cerrado->id)
    );
});

test('la pantalla de cierre de caja no muestra cierre abierto cuando no hay ninguno', function () {
    $usuario = User::factory()->create();

    $response = $this->actingAs($usuario)->get(route('cierres_caja.index'));

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('cierres_caja/Index', false)
        ->where('cierreAbierto', null)
        ->where('resumenAbierto', null)
    );
});
