// Bot de WhatsApp (Baileys) de Hiperfaz Gym.
//
// Solo ejecuta: mantiene la sesión de WhatsApp y envía los mensajes que
// Laravel le pide. Qué enviar, a quién y cuándo (horario, esperas, descansos)
// lo decide Laravel; aquí no hay lógica de negocio. Escucha únicamente en
// 127.0.0.1 y exige el token compartido en la cabecera X-Token.
//
//   GET  /estado       -> { conectado, estado, qr, numero }
//   POST /enviar       -> { telefono: "58414XXXXXXX", mensaje } => { id }
//   POST /desconectar  -> cierra la sesión y pide un QR nuevo
//   GET  /eventos      -> ?despues=N : historial de la sesión (QR, conexión,
//                         caídas, reconexiones). Laravel lo consulta y lo guarda.

import { createServer } from 'node:http';
import { timingSafeEqual } from 'node:crypto';
import { appendFileSync, readFileSync, rmSync, writeFileSync } from 'node:fs';
import makeWASocket, {
    Browsers,
    DisconnectReason,
    fetchLatestBaileysVersion,
    useMultiFileAuthState,
} from '@whiskeysockets/baileys';
import pino from 'pino';
import QRCode from 'qrcode';

const PORT = Number(process.env.PORT ?? 3100);
const TOKEN = process.env.WHATSAPP_TOKEN ?? '';
const AUTH_DIR = process.env.AUTH_DIR ?? './auth';
// fuera de AUTH_DIR a propósito: al cerrar sesión se borra esa carpeta y el historial debe sobrevivir
const EVENTOS_FILE = process.env.EVENTOS_FILE ?? './eventos.jsonl';
const MAX_EVENTOS = 500;
// Cuánto tiempo se ofrece QR para vincular antes de pararse a esperar. WhatsApp
// renueva el código cada ~20 s por su cuenta (la imagen se actualiza sola); esto
// es el tiempo total de la ventana. Un QR a la vista vincula la cuenta de quien
// lo escanee, así que no se deja abierto indefinidamente.
const QR_SEGUNDOS = Number(process.env.QR_TIMEOUT_SEG ?? 120);

if (!TOKEN) {
    console.error('Falta WHATSAPP_TOKEN en whatsapp-bot/.env');
    process.exit(1);
}

const logger = pino({ level: process.env.LOG_LEVEL ?? 'warn' });

let sock = null;
let generacion = 0; // cada conexión nueva invalida a la anterior: sus eventos tardíos se ignoran
let qrTimer = null;
let qrVenceEn = null; // marca de tiempo en que se acaba la ventana del QR
let estado = 'conectando'; // conectado | esperando_qr | qr_expirado | conectando | desconectado
let qr = null; // imagen del QR como data URL, solo mientras espera el escaneo
let numero = null;

const dormir = (ms) => new Promise((resolve) => setTimeout(resolve, ms));
const entre = (min, max) => Math.floor(Math.random() * (max - min + 1)) + min;

// --- Historial de la sesión -----------------------------------------------
// Cada evento lleva un id que solo sube; Laravel pide "los posteriores a N".
// Se guarda en un archivo de líneas JSON para que sobreviva a un reinicio.

let eventos = [];
let ultimoId = 0;

function cargarEventos() {
    try {
        eventos = readFileSync(EVENTOS_FILE, 'utf8')
            .split('\n')
            .filter(Boolean)
            .slice(-MAX_EVENTOS)
            .map((linea) => JSON.parse(linea));
        ultimoId = eventos.at(-1)?.id ?? 0;
        // compacta el archivo para que no crezca sin límite
        writeFileSync(EVENTOS_FILE, eventos.map((e) => JSON.stringify(e)).join('\n') + (eventos.length ? '\n' : ''));
    } catch {
        eventos = [];
    }
}

function registrar(tipo, detalle = null, codigo = null) {
    const evento = { id: ++ultimoId, tipo, detalle, codigo, numero, ocurrido_en: new Date().toISOString() };

    eventos.push(evento);
    if (eventos.length > MAX_EVENTOS) {
        eventos.shift();
    }
    appendFileSync(EVENTOS_FILE, JSON.stringify(evento) + '\n');
}

// Motivos de desconexión de Baileys en lenguaje de persona.
const MOTIVOS = {
    [DisconnectReason.connectionClosed]: 'La conexión se cerró',
    [DisconnectReason.connectionLost]: 'Se perdió la conexión con WhatsApp',
    [DisconnectReason.connectionReplaced]: 'La sesión se abrió en otro lugar',
    [DisconnectReason.timedOut]: 'Se agotó el tiempo de espera',
    [DisconnectReason.badSession]: 'Sesión dañada',
    [DisconnectReason.unavailableService]: 'WhatsApp no disponible',
};

let perdidaDesde = null; // cuándo se cayó la conexión, para medir la caída al reconectar
let cierreManual = false; // la sesión se cerró desde el panel: no es una desvinculación desde el teléfono
let qrAvisado = false; // se avisa una vez por QR pendiente, no cada vez que WhatsApp lo renueva

