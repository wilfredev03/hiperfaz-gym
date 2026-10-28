<?php

use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CategoriaController;
use App\Http\Controllers\CierreCajaController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\MembresiaVendidaController;
use App\Http\Controllers\MetodoPagoController;
use App\Http\Controllers\OrdenCompraController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProductoController;
use App\Http\Controllers\ProveedorController;
use App\Http\Controllers\RecordatorioController;
use App\Http\Controllers\RecordatorioPlantillaController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\SocioController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\SuscripcionController;
use App\Http\Controllers\TasaCambioController;
use App\Http\Controllers\UnidadMedidaController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VencimientoController;
use App\Http\Controllers\VentaController;
use Illuminate\Support\Facades\Route;

// no hay página de bienvenida: el sistema arranca en el login (o en la venta si ya hay sesión)
Route::get('/', fn () => auth()->check() ? to_route('ventas.create') : to_route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');

    Route::get('ventas', [VentaController::class, 'index'])->name('ventas.index');
    Route::get('ventas/create', [VentaController::class, 'create'])->name('ventas.create');
    Route::post('ventas', [VentaController::class, 'store'])->name('ventas.store');
    Route::get('ventas/{venta}', [VentaController::class, 'show'])->name('ventas.show');
    Route::post('ventas/{venta}/abonar', [VentaController::class, 'abonar'])->name('ventas.abonar');

    Route::get('cierres_caja', [CierreCajaController::class, 'index'])->name('cierres_caja.index');
    Route::post('cierres_caja/{cierre_caja}/cerrar', [CierreCajaController::class, 'cerrarCaja'])->name('cierres_caja.cerrar');

    Route::post('clientes/{cliente}/abonar', [ClienteController::class, 'abonar'])->name('clientes.abonar');
    Route::resource('clientes', ClienteController::class)->except(['create', 'show', 'edit']);

    // socios (clientes con membresía) y recepción: abiertos a cualquier usuario
    Route::post('socios/{socio}/membresia', [SocioController::class, 'membresia'])->name('socios.membresia');
    Route::put('suscripciones/{suscripcion}/fechas', [SuscripcionController::class, 'corregir'])->name('suscripciones.corregir');
    Route::resource('socios', SocioController::class)
        ->parameters(['socios' => 'socio'])
        ->except(['edit', 'destroy']);
    Route::get('vencimientos', [VencimientoController::class, 'index'])->name('vencimientos.index');
    Route::get('membresias_vendidas', [MembresiaVendidaController::class, 'index'])->name('membresias_vendidas.index');
    Route::get('membresias_vendidas/reporte', [MembresiaVendidaController::class, 'reporte'])->name('membresias_vendidas.reporte');
    Route::get('asistencias', [AsistenciaController::class, 'index'])->name('asistencias.index');
    Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');

    Route::post('tasas_cambio/cargar_automatica', [TasaCambioController::class, 'cargarAutomatica'])
        ->name('tasas_cambio.cargar_automatica');
    Route::resource('tasas_cambio', TasaCambioController::class)
        ->parameters(['tasas_cambio' => 'tasa_cambio'])
        ->only(['index', 'store']);

    // catálogo, compras, reportes, configuración y usuarios: solo el admin
    Route::middleware('rol:admin')->group(function () {
        Route::post('ventas/{venta}/anular', [VentaController::class, 'anular'])->name('ventas.anular');

        Route::get('ordenes_compra', [OrdenCompraController::class, 'index'])->name('ordenes_compra.index');
        Route::get('ordenes_compra/create', [OrdenCompraController::class, 'create'])->name('ordenes_compra.create');
        Route::post('ordenes_compra', [OrdenCompraController::class, 'store'])->name('ordenes_compra.store');
        Route::get('ordenes_compra/{orden_compra}', [OrdenCompraController::class, 'show'])->name('ordenes_compra.show');
        Route::post('ordenes_compra/{orden_compra}/anular', [OrdenCompraController::class, 'anular'])->name('ordenes_compra.anular');

        // recordatorios de membresías por WhatsApp (bot de Baileys, ver whatsapp-bot/)
        Route::prefix('recordatorios')->name('recordatorios.')->group(function () {
            Route::get('/', [RecordatorioController::class, 'index'])->name('index');
            Route::get('bot', [RecordatorioController::class, 'bot'])->name('bot');
            Route::post('bot/desconectar', [RecordatorioController::class, 'desconectarBot'])->name('bot.desconectar');
            Route::post('bot/reconectar', [RecordatorioController::class, 'reconectarBot'])->name('bot.reconectar');
            Route::post('iniciar', [RecordatorioController::class, 'iniciar'])->name('iniciar');
            Route::post('detener', [RecordatorioController::class, 'detener'])->name('detener');
            Route::post('proponer', [RecordatorioController::class, 'proponer'])->name('proponer');
            Route::put('ajustes', [RecordatorioController::class, 'ajustes'])->name('ajustes');
            Route::post('prueba', [RecordatorioController::class, 'prueba'])->name('prueba');
            Route::post('{recordatorio}/cancelar', [RecordatorioController::class, 'cancelar'])->name('cancelar');
            Route::post('{recordatorio}/reintentar', [RecordatorioController::class, 'reintentar'])->name('reintentar');
        });
        Route::resource('recordatorio_plantillas', RecordatorioPlantillaController::class)
            ->parameters(['recordatorio_plantillas' => 'plantilla'])
            ->only(['store', 'update', 'destroy']);

        Route::prefix('reportes')->name('reportes.')->group(function () {
            Route::get('ventas_por_periodo', [ReporteController::class, 'ventasPorPeriodo'])->name('ventas_por_periodo');
            Route::get('desglose_metodo_pago', [ReporteController::class, 'desglosePorMetodoPago'])->name('desglose_metodo_pago');
            Route::get('margen_ganancia', [ReporteController::class, 'margenGanancia'])->name('margen_ganancia');
            Route::get('productos_mas_vendidos', [ReporteController::class, 'productosMasVendidos'])->name('productos_mas_vendidos');
        });

        Route::resource('planes', PlanController::class)
            ->parameters(['planes' => 'plan'])
            ->only(['index', 'store', 'update']);
        Route::resource('categorias', CategoriaController::class)->except(['create', 'edit', 'show']);
        Route::resource('unidades_medida', UnidadMedidaController::class)
            ->parameters(['unidades_medida' => 'unidad_medida'])
            ->except(['create', 'edit', 'show']);
        Route::resource('productos', ProductoController::class)->except(['show', 'edit']);
        Route::patch('productos/{producto}/toggle_activo', [ProductoController::class, 'toggleActivo'])
            ->name('productos.toggle_activo');
        Route::resource('proveedores', ProveedorController::class)
            ->parameters(['proveedores' => 'proveedor'])
            ->except(['create', 'edit', 'show']);
        Route::resource('metodos_pago', MetodoPagoController::class)
            ->parameters(['metodos_pago' => 'metodo_pago'])
            ->except(['create', 'edit', 'show']);
        Route::resource('sucursales', SucursalController::class)
            ->parameters(['sucursales' => 'sucursal']);
        Route::resource('usuarios', UsuarioController::class)
            ->parameters(['usuarios' => 'usuario'])
            ->except(['show']);
    });
});

require __DIR__.'/settings.php';
