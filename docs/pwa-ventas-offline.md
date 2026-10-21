# Plan — PWA de ventas offline (tablet)

> **Estado:** planificado, no implementado. Se construye **después** de que el
> sistema online esté terminado y en producción con el primer cliente real.
> Este documento es la referencia de diseño para ese momento; nada de lo que
> describe existe todavía en el código.

---

## 1. Decisión y alcance

### Qué resuelve

El sistema se sirve desde un VPS, así que hoy sin Internet no se puede vender:
el navegador no carga nada. La PWA pone una copia de la pantalla de venta en
una tablet Android, guarda las ventas localmente y las sincroniza cuando vuelve
la señal.

### Modo de operación elegido

**Modo dual:** con conexión se sigue vendiendo desde la web normal, como hasta
ahora. La tablet entra cuando se cae Internet.

> **Nota de diseño.** Se evaluó la alternativa _local-first_ (la tablet es
> siempre la forma de vender, sincronizando en segundo plano) y se descartó a
> favor del modo dual. El modo dual tiene dos costos que este plan debe cubrir
> explícitamente, y por eso existen las secciones §9 y §10:
>
> - El caché de la tablet se queda viejo si el equipo pasa semanas sin usarse,
>   justo hasta el día del apagón. Lo cubre el _keep-warm_ (§9).
> - El camino offline casi nunca se ejercita, así que falla el día que hace
>   falta. Lo cubre el simulacro mensual (§10).
>
> Si en el futuro se prefiere revisar esta decisión, el cambio a local-first
> no invalida nada de este plan: son las mismas piezas, usadas siempre en vez
> de solo durante un corte.

### Alcance

| Va en la tablet (offline)               | Se queda en la web (online)     |
| --------------------------------------- | ------------------------------- |
| Catálogo de productos (solo lectura)    | Productos, categorías, unidades |
| Clientes con saldo (solo lectura)       | Clientes (alta y edición)       |
| Carrito y cobro, con pagos mixtos       | Proveedores y órdenes de compra |
| Venta a crédito sobre cliente existente | Tasas de cambio                 |
| Abonos a créditos existentes            | Sucursales y configuración      |
| Cierre de caja de la terminal           | Reportes y cuentas por cobrar   |

**Fuera de alcance, deliberadamente:** crear productos o clientes desde la
tablet, recibir mercancía, y cualquier reporte. Todo eso tolera esperar a que
vuelva la conexión, y meterlo multiplicaría la superficie de sincronización sin
beneficio real.

### Propiedad que hace esto tratable

**Nunca hay dos vendedores simultáneos.** Si no hay Internet, la web es
inusable y solo vende la tablet. Si hay Internet, se vende por la web y la
tablet está ociosa. Ese único escritor a la vez elimina de raíz el problema de
sobreventa concurrente.

La excepción es la ventana de drenaje (§8.4), que sí hay que manejar.

---

## 2. Requisitos de hardware

|                | Mínimo | Recomendado |
| -------------- | ------ | ----------- |
| RAM            | 3 GB   | **4 GB**    |
| Pantalla       | 10"    | 10"         |
| Android        | 10     | **12+**     |
| Almacenamiento | 32 GB  | 32 GB       |

**Android, no iPad.** En iOS, Safari desaloja IndexedDB bajo presión de
almacenamiento y no soporta Background Sync. Es la diferencia entre que esto
funcione y que se pierdan ventas.

La RAM no es por rendimiento: en tablets de 2 GB, Android mata Chrome en
segundo plano y se pierde el carrito a medio armar. CPU y almacenamiento son
irrelevantes — una venta ocupa ~1–3 KB y un catálogo de 2.000 productos, ~1 MB.

---

## 3. Piezas a construir

1. **`manifest.json`** — nombre, icono, `display: standalone`. Trivial.
2. **Service worker** vía `vite-plugin-pwa` — cachea el shell de la app para
   que arranque sin red. Generado, no escrito a mano.
