import {
    ArrowLeftRight,
    Banknote,
    CreditCard,
    Globe,
    Smartphone,
    Wallet,
} from '@lucide/vue';

export type EstadoVenta = 'pendiente' | 'parcial' | 'pagada' | 'anulada';

export const ESTADO_ESTILOS: Record<EstadoVenta, string> = {
    pagada: 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    parcial:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    pendiente:
        'border-blue-200 bg-blue-50 text-blue-700 dark:border-blue-900 dark:bg-blue-950 dark:text-blue-300',
    anulada:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
};

export const ESTADO_ETIQUETAS: Record<EstadoVenta, string> = {
    pagada: 'Pagada',
    parcial: 'Parcial',
    pendiente: 'Pendiente',
    anulada: 'Anulada',
};

export function fmtUsd(valor: number | string): string {
    return `$${Number(valor).toFixed(2)}`;
}

export function fmtBs(valor: number | string): string {
    return `Bs. ${Number(valor).toLocaleString('es-VE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;
}

export function fmtHora(fechaHora: string): string {
    return new Date(fechaHora).toLocaleTimeString('es-VE', {
        hour: '2-digit',
        minute: '2-digit',
    });
}

export function fmtFecha(fecha: string): string {
    return new Date(fecha).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

// --- Métodos de pago -----------------------------------------------------
// El catálogo de métodos es configurable por negocio, así que estas ayudas
// nunca deciden nada de lógica: solo eligen cómo se ve un método en
// pantalla (ícono y etiqueta corta) a partir de su nombre y su moneda.
// Viven aquí para que el cobro y los reportes muestren el mismo ícono para
// el mismo método.

export interface MetodoPagoVisual {
    nombre: string;
    moneda: string;
}

export function esMetodoEfectivo(metodo: MetodoPagoVisual): boolean {
    return metodo.nombre.toLowerCase().includes('efectivo');
}

// atajos de nombre para los métodos de efectivo; el resto se muestra tal
// cual está configurado en métodos de pago
export function etiquetaMetodo(metodo: MetodoPagoVisual): string {
    if (esMetodoEfectivo(metodo)) {
        return metodo.moneda === 'USD' ? 'Divisas' : 'Efectivo';
    }
    return metodo.nombre;
}

export function iconoMetodo(metodo: MetodoPagoVisual) {
    const nombre = metodo.nombre.toLowerCase();
    if (esMetodoEfectivo(metodo)) {
        return metodo.moneda === 'USD' ? Globe : Banknote;
    }
    if (nombre.includes('móvil') || nombre.includes('movil')) {
        return Smartphone;
    }
    if (nombre.includes('transfer')) {
        return ArrowLeftRight;
    }
    if (nombre.includes('punto de venta') || nombre.includes('tarjeta')) {
        return CreditCard;
    }
    return Wallet;
}
