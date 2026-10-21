import type { EstadoVenta } from '@/lib/ventas';

export interface ClienteBase {
    id: number;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    saldo_pendiente: string;
    activo: boolean;
}

// en la lista cada cliente trae sus ventas por cobrar (de la más antigua a la más reciente)
export interface ClienteLista extends ClienteBase {
    ventas_pendientes: VentaCabecera[];
}

export interface ClienteFicha extends ClienteBase {
    direccion: string | null;
}

export interface VentaCabecera {
    id: number;
    numero_venta: string;
    fecha: string;
    total: string;
    total_pagado: number;
    estado: EstadoVenta;
    es_credito: boolean;
    saldo_pendiente: number;
}

export interface CuentaCliente {
    cliente: ClienteFicha;
    ventas: VentaCabecera[];
    total_comprado: number;
    fecha_ultima_compra: string | null;
}

export interface MetodoPagoOpcion {
    id: number;
    nombre: string;
    moneda: string;
    requiere_referencia: boolean;
}

export interface TasaVigente {
    id: number;
    tasa: string;
    fecha: string;
}

export interface ProductoVendible {
    id: number;
    nombre: string;
    precio_venta: string;
    stock_actual: string;
    controla_inventario: boolean;
    imagen_url: string | null;
    categoria: { id: number; nombre: string } | null;
    unidad_medida: { id: number; nombre: string; abreviatura: string } | null;
}

export function iniciales(nombre: string): string {
    const partes = nombre.trim().split(/\s+/).filter(Boolean);
    if (partes.length === 0) return '?';
    if (partes.length === 1) return partes[0].slice(0, 2).toUpperCase();
    return (partes[0][0] + partes[1][0]).toUpperCase();
}

// Las fechas de negocio llegan como 'YYYY-MM-DD' (sin hora). `new Date()` las
// lee como UTC medianoche y en Venezuela (UTC-4) mostraría el día anterior,
// así que se arman como fecha local.
export function fechaLocal(fecha: string): Date {
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map(Number);
    return new Date(anio, mes - 1, dia);
}

export function fmtFechaCorta(fecha: string): string {
    return fechaLocal(fecha).toLocaleDateString('es-VE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
}

// Marca de tiempo completa (ISO con zona): sí se interpreta con `new Date()`.
export function fmtFechaHora(fechaHora: string): string {
    const fecha = new Date(fechaHora);
    const dia = fecha.toLocaleDateString('es-VE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });
    const hora = fecha.toLocaleTimeString('es-VE', {
        hour: '2-digit',
        minute: '2-digit',
    });
    return `${dia}, ${hora}`;
}

// Antigüedad de una deuda en texto corto: "hoy", "hace 1 día", "hace 12 días".
export function antiguedad(fecha: string): string {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    const dias = Math.round(
        (hoy.getTime() - fechaLocal(fecha).getTime()) / 86_400_000,
    );
    if (dias <= 0) return 'hoy';
    return dias === 1 ? 'hace 1 día' : `hace ${dias} días`;
}

export function diasDeAntiguedad(fecha: string): number {
    const hoy = new Date();
    hoy.setHours(0, 0, 0, 0);
    return Math.round(
        (hoy.getTime() - fechaLocal(fecha).getTime()) / 86_400_000,
    );
}

export function redondear(valor: number): number {
    return Math.round(valor * 10000) / 10000;
}

// Respuesta de ventas.show (la usa el estado de cuenta de una sola venta)
export interface DetalleVentaCompleto {
    id: number;
    numero_venta: string;
    fecha: string;
    subtotal: string;
    descuento: string;
    total: string;
    estado: EstadoVenta;
    saldo_pendiente: number;
    tasa_cambio: { tasa: string; fecha: string } | null;
    detalles: {
        id: number;
        cantidad: string;
        precio_unitario: string;
        subtotal: string;
        producto: { id: number; nombre: string };
    }[];
    pagos: {
        id: number;
        monto: string;
        referencia: string | null;
        fecha_pago: string;
        metodo_pago: { id: number; nombre: string; moneda: string };
    }[];
}

export interface NegocioInfo {
    nombre: string;
    telefono: string | null;
    direccion: string | null;
    logoUrl: string | null;
}