3. **Capa de datos local** sobre IndexedDB (§4).
4. **Cola de sincronización** con su máquina de estados (§6).
5. **Endpoint idempotente** de recepción (§7).
6. **Pantalla de venta en modo cliente** — la parte conceptualmente distinta:
   deja de recibir props de Inertia y lee de IndexedDB (§5).
7. **Panel de Conexión** con datos reales (§11).

El grueso del esfuerzo está en 3, 4 y 6. Lo demás es configuración.

---

## 4. Esquema de IndexedDB

Base `hiperfaz`, versión 1. Se recomienda `dexie` sobre la API cruda.

### Almacenes de solo lectura (espejo del servidor)

| Almacén           | Clave | Índices                  | Contenido                                                                        |
| ----------------- | ----- | ------------------------ | -------------------------------------------------------------------------------- |
| `productos`       | `id`  | `categoria_id`, `codigo` | Catálogo con precio, unidad, `controla_inventario`, `stock_actual` (referencial) |
| `categorias`      | `id`  | —                        | Para filtrar el catálogo                                                         |
| `unidades_medida` | `id`  | —                        | Para mostrar kg / unidad                                                         |
| `clientes`        | `id`  | `cedula_rif`             | Solo para venta a crédito y abonos                                               |
| `metodos_pago`    | `id`  | —                        | Configurables, nunca hardcodeados                                                |
| `tasa_vigente`    | —     | —                        | Registro único: última tasa conocida con su fecha                                |

Estos almacenes se **reemplazan completos** en cada refresco. No se editan
desde la tablet nunca.

### Fotos de producto (Cache Storage, no IndexedDB)

`productos.imagen` apunta a un archivo servido vía `/storage/...` (subida
dinámica desde el catálogo, distinta por negocio): no es un asset del build,
así que el service worker no la puede precachear como al shell de la app.
Se cachea en **runtime**, como parte del mismo refresco de catálogo que
llena el almacén `productos`: por cada producto con `imagen_url`, un
`fetch` + `caches.open('productos-imagenes').put(...)`, estrategia
cache-first una vez descargada. Con `ExpirationPlugin` (maxEntries/maxAge)
para no llenar el almacenamiento de la tablet — las fotos pesan
órdenes de magnitud más que el resto del catálogo (ver §2: ~1 MB para 2.000
productos _sin_ contar fotos). Antes de implementar esto, las imágenes deben
venir ya redimensionadas/comprimidas al tamaño real de tarjeta (ver
seeders y flujo de subida del catálogo) — si no, el refresco de catálogo en
una conexión débil, justo antes de que se caiga, se vuelve el cuello de
botella de todo el plan.

### Almacenes de escritura

| Almacén             | Clave           | Índices               | Contenido                                                         |
| ------------------- | --------------- | --------------------- | ----------------------------------------------------------------- |
| `ventas_pendientes` | `uuid`          | `estado`, `creada_en` | La venta completa: cabecera, detalle y pagos en un solo documento |
| `abonos_pendientes` | `uuid`          | `estado`              | Abonos a ventas a crédito ya existentes                           |
| `cierre_local`      | `uuid`          | —                     | Cierre de caja de la terminal                                     |
| `carrito_borrador`  | —               | —                     | Registro único, el carrito en curso                               |
| `bitacora_sync`     | autoincremental | `fecha`               | Últimos ~200 eventos, para diagnóstico                            |

### Por qué la venta es un documento único

`ventas_pendientes` guarda cabecera + detalle + pagos anidados, no en tres
almacenes separados. Una venta se sincroniza como una unidad atómica: o entra
entera o no entra. Separarla obligaría a transacciones multi-almacén sin ganar
nada.

### `carrito_borrador`

Se reescribe en cada cambio del carrito, no solo al cobrar. Con eso, si Android
mata Chrome en segundo plano o se reinicia la tablet, la venta a medio armar se
recupera al volver a abrir. Es barato y evita el error más irritante posible.

### Almacenamiento persistente

