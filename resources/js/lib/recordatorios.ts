export type TipoRecordatorio = 'por_vencer' | 'vence_hoy';
export type EstadoRecordatorio =
    | 'pendiente'
    | 'enviando'
    | 'enviado'
    | 'fallido'
    | 'cancelado';

export interface RecordatorioFila {
    id: number;
    cliente: string | null;
    cliente_id: number;
    telefono: string;
    tipo: TipoRecordatorio;
    mensaje: string;
    estado: EstadoRecordatorio;
    intentos: number;
    detalle: string | null;
    enviado_en: string | null;
    actualizado_en: string | null;
}

export interface Plantilla {
    id: number;
    tipo: TipoRecordatorio;
    nombre: string;
    cuerpo: string;
    activa: boolean;
}

export interface Ajustes {
    activo: boolean;
    hora_inicio: string;
    hora_fin: string;
    espera_min_seg: number;
    espera_max_seg: number;
    mensajes_descanso_min: number;
    mensajes_descanso_max: number;
    descanso_min: number;
    descanso_max: number;
    dias_aviso: number;
    duracion_minima_dias: number;
}

export interface EstadoBot {
    conectado: boolean;
    estado:
        | 'conectado'
        | 'esperando_qr'
        | 'conectando'
        | 'desconectado'
        | 'qr_expirado'
        | 'sin_servicio'
        | 'no_autorizado';
    qr: string | null;
    // segundos que le quedan a la ventana del QR a la vista
    qr_expira_en: number | null;
    numero: string | null;
}

export const TIPOS: Record<TipoRecordatorio, string> = {
    por_vencer: 'Por vencer',
    vence_hoy: 'Vence hoy',
};

export const ESTADOS: Record<
    EstadoRecordatorio,
    {
        texto: string;
        variante: 'default' | 'secondary' | 'destructive' | 'outline';
    }
> = {
    pendiente: { texto: 'Pendiente', variante: 'outline' },
    enviando: { texto: 'Enviando', variante: 'default' },
    enviado: { texto: 'Enviado', variante: 'secondary' },
    fallido: { texto: 'Fallido', variante: 'destructive' },
    cancelado: { texto: 'Cancelado', variante: 'outline' },
};

/** "3 may, 4:05 p. m." en hora local. */
export function fmtFechaHora(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('es-VE', {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/** Segundos restantes hasta una fecha ISO ("" si ya pasó o no hay). */
export function cuentaRegresiva(iso: string | null, ahora: number): string {
    if (!iso) {
        return '';
    }

    const segundos = Math.max(0, Math.round((new Date(iso).getTime() - ahora) / 1000));
    const m = Math.floor(segundos / 60);
    const s = String(segundos % 60).padStart(2, '0');

    return `${m}:${s}`;
}

export type TipoEventoBot =
    | 'servicio_iniciado'
    | 'qr_pendiente'
    | 'conectado'
    | 'conexion_perdida'
    | 'reconectado'
    | 'sesion_cerrada'
    | 'cierre_manual'
    | 'qr_expirado'
    | 'reconexion_manual';

export interface EventoConexion {
    id: number;
    tipo: TipoEventoBot;
    detalle: string | null;
    numero: string | null;
    usuario: string | null;
    ocurrido_en: string;
}

export interface Conexiones {
    eventos: EventoConexion[];
    resumen: {
        caidas_hoy: number;
        reconexiones_hoy: number;
        ultima_conexion: string | null;
    };
}

/** Texto y tono de cada evento de la sesión; `tono` decide el color del punto. */
export const EVENTOS_BOT: Record<
    TipoEventoBot,
    { texto: string; tono: 'ok' | 'aviso' | 'error' | 'neutro' }
> = {
    servicio_iniciado: { texto: 'Bot iniciado', tono: 'neutro' },
    qr_pendiente: { texto: 'QR generado', tono: 'aviso' },
    conectado: { texto: 'Conectado', tono: 'ok' },
    conexion_perdida: { texto: 'Conexión perdida', tono: 'error' },
    reconectado: { texto: 'Reconectado', tono: 'ok' },
    sesion_cerrada: { texto: 'Sesión cerrada', tono: 'error' },
    cierre_manual: { texto: 'Cierre manual', tono: 'aviso' },
    qr_expirado: { texto: 'QR expirado', tono: 'error' },
    reconexion_manual: { texto: 'QR nuevo solicitado', tono: 'aviso' },
};

/** Fecha y hora con segundos, para ver el orden exacto de una caída y su recuperación. */
export function fmtFechaHoraSeg(iso: string | null): string {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('es-VE', {
        day: 'numeric',
        month: 'short',
        hour: 'numeric',
        minute: '2-digit',
        second: '2-digit',
    });
}
