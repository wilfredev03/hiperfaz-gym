import type { MetodoPagoOpcion, TasaVigente } from '@/lib/clientes';
import { esMetodoEfectivo } from '@/lib/ventas';

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

export interface PlanOpcion {
    id: number;
    nombre: string;
    precio_venta: string;
    duracion_dias: number;
}

/** Lo que se envía al servidor para activar una membresía. */
export interface MembresiaForm {
    plan_id: number | null;
    // membresía a la medida: precio y duración (días o fechas) los pone quien cobra
    personalizada: boolean;
    precio: string;
    modo: 'dias' | 'fechas';
    dias: string;
    fecha_inicio: string;
    fecha_fin: string;
    es_credito: boolean;
    metodo_pago_id: number | null;
    referencia: string;
    // lo que entrega el cliente en efectivo/divisas, en la moneda del método
    monto_recibido: string;
}

export function membresiaVacia(): MembresiaForm {
    return {
        plan_id: null,
        personalizada: false,
        precio: '',
        modo: 'dias',
        dias: '',
        fecha_inicio: '',
        fecha_fin: '',
        es_credito: false,
        metodo_pago_id: null,
        referencia: '',
        monto_recibido: '',
    };
}

export type CampoErrorMembresia =
    | 'plan_id'
    | 'precio'
    | 'dias'
    | 'fecha_inicio'
    | 'fecha_fin'
    | 'metodo_pago_id'
    | 'referencia'
    | 'monto_recibido'
    | 'tasa_cambio';

export const CAMPOS_ERROR_MEMBRESIA: CampoErrorMembresia[] = [
    'plan_id',
    'precio',
    'dias',
    'fecha_inicio',
    'fecha_fin',
    'metodo_pago_id',
    'referencia',
    'monto_recibido',
    'tasa_cambio',
];

/** Hay algo elegido para cobrar: un plan o una membresía personalizada. */
export function hayMembresia(modelo: MembresiaForm): boolean {
    return modelo.plan_id !== null || modelo.personalizada;
}

export interface MembresiaElegida {
    nombre: string;
    duracion: string;
    // 0 mientras una membresía personalizada no tenga precio
    precio: number;
}

/** Resumen de lo elegido (plan del catálogo o personalizada) para mostrar el total. */
export function membresiaElegida(
    modelo: MembresiaForm,
    planes: PlanOpcion[],
): MembresiaElegida | null {
    if (modelo.personalizada) {
        const duracion =
            modelo.modo === 'dias'
                ? modelo.dias
                    ? textoDuracion(Number(modelo.dias))
                    : 'Sin duración'
                : modelo.fecha_inicio && modelo.fecha_fin
                  ? `${fmtDia(modelo.fecha_inicio)} al ${fmtDia(modelo.fecha_fin)}`
                  : 'Sin fechas';

        return {
            nombre: 'Membresía personalizada',
            duracion,
            precio: Number(modelo.precio) || 0,
        };
    }

    const plan = planes.find((p) => p.id === modelo.plan_id);

    return plan
        ? {
              nombre: plan.nombre,
              duracion: textoDuracion(plan.duracion_dias),
              precio: Number(plan.precio_venta),
          }
        : null;
}

export interface CobroEnEfectivo {
    moneda: 'USD' | 'VES';
    // lo que vale la membresía en la moneda del método (Bs redondeados a 2 decimales)
    total: number;
    // lo que entregó el cliente; igual al total mientras no se escriba otra cosa
    recibido: number;
    vuelto: number;
    // lo que falta si entregó menos del total
    falta: number;
}

/**
 * Cuenta de efectivo/divisas: lo recibido, el vuelto y lo que falta, en la
 * moneda del propio método (Bs para Efectivo, USD para Divisas). Es `null`
 * si no se paga con un método de efectivo o no hay con qué convertir.
 */
export function cobroEnEfectivo(
    modelo: MembresiaForm,
    planes: PlanOpcion[],
    metodosPago: MetodoPagoOpcion[],
    tasa: TasaVigente | null,
): CobroEnEfectivo | null {
    const elegida = membresiaElegida(modelo, planes);
    const metodo = metodosPago.find((m) => m.id === modelo.metodo_pago_id);

    if (!elegida || !metodo || modelo.es_credito || !esMetodoEfectivo(metodo)) {
        return null;
    }

    const enBs = metodo.moneda === 'VES';

    if (enBs && !tasa) {
        return null;
    }

    const total = Math.round(elegida.precio * (enBs ? Number(tasa!.tasa) : 1) * 100) / 100;
    const escrito = Number(modelo.monto_recibido);
    const recibido =
        modelo.monto_recibido !== '' && Number.isFinite(escrito) ? escrito : total;
    const diferencia = Math.round((recibido - total) * 100) / 100;

    return {
        moneda: enBs ? 'VES' : 'USD',
        total,
        recibido,
        vuelto: Math.max(0, diferencia),
        falta: Math.max(0, -diferencia),
    };
}