Al instalar hay que pedir `navigator.storage.persist()`. Sin eso, Android puede
desalojar IndexedDB bajo presión de espacio. Una PWA instalada normalmente lo
concede. **Si se deniega, hay que avisarlo en el panel de Conexión** — es la
diferencia entre una cola segura y una que puede evaporarse.

---

## 5. La pantalla de venta cambia de naturaleza

Hoy las pantallas son páginas Inertia: el servidor manda las props ya
cocinadas. Una pantalla que funcione sin red no puede depender de eso.

- **Lee** el catálogo, clientes, métodos de pago y tasa desde IndexedDB.
- **Escribe** la venta en `ventas_pendientes`, nunca directo al servidor.
- El componente Vue del carrito, el cálculo de totales, el manejo de pagos
  mixtos y el vuelto **se reutilizan tal cual**. Lo único que cambia es de
  dónde vienen los datos y a dónde va el resultado.

El resto del sistema sigue siendo Inertia igual que ahora. Conviene extraer la
lógica de carrito y totales a un composable compartido por ambos caminos, para
no mantener dos veces la misma aritmética de dinero.

---

## 6. Máquina de estados de la cola

Cada registro en `ventas_pendientes` tiene un `estado`:

```
  pendiente ──► enviando ──► confirmada ──► (purgada a los 30 días)
                   │
                   ├── fallo transitorio ──► pendiente  (reintento con espera)
                   │
                   └── fallo permanente ───► requiere_atencion
```

| Estado              | Significado                                     |
| ------------------- | ----------------------------------------------- |
| `pendiente`         | Guardada local, esperando turno                 |
| `enviando`          | POST en vuelo                                   |
| `confirmada`        | El servidor acusó recibo con su `id` definitivo |
| `requiere_atencion` | Agotó reintentos; **no bloquea la cola**        |

### Reintentos

Espera creciente: 2s, 8s, 30s, 2min, 10min. Máximo **5 intentos**, después pasa
a `requiere_atencion`.

### La regla que evita el atasco

**Un fallo permanente nunca bloquea a los demás.** Si una venta es rechazada
por el servidor (producto borrado, dato inválido, caja ya cerrada), se marca
`requiere_atencion` y **la cola sigue drenando las siguientes**.

Este es el riesgo real de la cola, y no tiene nada que ver con el volumen. 50
ventas son ~100 KB y unos 5 segundos de envío — el volumen nunca es el
problema. Una sola venta envenenada con reintento infinito y cola estrictamente
secuencial sí lo es: tumba el día entero. En el peor caso aceptable queda 1
venta marcada y 49 sincronizadas.

### Disparadores

Automática, en cuatro momentos: al recuperar conexión (evento `online` +
Background Sync), al abrir la app, cada 60s mientras haya conexión y cola no
vacía, y al cerrar caja.

Más un botón manual **"Sincronizar ahora"**, que no hace falta técnicamente
pero le da al dueño algo que apretar cuando desconfía.

### Purga

Las `confirmada` se borran a los 30 días. El servidor ya es la fuente de
verdad; la copia local solo sirve para auditar un reclamo reciente.

---

## 7. Identidad de la venta e idempotencia

### El problema

La tablet manda una venta, el servidor la guarda, y se cae la señal antes de
que llegue la respuesta. La tablet no sabe si entró, reintenta, y se duplica la
venta. **Esta es la decisión técnica central de todo el plan.**

### La solución

Cada venta nace en la tablet con un **UUID v4**. El servidor hace _insertar solo
si ese UUID no existe_; si ya existe, responde `200` con el registro existente
en vez de crear otro. Con eso, reintentar cuantas veces sea es seguro, y todo lo
demás se simplifica.

### Cambios en `ventas`

```
uuid              uuid, unique, nullable   -- null en ventas hechas por la web
origen            string                   -- 'web' | 'tablet'
terminal_id       string, nullable         -- identifica la tablet
sincronizada_en   timestamp, nullable      -- cuándo llegó al servidor
```

