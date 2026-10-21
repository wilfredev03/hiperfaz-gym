export type Vigencia = 'vigente' | 'por_vencer' | 'vencida' | 'sin_membresia';

export type EstadoSuscripcion =
    | 'activa'
    | 'vencida'
    | 'congelada'
    | 'cancelada';

export interface SocioLista {
    id: number;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    saldo_pendiente: string;
    activo: boolean;
    vence_el: string | null;
    vigencia: Vigencia;
    dias_restantes: number | null;
}

export const VIGENCIA_ETIQUETAS: Record<Vigencia, string> = {
    vigente: 'Vigente',
    por_vencer: 'Por vencer',
    vencida: 'Vencida',
    sin_membresia: 'Sin membresía',
};

export const VIGENCIA_ESTILOS: Record<Vigencia, string> = {
    vigente:
        'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-900 dark:bg-emerald-950 dark:text-emerald-300',
    por_vencer:
        'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300',
    vencida:
        'border-red-200 bg-red-50 text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300',
    sin_membresia: 'border-border bg-muted text-muted-foreground',
};

export const ESTADO_SUSCRIPCION_ETIQUETAS: Record<EstadoSuscripcion, string> = {
    activa: 'Activa',
    vencida: 'Vencida',
    congelada: 'Congelada',
    cancelada: 'Cancelada',
};

/**
 * Una fecha `YYYY-MM-DD` se interpreta como día local: `new Date('2026-10-20')`
 * la toma como UTC y en Venezuela (UTC-4) mostraría el día anterior.
 */
export function fmtDia(fecha: string): string {
    const [anio, mes, dia] = fecha.slice(0, 10).split('-').map(Number);

    return new Date(anio, mes - 1, dia).toLocaleDateString('es-VE', {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
    });
}

export function textoRestante(vigencia: Vigencia, dias: number | null): string {
    if (vigencia === 'sin_membresia') {
        return 'Nunca ha tenido membresía';
    }
    if (vigencia === 'vencida') {
        return 'Membresía vencida';
    }
    if (dias === 0) {
        return 'Vence hoy';
    }

    return dias === 1 ? 'Queda 1 día' : `Quedan ${dias} días`;
}

export function textoDuracion(dias: number): string {
    return dias === 1 ? '1 día' : `${dias} días`;
}
