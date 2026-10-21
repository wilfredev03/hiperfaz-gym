# Hiperfaz Gym — Contexto del proyecto

Sistema de gestión para un **gimnasio**: membresías (suscripciones), ventas de
mostrador (bebidas, batidos, suplementos), cierre de caja, inventario y
compras. Nace como copia de Hiperfaz Gestión (sistema comercial genérico para
negocios de venta al detal); ambos repos evolucionan por separado y no se
sincronizan. El primer cliente es un gimnasio que ya tiene control de acceso
físico (terminal Hikvision con huella/rostro y puerta eléctrica) administrado
por un tercero.

Marca bajo la que se vende este producto: **Hiperfaz Gym**.

## Stack

- **Backend:** Laravel (última versión estable), Starter Kit con Breeze/Jetstream
  variante Vue + Inertia. Monolito — no hay API REST separada ni CORS, Inertia
  sirve los componentes Vue directamente desde las rutas de Laravel.
- **Frontend:** Vue 3 vía Inertia, compilado con Vite.
- **Base de datos:** PostgreSQL (`hiperfaz_gym` en local). Se eligió sobre MySQL
  por mejor soporte de window functions para reportes y tipado más estricto
  para montos de dinero.
- **Entorno local:** Laragon (Windows). Laravel 13 exige PHP ≥ 8.3: usar el
  8.3 de Laragon.
- **Despliegue:** VPS (no serverless, no Supabase/Vercel) — Nginx + PHP-FPM +
  Postgres + Supervisor para colas. Cada cliente tiene su propia carpeta, su
  propia base de datos y su propio dominio en el mismo VPS (no multi-tenant
  compartido, cada negocio está completamente aislado).
- **Backups:** `spatie/laravel-backup` programado por cron, obligatorio antes
  de dar el sistema por "en producción" con un cliente real.

## Contexto de negocio relevante

- Se vende **membresías** (mensual, quincenal, trimestral, pase de día...) y
  también bebidas, batidos y suplementos. Todo pasa por el mismo flujo de
  ventas.
- El sistema maneja **doble moneda** (USD y VES/bolívares) con conversión mediante la tasa BCV.
- Los métodos de pago típicos incluyen efectivo USD, efectivo Bs, pago móvil,
  transferencia y Zelle — deben ser configurables, no hardcodeados.
- Es común que un socio pague una sola venta combinando varios métodos
  (parte en efectivo, parte en pago móvil).
- Es común el fiado/crédito informal a socios conocidos (ej. membresía pagada
  por partes).
- El control de acceso físico (terminal Hikvision) **no forma parte de este
  sistema todavía** — ver principio #11 y "Fase 2".

## Principios de diseño (no romper estos al agregar features nuevas)

1. **Inmutabilidad del histórico.** Una venta ya creada nunca se edita. Cada
   `venta`, `venta_detalle` y `venta_pagos` guarda su propio precio, costo y
   tasa de cambio en el momento de la transacción. Nunca se recalculan ventas
   pasadas con precios, costos o tasas actuales.
2. **El inventario es un ledger, no un contador.** `movimientos_inventario`
   es una tabla de solo inserción (append-only) que registra cada entrada y
   salida de stock con `stock_anterior` y `stock_nuevo`. El campo
   `productos.stock_actual` es solo una caché — si alguna vez queda
   inconsistente, se puede reconstruir sumando el ledger completo.
3. **Dinero y cantidades siempre en `DECIMAL`/`NUMERIC`.** Nunca `FLOAT`, ni
   para precios/costos ni para cantidades.
4. **Catálogo genérico, nunca lógica de negocio hardcodeada por rubro.**
   `categorias` y `unidades_medida` son tablas configurables desde el
   sistema. El código nunca debe preguntar "es gimnasio" ni por nombres de
   categoría — todo se resuelve con los datos que carga cada negocio.
   `productos.controla_inventario` decide si un producto descuenta stock
   (ej. suplemento envasado) o no (ej. membresía, batido hecho al momento).
   Un **plan de membresía es un producto con `controla_inventario = false`**
   que se vende por el flujo normal de ventas; no se duplica ese flujo.