`fecha` y `created_at` deben guardar **el momento real de la venta en la
tablet**, no el de la sincronización. Si no, una venta del lunes sincronizada el
miércoles aparece en el reporte del miércoles y descuadra todo. Es un error
fácil de cometer y caro de detectar después.

### `numero_venta`

Ya es `string` y `unique` en la migración actual, así que sirve sin cambios de
tipo. Offline no se puede pedir el siguiente correlativo al servidor, así que la
tablet genera **prefijo de terminal + secuencia local**: `T1-000042`. Con una
tablet por sucursal no hay colisión posible, y el prefijo hace evidente en los
reportes qué se vendió en contingencia.

---

## 8. Inventario — el punto que choca con el principio #2

### El conflicto

`movimientos_inventario` exige `stock_anterior` y `stock_nuevo` en cada fila
(ambos `NOT NULL` hoy). Offline la tablet **no conoce el stock verdadero
previo**: solo tiene la foto del último refresco.

### La resolución

La tablet envía **solo el delta** (`cantidad` y `tipo`). El **servidor calcula
`stock_anterior` y `stock_nuevo`** en el momento de recibir, dentro de la
transacción de sincronización, leyendo el stock real de ese instante.

Esto **no rompe el principio #2**, lo respeta: el ledger sigue siendo
append-only y sigue siendo la fuente de verdad reconstruible. Lo único que se
mueve es _dónde_ se calculan esos dos campos — del cliente al servidor.

### Consecuencia en el orden

Los movimientos deben aplicarse **en el orden real de venta**, no en el de
llegada. La cola drena en orden de `creada_en` para que la cadena
`stock_anterior → stock_nuevo` quede coherente al reconstruirla.

### 8.4 La ventana de drenaje

Único escenario donde vuelve el riesgo de sobreventa: vuelve Internet a
mediodía, la tablet tiene 12 ventas en cola, y alguien empieza a vender por la
web contra un `stock_actual` que todavía no las refleja.

Tres opciones, de más a menos estricta:

1. **Bloquear** la venta web hasta drenar la cola. Seguro, molesto.
2. **Avisar** en la pantalla de venta web: "12 ventas de tablet sin sincronizar,
   el stock puede estar desactualizado". ← **recomendada**
3. **Ignorar.** El ledger reconstruye el stock real igual, y `stock_actual` es
   una caché por el principio #2.

Se recomienda la 2: la ventana dura segundos, y bloquear la caja por eso es
peor que el problema. Si el negocio vende productos con stock muy ajustado,
subir a la 1.

---

## 9. Cierre de caja en modo dual

Un día con corte tiene ventas en los dos sistemas, y **ambas deben caer en un
solo cierre**.

- La tablet abre un `cierre_local` con su propio `monto_inicial` al empezar a
  operar offline.
- Al sincronizar, sus ventas se **asocian al cierre abierto del servidor para
  esa fecha**; no crean uno nuevo.
- Si no hay cierre abierto para esa fecha porque ya se cerró, esas ventas van a
  `requiere_atencion` con motivo explícito. Un cierre ya cuadrado no se toca
  automáticamente — eso lo decide una persona.
- **Regla operativa: no cerrar caja con cola pendiente.** La pantalla de cierre
  debe verificar la cola y advertir antes de permitirlo.

Esto se apoya en algo que ya está bien resuelto: `ventas.cierre_caja_id` (día en
que se facturó) y `venta_pagos.cierre_caja_id` (día en que entró el dinero) son
independientes, así que un abono sincronizado tarde cae donde corresponde sin
tocar la venta.

---

## 10. Mantener la tablet lista

Consecuencia directa del modo dual: si la tablet solo se usa durante cortes, su
caché queda viejo justo cuando hace falta.

- **Refresco automático** del catálogo, clientes y tasa cada vez que la app se
  abre con conexión, y una vez al día en segundo plano.
