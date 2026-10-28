@php
    $bs = fn ($v) => 'Bs. '.number_format((float) $v, 2, ',', '.');
    $usdFmt = fn ($v) => '$'.number_format((float) $v, 2, ',', '.');
    $tasa = (float) ($cierre->tasaCambio->tasa ?? 0);
    $totalCredito = $cierre->ventasCredito->sum(fn ($v) => (float) $v->total);
    $totalCreditoBs = $cierre->ventasCredito->sum(fn ($v) => (float) $v->total * (float) ($v->tasaCambio->tasa ?? 0));
@endphp
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Cierre de caja</title>
    <style>
        .nw { white-space: nowrap; }

        /* celular */
        @media only screen and (max-width: 520px) {
            .outer { padding: 12px 6px !important; }
            .pad { padding-left: 18px !important; padding-right: 18px !important; }
            .fecha { font-size: 19px !important; }
            .hero-pad { padding: 18px 18px !important; }
            .hero-bs { font-size: 28px !important; }
            .hero-usd { font-size: 15px !important; }
            .tbl { font-size: 12px !important; }
            .tbl td, .tbl th { padding: 8px 6px !important; }
            .tbl th { font-size: 10px !important; letter-spacing: .4px !important; }
        }

        /* tema oscuro (Apple Mail, iOS, Outlook.com, Thunderbird...) */
        @media (prefers-color-scheme: dark) {
            .bg-body { background: #0a0a0a !important; }
            .card { background: #171717 !important; border-color: #2e2e2e !important; }
            .t { color: #fafafa !important; }
            .m { color: #a3a3a3 !important; }
            .m2 { color: #d4d4d4 !important; }
            .th { background: #1f1f1f !important; color: #a3a3a3 !important; border-bottom-color: #2e2e2e !important; }
            .row { border-bottom-color: #262626 !important; }
            .hero { background: #fafafa !important; }
            .hero-lbl, .hero-sub { color: #525252 !important; }
            .hero-bs { color: #171717 !important; }
            .hero-usd { color: #404040 !important; }
            .note { background: #1f1f1f !important; color: #a3a3a3 !important; }
        }
    </style>
</head>
<body class="bg-body" style="margin:0;padding:0;background:#f4f4f5;font-family:'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#171717;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="bg-body" style="background:#f4f4f5;">
<tr><td align="center" class="outer" style="padding:24px 12px;">

    <table role="presentation" width="600" cellpadding="0" cellspacing="0" class="card" style="width:100%;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e5e5;">

        {{-- encabezado --}}
        <tr>
            <td class="pad head-line" bgcolor="#ffffff" style="padding:22px 28px;background:#ffffff;background-image:linear-gradient(#ffffff,#ffffff);border-bottom:3px solid #E61C24;">
                @if ($logoPath)
                    <img src="{{ $message->embed($logoPath) }}" alt="{{ $negocio['nombre'] }}" height="56" style="display:block;margin:0 auto;height:56px;width:auto;max-width:240px;border:0;">
                @else
                    <p style="margin:0;text-align:center;font-size:20px;font-weight:700;color:#171717;">{{ $negocio['nombre'] }}</p>
                @endif
                @if ($negocio['telefono'] || $negocio['direccion'])
                    <p style="margin:10px 0 0;text-align:center;font-size:12px;line-height:1.5;color:#737373;">
                        {{ $negocio['telefono'] }}@if ($negocio['telefono'] && $negocio['direccion']) · @endif{{ $negocio['direccion'] }}
                    </p>
                @endif
            </td>
        </tr>

        {{-- título --}}
        <tr>
            <td class="pad" style="padding:26px 28px 8px;">
                <p class="m" style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#737373;font-weight:600;">Reporte de cierre de caja</p>
                <p class="t fecha" style="margin:6px 0 0;font-size:24px;font-weight:700;color:#171717;">{{ $cierre->fecha->locale('es')->translatedFormat('l, d \d\e F \d\e Y') }}</p>
                <p class="m" style="margin:8px 0 0;font-size:13px;line-height:1.5;color:#737373;">
                    Abierta por <strong class="t" style="color:#404040;">{{ $cierre->usuario->name ?? '—' }}</strong>
                    @if ($cierre->hora_apertura) a las {{ $cierre->hora_apertura->format('H:i') }}@endif
                    @if ($cierre->hora_cierre) · cerrada a las {{ $cierre->hora_cierre->format('H:i') }}@endif
                </p>
            </td>
        </tr>

        {{-- total --}}
        <tr>
            <td class="pad" style="padding:16px 28px 8px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="hero" style="background:#171717;border-radius:10px;">
                    <tr>
                        <td class="hero-pad" style="padding:22px 24px;">
                            <p class="hero-lbl" style="margin:0;font-size:11px;letter-spacing:1.5px;text-transform:uppercase;color:#a3a3a3;font-weight:600;">Total vendido</p>
                            <p class="hero-bs nw" style="margin:6px 0 0;font-size:34px;line-height:1.1;font-weight:700;color:#ffffff;">{{ $bs($cierre->total_ventas_bs) }}</p>
                            <p class="hero-usd nw" style="margin:6px 0 0;font-size:16px;color:#d4d4d4;">{{ $usdFmt($cierre->total_ventas) }}</p>
                            @if ($tasa > 0)
                                <p class="hero-sub" style="margin:12px 0 0;font-size:11px;color:#a3a3a3;">Tasa del cierre: {{ number_format($tasa, 2, ',', '.') }} Bs. por USD</p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>

        {{-- productos y membresías (los cierres anteriores a esta separación no lo traen) --}}
        @if ($cierre->total_membresias !== null)
            <tr>
                <td class="pad" style="padding:16px 28px 0;">
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="tbl" style="font-size:13px;border-collapse:collapse;">
                        <tr>
                            <td class="row t" style="padding:10px;border-bottom:1px solid #f0f0f0;">Productos</td>
                            <td align="right" class="row t nw" style="padding:10px;border-bottom:1px solid #f0f0f0;font-weight:700;white-space:nowrap;">{{ $bs((float) $cierre->total_ventas_bs - (float) $cierre->total_membresias_bs) }}</td>
                            <td align="right" class="row m2 nw" style="padding:10px;border-bottom:1px solid #f0f0f0;color:#525252;white-space:nowrap;">{{ $usdFmt((float) $cierre->total_ventas - (float) $cierre->total_membresias) }}</td>
                        </tr>
                        <tr>
                            <td class="row t" style="padding:10px;border-bottom:1px solid #f0f0f0;">Membresías</td>
                            <td align="right" class="row t nw" style="padding:10px;border-bottom:1px solid #f0f0f0;font-weight:700;white-space:nowrap;">{{ $bs($cierre->total_membresias_bs) }}</td>
                            <td align="right" class="row m2 nw" style="padding:10px;border-bottom:1px solid #f0f0f0;color:#525252;white-space:nowrap;">{{ $usdFmt($cierre->total_membresias) }}</td>
                        </tr>
                    </table>
                </td>
            </tr>
        @endif

        {{-- desglose por método --}}
        <tr>
            <td class="pad" style="padding:24px 28px 4px;">
                <p class="t" style="margin:0 0 10px;font-size:14px;font-weight:700;color:#171717;">Desglose por método de pago</p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="tbl" style="font-size:13px;border-collapse:collapse;">
                    <tr>
                        <th align="left" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">Método</th>
                        <th align="right" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">Bs</th>
                        <th align="right" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">USD</th>
                    </tr>
                    @forelse ($cierre->detalleMetodos as $detalle)
                        <tr>
                            <td class="row t" style="padding:10px;border-bottom:1px solid #f0f0f0;">{{ $detalle->metodoPago->nombre }}</td>
                            <td align="right" class="row t nw" style="padding:10px;border-bottom:1px solid #f0f0f0;font-weight:700;white-space:nowrap;">{{ $bs($detalle->monto_bs) }}</td>
                            <td align="right" class="row m2 nw" style="padding:10px;border-bottom:1px solid #f0f0f0;color:#525252;white-space:nowrap;">{{ $usdFmt($detalle->monto) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="m" style="padding:14px 10px;color:#737373;">No entró dinero a caja este día.</td></tr>
                    @endforelse
                </table>
            </td>
        </tr>

        {{-- ventas a crédito --}}
        <tr>
            <td class="pad" style="padding:24px 28px 8px;">
                <p class="t" style="margin:0 0 10px;font-size:14px;font-weight:700;color:#171717;">Ventas a crédito del día</p>
                @if ($cierre->ventasCredito->isEmpty())
                    <p class="m" style="margin:0;font-size:13px;color:#737373;">No hubo ventas a crédito este día.</p>
                @else
                    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="tbl" style="font-size:13px;border-collapse:collapse;">
                        <tr>
                            <th align="left" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">Cliente</th>
                            <th align="right" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">Bs</th>
                            <th align="right" class="th" style="background:#fafafa;padding:9px 10px;font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:#737373;border-bottom:1px solid #e5e5e5;">USD</th>
                        </tr>
                        @foreach ($cierre->ventasCredito as $venta)
                            <tr>
                                <td class="row t" style="padding:10px;border-bottom:1px solid #f0f0f0;">
                                    {{ $venta->cliente->nombre ?? '—' }}<br>
                                    <span class="m" style="font-size:11px;color:#737373;">{{ $venta->numero_venta }}</span>
                                </td>
                                <td align="right" class="row t nw" style="padding:10px;border-bottom:1px solid #f0f0f0;font-weight:700;white-space:nowrap;">{{ $bs((float) $venta->total * (float) ($venta->tasaCambio->tasa ?? 0)) }}</td>
                                <td align="right" class="row m2 nw" style="padding:10px;border-bottom:1px solid #f0f0f0;color:#525252;white-space:nowrap;">{{ $usdFmt($venta->total) }}</td>
                            </tr>
                        @endforeach
                        <tr>
                            <td class="t" style="padding:10px;font-weight:700;">Total a crédito</td>
                            <td align="right" class="t nw" style="padding:10px;font-weight:700;white-space:nowrap;">{{ $bs($totalCreditoBs) }}</td>
                            <td align="right" class="m2 nw" style="padding:10px;color:#525252;white-space:nowrap;">{{ $usdFmt($totalCredito) }}</td>
                        </tr>
                    </table>
                @endif
            </td>
        </tr>

        {{-- nota --}}
        <tr>
            <td class="pad" style="padding:16px 28px 0;">
                <p class="note" style="margin:0;padding:12px 14px;background:#fafafa;border-radius:8px;font-size:12px;line-height:1.5;color:#737373;">
                    El total vendido incluye las ventas a crédito; el desglose por método es el dinero que efectivamente entró a caja ese día.
                </p>
            </td>
        </tr>

        {{-- pie --}}
        <tr>
            <td class="pad m" style="padding:24px 28px;font-size:11px;line-height:1.6;color:#a3a3a3;text-align:center;">
                Este correo se generó automáticamente al cerrar la caja.
            </td>
        </tr>
    </table>

</td></tr>
</table>
</body>
</html>
