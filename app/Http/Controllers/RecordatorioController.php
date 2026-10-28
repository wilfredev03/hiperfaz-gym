<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordatorioAjusteRequest;
use App\Models\Recordatorio;
use App\Models\RecordatorioAjuste;
use App\Models\RecordatorioPlantilla;
use App\Models\WhatsAppEvento;
use App\Services\Recordatorios\GeneradorMensaje;
use App\Services\Recordatorios\RecordatorioService;
use App\Services\Recordatorios\SincronizadorEventosBot;
use App\Services\Recordatorios\TelefonoVenezolano;
use App\Services\Recordatorios\WhatsAppCliente;
use App\Services\Recordatorios\WhatsAppException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecordatorioController extends Controller
{
    private const FILTROS_HISTORIAL = ['enviado', 'fallido', 'cancelado'];

    /**
     * Dashboard de recordatorios por WhatsApp: interruptor del bot, cola de
     * pendientes, historial de lo enviado, plantillas y ajustes de ritmo. El
     * estado de la sesión de WhatsApp (QR, conectado) no va aquí: la página lo
     * consulta aparte (`recordatorios.bot`) para refrescarlo sin recargar.
     */
    public function index(Request $request, SincronizadorEventosBot $eventosBot): Response
    {
        $ajuste = RecordatorioAjuste::actual();
        $filtro = in_array($request->query('estado'), self::FILTROS_HISTORIAL, true) ? $request->query('estado') : null;
        $hoy = now()->startOfDay();

        $historial = Recordatorio::query()
            ->with('cliente:id,nombre')
            ->whereIn('estado', $filtro ? [$filtro] : self::FILTROS_HISTORIAL)
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Recordatorio $r) => $this->fila($r));

        return Inertia::render('recordatorios/Index', [
            'ajustes' => $ajuste->only([
                'activo', 'hora_inicio', 'hora_fin', 'espera_min_seg', 'espera_max_seg',
                'mensajes_descanso_min', 'mensajes_descanso_max', 'descanso_min', 'descanso_max',
                'dias_aviso', 'duracion_minima_dias',
            ]),
            'motor' => [
                'dentro_de_horario' => $ajuste->dentroDeHorario(),
                'proximo_envio_at' => $ajuste->proximo_envio_at?->isFuture() ? $ajuste->proximo_envio_at->toIso8601String() : null,
                'descanso_hasta' => $ajuste->descanso_hasta?->isFuture() ? $ajuste->descanso_hasta->toIso8601String() : null,
            ],
            'resumen' => [
                'pendientes' => Recordatorio::where('estado', 'pendiente')->count(),
                'enviados_hoy' => Recordatorio::where('estado', 'enviado')->where('enviado_en', '>=', $hoy)->count(),
                'fallidos' => Recordatorio::where('estado', 'fallido')->where('updated_at', '>=', $hoy)->count(),
                'cancelados_hoy' => Recordatorio::where('estado', 'cancelado')->where('updated_at', '>=', $hoy)->count(),
            ],
            'cola' => Recordatorio::query()
                ->with('cliente:id,nombre')
                ->whereIn('estado', ['pendiente', 'enviando'])
                ->orderByRaw("tipo = 'vence_hoy' desc")
                ->orderBy('id')
                ->limit(200)
                ->get()
                ->map(fn (Recordatorio $r) => $this->fila($r)),
            'historial' => $historial,
            'filtro' => $filtro,
            // en un closure para que solo consulte al bot cuando la página lo pide
            // (carga completa o recarga parcial de `conexiones` al cambiar el estado)
            'conexiones' => function () use ($eventosBot) {
                $eventosBot->sincronizar();

                return $this->conexiones();
            },
            'plantillas' => RecordatorioPlantilla::orderBy('tipo')->orderBy('id')->get(),
            'variables' => GeneradorMensaje::VARIABLES,
        ]);
    }

    /** Estado de la sesión de WhatsApp (conectado, QR por escanear...). Lo consulta la página cada pocos segundos. */
    public function bot(WhatsAppCliente $bot): JsonResponse
    {
        return response()->json($bot->estado());
    }

    public function desconectarBot(WhatsAppCliente $bot): RedirectResponse
    {
        RecordatorioAjuste::actual()->update(['activo' => false]);

        $cerrada = $bot->desconectar();

        if ($cerrada) {
            // el bot no reporta los cierres pedidos desde el panel: se anotan aquí, con quién fue
            WhatsAppEvento::create([
                'tipo' => 'cierre_manual',
                'detalle' => 'Sesión cerrada desde el panel para vincular otro número.',
                'usuario_id' => auth()->id(),
                'ocurrido_en' => now(),
            ]);
        }

        $this->avisar($cerrada, 'Sesión de WhatsApp cerrada. Escanea el nuevo QR para vincular un número.', 'No se pudo cerrar la sesión: el servicio de WhatsApp no responde.');

        return back();
    }

    /**
     * Botón "Generar nuevo QR": cuando el QR expiró sin escanearse, o la sesión
     * quedó caída, descarta el intento y arranca uno limpio.
     */
    public function reconectarBot(WhatsAppCliente $bot): RedirectResponse
    {
        $ok = $bot->reconectar();

        if ($ok) {
            WhatsAppEvento::create([
                'tipo' => 'reconexion_manual',
                'detalle' => 'Se pidió un QR nuevo desde el panel.',
                'usuario_id' => auth()->id(),
                'ocurrido_en' => now(),
            ]);
        }

        $this->avisar($ok, 'Generando un QR nuevo...', 'No se pudo reconectar: el servicio de WhatsApp no responde o ya está conectado.');

        return back();
    }

    public function iniciar(): RedirectResponse
    {
        RecordatorioAjuste::actual()->update(['activo' => true]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Envíos iniciados. Se enviará dentro del horario de trabajo.')]);

        return back();
    }

    public function detener(): RedirectResponse
    {
        RecordatorioAjuste::actual()->update(['activo' => false]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Envíos detenidos.')]);

        return back();
    }

    /** Arma la cola de hoy ahora mismo (el cron también lo hace cada mañana). */
    public function proponer(RecordatorioService $recordatorios): RedirectResponse
    {
        $r = $recordatorios->proponer();

        $detalle = collect([
            $r['sin_telefono'] ? "{$r['sin_telefono']} sin teléfono válido" : null,
            $r['sin_plantilla'] ? "{$r['sin_plantilla']} sin plantilla activa" : null,
        ])->filter()->implode(', ');

        Inertia::flash('toast', [
            'type' => $r['creados'] > 0 ? 'success' : 'info',
            'message' => __(':n avisos agregados a la cola.', ['n' => $r['creados']]).($detalle ? " ({$detalle})" : ''),
        ]);

        return back();
    }

    public function ajustes(RecordatorioAjusteRequest $request): RedirectResponse
    {
        RecordatorioAjuste::actual()->update([
            ...$request->validated(),
            // los límites de descanso cambiaron: que el motor sortee la meta de nuevo
            'mensajes_para_descanso' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ajustes guardados.')]);

        return back();
    }

    public function cancelar(Recordatorio $recordatorio): RedirectResponse
    {
        if ($recordatorio->estado !== 'pendiente') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Solo se puede cancelar un aviso pendiente.')]);

            return back();
        }

        $recordatorio->update(['estado' => 'cancelado', 'detalle' => 'Cancelado manualmente.']);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Aviso cancelado.')]);

        return back();
    }

    public function reintentar(Recordatorio $recordatorio): RedirectResponse
    {
        if ($recordatorio->estado !== 'fallido') {
            Inertia::flash('toast', ['type' => 'error', 'message' => __('Solo se puede reintentar un aviso fallido.')]);

            return back();
        }

        $recordatorio->update([
            'estado' => 'pendiente',
            'intentos' => 0,
            'fecha_aviso' => today()->toDateString(),
            'detalle' => null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Aviso devuelto a la cola.')]);

        return back();
    }

    /** Manda un mensaje de prueba ya, fuera de la cola, para comprobar que el bot funciona. */
    public function prueba(Request $request, WhatsAppCliente $bot): RedirectResponse
    {
        $datos = $request->validate(['telefono' => ['required', 'string', 'max:30']]);

        $telefono = TelefonoVenezolano::normalizar($datos['telefono']);

        if (! $telefono) {
            return back()->withErrors(['telefono' => 'Escribe un celular venezolano válido (ej. 0414-1234567).']);
        }

        try {
            $bot->enviar($telefono, 'Mensaje de prueba de '.config('app.name').': el bot de WhatsApp funciona correctamente.');
            Inertia::flash('toast', ['type' => 'success', 'message' => __('Mensaje de prueba enviado.')]);
        } catch (WhatsAppException $e) {
            Inertia::flash('toast', ['type' => 'error', 'message' => $e->getMessage()]);
        }

        return back();
    }

    /**
     * Historial de la sesión de WhatsApp: los últimos eventos y un resumen del día.
     *
     * @return array{eventos: list<array<string, mixed>>, resumen: array<string, mixed>}
     */
    private function conexiones(): array
    {
        $hoy = now()->startOfDay();

        return [
            'eventos' => WhatsAppEvento::query()
                ->with('usuario:id,name')
                ->orderByDesc('ocurrido_en')
                ->orderByDesc('id')
                ->limit(60)
                ->get()
                ->map(fn (WhatsAppEvento $e) => [
                    'id' => $e->id,
                    'tipo' => $e->tipo,
                    'detalle' => $e->detalle,
                    'numero' => $e->numero,
                    'usuario' => $e->usuario?->name,
                    'ocurrido_en' => $e->ocurrido_en->toIso8601String(),
                ])
                ->all(),
            'resumen' => [
                'caidas_hoy' => WhatsAppEvento::where('tipo', 'conexion_perdida')->where('ocurrido_en', '>=', $hoy)->count(),
                'reconexiones_hoy' => WhatsAppEvento::where('tipo', 'reconectado')->where('ocurrido_en', '>=', $hoy)->count(),
                'ultima_conexion' => WhatsAppEvento::whereIn('tipo', ['conectado', 'reconectado'])
                    ->latest('ocurrido_en')
                    ->first()?->ocurrido_en->toIso8601String(),
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function fila(Recordatorio $r): array
    {
        return [
            'id' => $r->id,
            'cliente' => $r->cliente?->nombre,
            'cliente_id' => $r->cliente_id,
            'telefono' => $r->telefono,
            'tipo' => $r->tipo,
            'mensaje' => $r->mensaje,
            'estado' => $r->estado,
            'intentos' => $r->intentos,
            'detalle' => $r->detalle,
            'enviado_en' => $r->enviado_en?->toIso8601String(),
            'actualizado_en' => $r->updated_at?->toIso8601String(),
        ];
    }

    private function avisar(bool $ok, string $exito, string $error): void
    {
        Inertia::flash('toast', ['type' => $ok ? 'success' : 'error', 'message' => $ok ? $exito : $error]);
    }
}