5. **Pagos divididos.** `venta_pagos` es una tabla independiente de
   `ventas` porque una venta puede pagarse con más de un método a la vez.
6. **Crédito/fiado sin tabla nueva de "cuentas por cobrar".**
    - `ventas.es_credito` + `ventas.cliente_id` (obligatorio si es crédito,
      forzado con un `CHECK constraint` en Postgres, no solo validación de
      Laravel).
    - `ventas.estado`: `pendiente | parcial | pagada | anulada`.
    - Un abono posterior a una venta a crédito **no reabre ni edita la
      venta**: simplemente se inserta una fila nueva en `venta_pagos` con
      `fecha_pago` de hoy.
    - `ventas.cierre_caja_id` = el día en que se **facturó** la venta.
      `venta_pagos.cierre_caja_id` = el día en que **entró el dinero**
      (pueden ser distintos si el abono llega días después).
    - `clientes.saldo_pendiente` es un campo cacheado opcional para no
      recalcular sumas en cada consulta de "cuentas por cobrar".
7. **Cierre de caja.** Sin fondo inicial, sin notas, sin apertura manual ni
   conteo físico. `cierres_caja` se abre solo (`estado = abierto`) con la
   primera venta del día (`CierreCaja::abrirAutomaticaDeHoy`, invocado desde
   `VentaController@store`) — nunca hay un botón de "abrir caja". Cerrar
   caja no pide ningún monto digitado: el controlador calcula
   `total_ventas` (suma de `ventas.total` del día) y el desglose por método
   de pago desde `venta_pagos`, lo guarda en `cierre_caja_metodos` (una fila
   por método con su `monto`, sin esperado/contado/diferencia) y envía el
   reporte por correo. El reporte muestra: total vendido, desglose por
   método de pago y las ventas a crédito de ese día — siempre en USD **y**
   su equivalente en bolívares, nunca solo en una moneda. `venta_pagos.monto`
   y `ventas.total` siempre están en USD; el equivalente en Bs se calcula con
   la tasa propia del cierre (`cierres_caja.tasa_cambio_id`, fijada al
   abrirlo) y se persiste en `cierre_caja_metodos.monto_bs` y
   `cierres_caja.total_ventas_bs` al cerrar, para no recalcular un cierre ya
   cerrado con una tasa distinta más adelante. `total_ventas` es lo
   facturado (incluye ventas a crédito aunque no se hayan cobrado); el
   desglose por método de pago es el dinero que efectivamente entró, así
   que una venta a crédito sin abonar no aparece ahí hasta que se registre
   un pago sobre `venta_pagos.cierre_caja_id` del día en que entra el dinero.
8. **Reportes semanales/quincenales/mensuales son consultas, no tablas
   nuevas** (agregaciones con `SUM`/`GROUP BY` sobre fecha). Solo si el
   rendimiento se vuelve un problema real se justifica una tabla resumen tipo
   `resumen_ventas_diario` — no construirla de forma preventiva.
9. **Multi-sucursal por negocio, no multi-tenant por variables de entorno.**
   Un mismo negocio (una carpeta + una BD + un dominio, ver Stack) puede
   tener varias sucursales. Eso vive en la tabla `sucursales`, nunca en
   `.env` ni en instancias separadas del proyecto. Ajustes que varían por
   sucursal (hoy: `termino_orden`, cómo se le llama a una orden en el punto
   de venta) son columnas de `sucursales`, no texto libre en `localStorage`.
   La sucursal activa se comparte a todas las páginas Inertia como
   `sucursalActiva` (`HandleInertiaRequests`); hoy se toma la primera
   sucursal `activa` porque todavía no hay selección por sesión/usuario —
   cuando se agregue multi-sucursal real, ese es el único punto a tocar.