function limpiarQrTimer() {
    clearTimeout(qrTimer);
    qrTimer = null;
    qrVenceEn = null;
}

// Se acabó la ventana sin que nadie escaneara: se cierra el intento y se espera
// a que alguien pida un QR nuevo (botón del panel), en vez de generar códigos sin fin.
function expirarQr() {
    if (estado !== 'esperando_qr') {
        return;
    }

    limpiarQrTimer();
    estado = 'qr_expirado';
    qr = null;
    qrAvisado = false;
    registrar('qr_expirado', `Nadie escaneó el código en ${Math.round(QR_SEGUNDOS / 60)} min. Genera un QR nuevo para vincular.`);

    try {
        sock?.end(undefined);
    } catch {
        // ya estaba cerrado
    }
}

// Pedido desde el panel: descarta el intento actual y arranca uno limpio, que
// vuelve a mostrar un QR (o se conecta solo si ya hay sesión guardada).
async function reconectar() {
    if (estado === 'conectado') {
        throw { status: 409, error: 'WhatsApp ya está conectado.' };
    }

    limpiarQrTimer();
    estado = 'conectando';
    qr = null;
    qrAvisado = false;

    try {
        sock?.end(undefined);
    } catch {
        // ya estaba cerrado
    }

    await conectar();
}

