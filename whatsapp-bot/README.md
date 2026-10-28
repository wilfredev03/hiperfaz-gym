# Bot de WhatsApp (Baileys)

Proceso Node aparte que usa Laravel para enviar los recordatorios de
membresías. Solo ejecuta: mantiene la sesión de WhatsApp y manda lo que
Laravel le pide. Qué se envía, a quién y cuándo (horario, esperas, descansos)
lo decide Laravel; ver `App\Services\Recordatorios`.

> Baileys es una librería **no oficial**. WhatsApp puede bloquear el número.
> Usa un número dedicado del gimnasio (no el personal del dueño) y no bajes
> las esperas ni los descansos configurados en el dashboard.

## Instalación

Requiere Node 20.6 o superior.

```bash
cd whatsapp-bot
npm install @whiskeysockets/baileys qrcode pino
cp .env.example .env     # y completa WHATSAPP_TOKEN
```

El mismo `WHATSAPP_TOKEN` va en el `.env` de Laravel (junto a `WHATSAPP_URL`,
por defecto `http://127.0.0.1:3100`). Después `npm install` deja fijadas las
versiones en `package.json`/`package-lock.json`: súbelas a git.

```bash
npm start
```

Entra a **Recordatorios** en el sistema (admin): mientras no haya sesión, el
dashboard muestra el QR. Escanéalo desde WhatsApp > Dispositivos vinculados.

## API (solo `127.0.0.1`, cabecera `X-Token`)

| Método | Ruta           | Descripción                                          |
| ------ | -------------- | ---------------------------------------------------- |
| GET    | `/estado`      | `{ conectado, estado, qr, numero }`                  |
| POST   | `/enviar`      | `{ telefono: "58414XXXXXXX", mensaje }` → `{ id }`   |
| POST   | `/reconectar`  | Descarta el intento actual y genera un QR nuevo      |
| POST   | `/desconectar` | Cierra la sesión y pide un QR nuevo                  |
| GET    | `/eventos`     | `?despues=N` → `{ ultimo_id, eventos }` (historial)  |

`/eventos` devuelve el historial de la sesión (QR pendiente, conectado, caídas
y reconexiones). Se guarda en `eventos.jsonl` y Laravel lo consulta cada minuto
(y al abrir el dashboard) para copiarlo a su base de datos; ahí se ve en
Recordatorios > Conexión. Incluye `eventos.jsonl` en los respaldos si quieres
conservar el historial anterior al último copiado.

**Vigencia del QR.** WhatsApp renueva la imagen del código cada ~20 s (el panel
la actualiza sola). La ventana total para escanear es `QR_TIMEOUT_SEG`
(120 s por defecto); pasada, el bot deja de generar códigos, queda en
`qr_expirado` y el panel muestra el botón **Generar nuevo QR** (`/reconectar`).
Así no queda un QR abierto indefinidamente: quien lo escanee vincula su
WhatsApp al sistema.

`/enviar` responde `422` si el número no tiene WhatsApp y `503` si la sesión
no está conectada.

## Producción (Supervisor)

```ini
[program:hiperfaz-whatsapp-bot]
command=/usr/bin/node --env-file=.env index.js
directory=/var/www/CLIENTE/whatsapp-bot
user=www-data
autostart=true
autorestart=true
stopwaitsecs=10
stdout_logfile=/var/log/supervisor/hiperfaz-whatsapp-bot.log
redirect_stderr=true
```

El envío lo dispara el scheduler de Laravel (`recordatorios:enviar`, cada
minuto), así que el cron de `schedule:run` debe estar activo en el VPS.

Cada cliente (carpeta + BD + dominio) tiene su propio bot, su propio puerto
y su propio número. La carpeta `auth/` guarda la sesión: inclúyela en los
respaldos o habrá que volver a escanear el QR.