10. **La suscripción no se edita.** Una membresía vendida genera una fila en
    `suscripciones`, creada dentro de la misma transacción de la venta
    (`VentaController@store`) y ligada al `venta_detalle` que la originó.
    Renovar = fila **nueva** (nunca se extiende ni se modifica la anterior);
    el historial de renovaciones es simplemente la lista de filas del socio.
    Los cambios de estado (`activa | vencida | congelada | cancelada`) son
    el único campo mutable permitido y nunca alteran fechas ya pactadas.
    Si la venta se anula, la suscripción pasa a `cancelada`.
11. **El acceso físico está desacoplado.** `asistencias` es una tabla de
    eventos **append-only** (`cliente_id` nullable, `fecha_hora`,
    `dispositivo`, `resultado`, `origen`) que registra intentos de entrada.
    Nada en el sistema depende de que exista un terminal: hoy la alimenta
    recepción manualmente (`origen = manual`) y en la fase 2 la alimentará
    el terminal. El sistema decide si un socio _tiene derecho_ a entrar
    (suscripción vigente); el terminal solo ejecuta y reporta. Ningún
    código del dominio habla con el dispositivo ni con ISAPI hasta la fase 2.

## Estado actual

Esto es una **copia limpia** de Hiperfaz Gestión (sin historial git). Todo el
núcleo comercial existe y tiene pruebas; lo propio del gimnasio **aún no está
construido**.

### Stack real

Laravel 13, Inertia 3 + Vue 3, Fortify (login, 2FA y passkeys), Wayfinder
(rutas tipadas en `resources/js/routes` y `resources/js/actions`), Pest y CI en
`.github/workflows/tests.yml`. Cola con driver `database`. Suite actual: 190
pruebas; 1 falla de origen (`ProductoControllerTest` pide la ruta
`productos.edit`, que `routes/web.php` excluye con `except(['show','edit'])`).

### Roles

Hoy solo se siembra el usuario `admin` (acceso a todo); el rol `vendedor`
sigue existiendo en el código para cuando haya recepcionistas.
`users.rol` (`CHECK` en Postgres): `admin | vendedor`. El middleware `rol:admin`
protege catálogo (productos, categorías, unidades, proveedores, métodos de
pago), compras, reportes, sucursales y usuarios. Ventas, cierre de caja,
clientes y tasas de cambio están abiertos a cualquier usuario autenticado.

### Ya existe (heredado, funcional)

- **Ventas** (`VentaController`, `StoreVentaRequest`): `index`, `create`,
  `show`, `store` (detalle, descuento de inventario, pagos mixtos, vuelto,
  crédito en una transacción), `abonar` y `anular` (admin).
- **Cierre de caja** (`CierreCajaController`): `index` y `cerrarCaja`, con
  correo `App\Mail\CierreCajaCerrado` (Bs primero, con logo).
- **Cuentas por cobrar** (`ClienteController`, `clientes/Index.vue`, ruta
  `clientes.*`; antes "Mayoristas"): aquí vive todo lo fiado. Lista por defecto
  los clientes con deuda (filtro "Todos los clientes" para el resto), con sus
  ventas por cobrar desplegables, abono, compartir estado de cuenta, venta a
  crédito y datos de contacto, vía `App\Services\CobranzaService`. Está en el
  grupo Finanzas del menú. Ya no distingue mayoristas: `clientes.es_mayorista`
  quedó sin uso (la columna sigue en la base de datos, ningún código la lee;
  se puede quitar con una migración cuando se decida).
- **Catálogo y configuración** (admin): productos (con imagen) en páginas
  propias; categorías, unidades, proveedores, métodos de pago, sucursales y
  usuarios como pestañas de Configuración
  (`resources/js/layouts/settings/Layout.vue`, `resources/js/pages/settings/`),
  con lista paginada y alta/edición en modales.