- **Antigüedad visible.** Si los datos tienen más de 24h, la pantalla de venta
  lo dice. Si tienen más de 7 días, advertencia fuerte: los precios pueden estar
  desactualizados.
- **La tasa es crítica.** Vender con una tasa BCV de hace una semana distorsiona
  todos los montos en bolívares. Si la tasa cacheada es vieja, hay que pedir
  confirmación explícita o dejar ingresarla a mano antes de operar.
- **Simulacro mensual.** Poner en el instructivo del cliente: una vez al mes,
  vender algo desde la tablet en modo avión y verificar que sincroniza. Es la
  única forma de que el camino offline esté probado el día del apagón, y de que
  el personal sepa usarlo sin improvisar.

---

## 11. Visibilidad — dos lugares

### En la pantalla de venta

Indicador permanente y discreto: `● 12 por sincronizar`. La cajera necesita
saberlo de un vistazo, no entrando a configuración. En rojo si hay alguna en
`requiere_atencion`.

### En la pestaña Conexión (`settings/Conexion.vue`)

Ya existe, hoy con un texto explicativo de marcador de posición. Ese es su
contenido definitivo:

- Estado de conexión en vivo — **ya implementado** (`useConexion.ts` + `/ping`).
- Contador de pendientes y hora de la última sincronización exitosa.
- **Lista de `requiere_atencion` con su motivo** y botón de reintentar. Es la
  parte más importante de la pantalla: son las ventas que nadie va a recuperar
  si no se ven.
- Antigüedad del caché, con botón de refrescar.
- Aviso si `navigator.storage.persist()` fue denegado.
- Botón "Sincronizar ahora".

---

## 12. Orden de implementación

Cada fase deja algo verificable. No empezar hasta que el sistema online esté en
producción.

| #   | Fase                                                                                            | Entrega                                                                |
| --- | ----------------------------------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| 1   | UUID + endpoint idempotente                                                                     | La web sigue igual; el servidor ya acepta ventas con UUID sin duplicar |
| 2   | Migraciones (`uuid`, `origen`, `terminal_id`, `sincronizada_en`) + cálculo de stock en servidor | Base lista, sin tablet todavía                                         |
| 3   | `manifest.json` + service worker                                                                | La app se instala en la tablet y abre a pantalla completa              |
| 4   | IndexedDB + refresco de catálogo                                                                | La tablet muestra productos sin red                                    |
| 5   | Pantalla de venta en modo cliente + `carrito_borrador`                                          | Se puede armar y guardar una venta offline                             |
| 6   | Cola de sincronización y máquina de estados                                                     | Las ventas offline llegan al servidor                                  |
| 7   | Panel de Conexión con datos reales + indicador en venta                                         | Se ve qué pasa                                                         |
| 8   | Cierre de caja dual                                                                             | Cuadra un día con corte                                                |
| 9   | Abonos offline                                                                                  | Fiado durante un corte                                                 |

Las fases 1 y 2 se pueden adelantar mientras se termina el sistema online: no
rompen nada y dejan el terreno preparado.

---

## 13. Decisiones abiertas

- **Una tablet por sucursal o por cajero.** Con más de una vendiendo a la vez
  offline vuelve la sobreventa real, y este plan no la cubre.
- **Impresora térmica.** Web Bluetooth funciona en Chrome/Android con ESC/POS,
  pero es delicado. Hay que probarlo con el modelo concreto antes de prometerlo.
- **Balanza.** No va a hablar con la tablet. El operador lee el display y teclea
  `0.850`, así que el campo de cantidad debe estar diseñado para teclear peso
  rápido, no para pulsar `+`/`−`.
- **Autenticación offline.** La sesión de Laravel no sirve sin red. Hay que
  definir cómo se identifica al usuario en la tablet (PIN local por cajero es lo
  más simple) y cómo se atribuye `ventas.usuario_id` al sincronizar.
- **Anulación offline.** Hoy no está en alcance. Si una venta offline se hace
  mal, se corrige al sincronizar desde la web.