async function conectar() {
    const miGeneracion = ++generacion;
    const { state, saveCreds } = await useMultiFileAuthState(AUTH_DIR);
    const { version } = await fetchLatestBaileysVersion();

    sock = makeWASocket({
        version,
        auth: state,
        logger,
        browser: Browsers.ubuntu('Chrome'),
        markOnlineOnConnect: false,
        syncFullHistory: false,
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async ({ connection, lastDisconnect, qr: codigo }) => {
        // un socket reemplazado o expirado puede seguir emitiendo eventos un momento
        if (miGeneracion !== generacion || estado === 'qr_expirado') {
            return;
        }

        if (codigo) {
            estado = 'esperando_qr';
            qr = await QRCode.toDataURL(codigo, { margin: 1, width: 320 });

            if (!qrAvisado) {
                qrAvisado = true;
                qrVenceEn = Date.now() + QR_SEGUNDOS * 1000;
                qrTimer = setTimeout(expirarQr, QR_SEGUNDOS * 1000);
                registrar('qr_pendiente', `Esperando que se escanee el código QR (vigente ${Math.round(QR_SEGUNDOS / 60)} min).`);
            }
        }

        if (connection === 'open') {
            limpiarQrTimer();
            estado = 'conectado';
            qr = null;
            qrAvisado = false;
            numero = sock.user?.id?.split(':')[0]?.split('@')[0] ?? null;
            console.log(`WhatsApp conectado (${numero}).`);

            if (perdidaDesde) {
                const segundos = Math.round((Date.now() - perdidaDesde) / 1000);
                registrar('reconectado', `Conexión recuperada tras ${segundos} s sin servicio.`);
                perdidaDesde = null;
            } else {
                registrar('conectado', 'Sesión de WhatsApp conectada.');
            }
        }

        if (connection === 'close') {
            const motivo = lastDisconnect?.error?.output?.statusCode;
            const numeroAnterior = numero;
            const estabaEnQr = estado === 'esperando_qr';
            numero = null;
            qr = null;
            qrAvisado = false;

            // WhatsApp cerró por su cuenta el intento de QR (se agotaron los códigos que
            // concede): es lo mismo que una expiración, y no se reintenta solo. El
            // reinicio 515 que sigue a un escaneo exitoso sí debe continuar.
            if (estabaEnQr && motivo !== DisconnectReason.restartRequired && motivo !== DisconnectReason.loggedOut) {
                estado = 'esperando_qr';
                expirarQr();

                return;
            }

            limpiarQrTimer();

            if (motivo === DisconnectReason.loggedOut) {
                // se cerró la sesión (desde el teléfono, o desde el panel): credenciales inservibles
                estado = 'desconectado';
                rmSync(AUTH_DIR, { recursive: true, force: true });
                perdidaDesde = null;

                if (!cierreManual) {
                    numero = numeroAnterior;
                    registrar('sesion_cerrada', 'El número se desvinculó desde el teléfono. Hay que escanear un QR nuevo.', motivo);
                    numero = null;
                }
                cierreManual = false;
            } else {
                estado = 'conectando';

                // tras escanear el QR WhatsApp pide reiniciar la conexión (515): es normal, no una caída
                if (motivo !== DisconnectReason.restartRequired && !perdidaDesde) {
                    perdidaDesde = Date.now();
                    numero = numeroAnterior;
                    registrar('conexion_perdida', `${MOTIVOS[motivo] ?? 'Conexión interrumpida'}. Reconectando...`, motivo ?? null);
                    numero = null;
                }
            }

            setTimeout(() => conectar().catch((e) => console.error('Reconexión fallida:', e)), 3000);
        }
    });
}

// Un mensaje a la vez: así la simulación de "escribiendo..." y el envío de
// dos peticiones seguidas nunca se pisan.
let cola = Promise.resolve();

function enviar(telefono, mensaje) {
    const tarea = cola.then(() => enviarAhora(telefono, mensaje));
    cola = tarea.catch(() => {});

    return tarea;
}

async function enviarAhora(telefono, mensaje) {
    if (estado !== 'conectado') {
        throw { status: 503, error: 'WhatsApp no está conectado.' };
    }

    const [registro] = (await sock.onWhatsApp(`${telefono}@s.whatsapp.net`)) ?? [];

    if (!registro?.exists) {
        throw { status: 422, error: 'El número no tiene WhatsApp.' };
    }

    // se comporta como una persona: aparece "escribiendo..." un rato
    // proporcional al largo del texto antes de que llegue el mensaje
    await sock.presenceSubscribe(registro.jid);
    await sock.sendPresenceUpdate('composing', registro.jid);
    await dormir(Math.min(1500 + mensaje.length * 25, 6000) + entre(0, 1200));
    await sock.sendPresenceUpdate('paused', registro.jid);

    const enviado = await sock.sendMessage(registro.jid, { text: mensaje });

    return { id: enviado?.key?.id ?? null };
}

async function desconectar() {
    cierreManual = true; // Laravel anota este cierre con el usuario que lo pidió
    try {
        await sock?.logout();
    } catch {
        // si ya estaba caído, basta con borrar las credenciales
    }
    rmSync(AUTH_DIR, { recursive: true, force: true });
    // si el cierre no llegó a producir el evento de Baileys, que la marca no
    // oculte una desvinculación posterior hecha desde el teléfono
    setTimeout(() => (cierreManual = false), 15_000);
}

function tokenValido(recibido) {
    const a = Buffer.from(String(recibido ?? ''));
    const b = Buffer.from(TOKEN);

    return a.length === b.length && timingSafeEqual(a, b);
}

function leerJson(req) {
    return new Promise((resolve, reject) => {
        let cuerpo = '';
        req.on('data', (trozo) => {
            cuerpo += trozo;
            if (cuerpo.length > 10_000) {
                reject({ status: 413, error: 'Cuerpo demasiado grande.' });
                req.destroy();
            }
        });
        req.on('end', () => {
            try {
                resolve(cuerpo ? JSON.parse(cuerpo) : {});
            } catch {
                reject({ status: 400, error: 'JSON inválido.' });
            }
        });
    });
}

const servidor = createServer(async (req, res) => {
    const responder = (status, datos) => {
        res.writeHead(status, { 'Content-Type': 'application/json' });
        res.end(JSON.stringify(datos));
    };

    if (!tokenValido(req.headers['x-token'])) {
        return responder(401, { error: 'No autorizado.' });
    }

    try {
        if (req.method === 'GET' && req.url === '/estado') {
            return responder(200, {
                conectado: estado === 'conectado',
                estado,
                qr,
                numero,
                // segundos que le quedan al QR a la vista, para la cuenta regresiva del panel
                qr_expira_en: qrVenceEn ? Math.max(0, Math.round((qrVenceEn - Date.now()) / 1000)) : null,
            });
        }

        if (req.method === 'POST' && req.url === '/reconectar') {
            await reconectar();

            return responder(200, { ok: true });
        }

        if (req.method === 'GET' && req.url.startsWith('/eventos')) {
            const despues = Number(new URL(req.url, 'http://localhost').searchParams.get('despues') ?? 0) || 0;

            return responder(200, {
                ultimo_id: ultimoId,
                eventos: eventos.filter((e) => e.id > despues).slice(0, 200),
            });
        }

        if (req.method === 'POST' && req.url === '/enviar') {
            const { telefono, mensaje } = await leerJson(req);

            if (!/^58\d{10}$/.test(String(telefono)) || typeof mensaje !== 'string' || !mensaje.trim() || mensaje.length > 1000) {
                return responder(400, { error: 'Teléfono (58XXXXXXXXXX) o mensaje inválido.' });
            }

            return responder(200, await enviar(telefono, mensaje));
        }

        if (req.method === 'POST' && req.url === '/desconectar') {
            await desconectar();

            return responder(200, { ok: true });
        }

        return responder(404, { error: 'No encontrado.' });
    } catch (e) {
        if (e?.status) {
            return responder(e.status, { error: e.error });
        }
        console.error(e);

        return responder(500, { error: 'Error interno del bot.' });
    }
});

servidor.listen(PORT, '127.0.0.1', () => console.log(`Bot de WhatsApp escuchando en 127.0.0.1:${PORT}`));

cargarEventos();
registrar('servicio_iniciado', 'Servicio del bot iniciado.');

conectar().catch((e) => {
    console.error('No se pudo iniciar la sesión de WhatsApp:', e);
    process.exit(1);
});