- **Tasas de cambio** manual y automática (`ObtenerTasaBcvCommand`, cada hora
  06:00–12:00 hora de Caracas; `App\Services\TasaBcv` con `DolarApi`,
  `BcvScraping` y `Fallback`).
- **Compras** (`OrdenCompraController`, admin): la compra entra al inventario
  al crearla (`recibida_completa`), costo obligatorio por producto, USD/Bs,
  anulación con `ajuste_negativo` en el ledger. El esquema admite estados
  parciales que no se usan.
- **Reportes** (`ReporteController`, admin, JSON): ventas por período,
  desglose por método de pago, margen de ganancia y productos más vendidos.

### Falta (propio del gimnasio, en este orden)

1. ~~Seeders del rubro~~ (hecho): `DatabaseSeeder` siembra solo admin,
   sucursal, unidades y métodos de pago; el catálogo del gym va aparte en
   `GimnasioMenuSeeder`. Queda limpiar a mano `storage/app/public/productos`
   (imágenes de otros rubros) y textos/listas de categorías heredadas en
   `ProductoController`, `productos/Index.vue`, `ventas/Create.vue`,
   `CategoriaFactory` y `ProductoFactory`.
2. ~~Modelo de socios y membresías~~ (hecho, ver abajo).
3. Pantallas: alta de plan (hoy `planes` solo se siembra), vender/renovar
   membresía desde el punto de venta, estado de vigencia del socio, registro
   manual de asistencia desde recepción, listado de vencimientos.
4. ~~Anular ventas~~ (hecho): `POST ventas/{venta}/anular` (solo admin, motivo
   obligatorio) vía `App\Services\AnulacionVentaService`: devuelve el stock con
   movimientos `devolucion`, cancela la suscripción, descuenta la deuda a
   crédito y guarda `anulada_en/anulada_por_id/motivo_anulacion`. Solo se anula
   mientras la caja de la venta y de sus pagos siga abierta. El cierre de caja
   y "Ventas del día" excluyen las anuladas.
   ~~Marcar `vencida`~~ (hecho): comando diario `suscripciones:marcar-vencidas`
   (00:05 Caracas); el acceso sigue calculándose por fechas, el comando solo
   ordena `estado`, y `VENCE_EL_SQL` cuenta `activa` y `vencida`.
   `ClienteController` ya es "Cuentas por cobrar" y no filtra por mayorista.

### Separación gimnasio / ventas (decidido)

La barra lateral va en grupos: **Gimnasio** (Clientes, Asistencia, Por vencer,
Membresías vendidas), **Ventas** (Nueva venta, Ventas del día, Catálogo, Compras),
**Finanzas** (Cuentas por cobrar, Cierre de caja) y **Sistema** (Tasas de cambio, Configuración).
Las membresías se administran en Configuración > Membresías y no aparecen en
el catálogo de productos ni en Compras (`Producto::sinPlan()`); se cobran en
el punto de venta. "Ventas del día" cuenta solo productos (en una venta mixta
prorratea total y pagos a la parte de productos); "Membresías vendidas" cuenta
solo suscripciones y distingue alta (primera suscripción del socio) de
renovación. El cierre de caja sigue siendo el total del dinero que entró, y lo
parte en **productos** y **membresías** (`cierres_caja.total_membresias` y
`_bs`, fijados al cerrar con la tasa del cierre; productos = total − membresías;
el descuento de cada venta se reparte a prorrata entre sus líneas; `NULL` en
los cierres anteriores a la separación, que no muestran el desglose).
"Por vencer" (`VencimientoController`, `vencimientos.index`) lista a los
socios activos que vencen en los próximos 7 días y a los que vencieron en los
últimos 30 sin renovar, con botón "Renovar" hacia el punto de venta.

### Modelo de membresías (construido)

- `clientes` = las personas con membresía. En la interfaz se les llama
  **clientes** (nunca "socios"); los nombres internos de rutas, controladores y
  archivos (`socios.*`, `SocioController`, `pages/socios/`) quedaron con el
  nombre original para no romper nada. Se agregaron `fecha_nacimiento`, `foto` (reservada: hoy no se toma foto a los clientes y no hay subida ni pantalla que la use),
  `contacto_emergencia_nombre/telefono` e `id_acceso` (único, nullable, para
  enlazar con el terminal en la fase 2). La cédula es `documento`.
- `planes`: `producto_id` (único) + `duracion_dias` (`CHECK > 0`). Un producto
  con fila en `planes` es una membresía; `Producto::plan()`.
- `suscripciones`: `cliente_id`, `plan_id`, `venta_detalle_id` (único),
  `fecha_inicio`, `fecha_vencimiento` (último día con acceso, inclusive),
  `estado` con `CHECK`. El modelo lanza excepción si se intenta modificar
  cualquier cosa que no sea `estado`.
- `asistencias`: append-only (sin `updated_at`; el modelo bloquea update y
  delete), `CHECK` en `resultado` y `origen (manual | terminal)`.
- `VentaController@store` llama a `App\Services\SuscripcionService::emitir`
  dentro de la transacción: exige cliente y cantidad entera si hay un plan;
  comprar N unidades crea **una** suscripción de N × `duracion_dias`; si el
  socio ya tiene una vigente, la nueva empieza el día siguiente a su vencimiento.

### Fase 2 (no empezar)

Integración con el terminal Hikvision. No se conoce el modelo, cómo está
integrado hoy ni quién tiene la contraseña de admin. Hasta aclararlo no se
construye nada que hable con el dispositivo (ni ISAPI): solo se mantiene
`asistencias` y `clientes.id_acceso` listos para enchufarlo.

## Pendiente antes de producción

Obligatorio antes de dar el sistema por "en producción" con un cliente real:

1. **Backups:** instalar y programar `spatie/laravel-backup` (hoy no está en
   `composer.json`). Probar una restauración.
2. **Correo y colas:** `MAIL_MAILER=log` en `.env.example`; configurar SMTP
   real y encolar `CierreCajaCerrado` (hoy se envía síncrono desde
   `CierreCajaController`). Supervisor para `queue:work` y cron de
   `schedule:run` en el VPS (lo necesita también la tasa BCV).
3. **Despliegue documentado:** `docs/despliegue.md` con Nginx, PHP-FPM,
   Postgres, Supervisor y el alta de un cliente nuevo (carpeta + BD + dominio).

## Backlog heredado

- Pantalla de reportes que consuma los endpoints de `ReporteController`.
- **Anular ventas** (`ventas.estado = anulada` existe, sin ruta ni acción);
  debe revertir inventario con movimientos nuevos y, ahora, cancelar la
  suscripción asociada.
- Ajustes manuales de inventario (merma, conteo, daño).
- Stock mínimo y alerta de reposición; comando para reconstruir
  `stock_actual` desde el ledger; auditoría de acciones sensibles.
- Pruebas faltantes: `CobranzaService`, el `CHECK` de crédito y la
  reconstrucción del stock.
- Migración que simplifica `ordenes_compra.estado` y quita
  `orden_compra_detalle.cantidad_recibida`.
- `docs/pwa-ventas-offline.md` describe una PWA offline heredada del otro
  repo; **no aplica por ahora** a este proyecto.

## Convenciones de nombres

Todo el dominio (tablas, columnas, nombres de modelos en su forma de tabla,
variables de negocio) está en **español**: `productos`, `ventas`,
`suscripciones`, `asistencias`, `fecha_vencimiento`, etc. Sigue esta
convención en cualquier tabla, columna o endpoint nuevo — no mezclar con
inglés a mitad de camino.

## Convención de commits

En español, formato convencional (`feat(...)`, `fix(...)`, `docs:`...). Solo
se hacen commits cuando el usuario lo pide.
