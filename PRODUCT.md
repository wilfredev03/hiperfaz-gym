# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

El personal del propio negocio — típicamente el dueño y uno o dos empleados —
que alterna entre dos contextos con el mismo conjunto de personas: venta en
mostrador (tablet) y trabajo de trastienda/oficina — cierre de caja,
inventario, compras, reportes (laptop/desktop). No hay un rol de "solo
cajero" separado de "solo administrador": las mismas personas cambian de
tarea y de dispositivo a lo largo del día.

## Product Purpose

Sistema de gestión para un gimnasio en Venezuela: clientes con membresía
(vigencia, renovaciones, asistencia), ventas de mostrador (bebidas, batidos,
suplementos), cierre de caja, inventario y compras. Existe para que el
gimnasio reemplace hojas de cálculo, cuadernos y cálculos manuales de tasa de
cambio con un sistema único que cubre todo el ciclo: vender una membresía o
un producto, cobrar (incluyendo crédito informal), saber quién tiene derecho
a entrar y a quién le toca renovar, cerrar caja y reponer inventario. Éxito
significa que el cierre de caja diario cuadra sin trabajo manual adicional,
que recepción sabe en segundos si un cliente puede entrar y que el dueño
puede confiar en los reportes de margen e inventario sin reconciliar a mano.

## Positioning

Nace de un sistema comercial genérico para venta al detal y conserva su
modelo de datos configurable (categorías, unidades de medida, si un producto
controla inventario): una membresía es un producto más, con duración, que se
vende por el mismo flujo que una bebida o un suplemento. El sistema decide
si un cliente tiene derecho a entrar; el control de acceso físico queda
desacoplado (ver CLAUDE.md). Su mecanismo distintivo frente a un POS
genérico es el manejo nativo de la economía venezolana de comercio pequeño: doble moneda
(USD/VES) con tasa BCV, pagos combinados en una misma venta (parte efectivo,
parte pago móvil, etc.), y fiado/crédito informal a clientes conocidos como
flujo de primera clase, no como una excepción.

## Operating Context

- **Mostrador (venta):** tablet, ritmo rápido, cobro con métodos de pago
  mixtos y posible fiado a un cliente conocido.
- **Trastienda/oficina:** laptop o desktop, para cierre de caja al final del
  día, gestión de inventario y compras, y reportes.
- El mismo personal usa ambos contextos, por lo que las pantallas deben
  funcionar bien tanto en tablet como en desktop, no son roles ni
  dispositivos separados por persona.
- Despliegue self-hosted en VPS por cliente (una carpeta, una base de datos,
  un dominio por negocio) — no hay onboarding tipo SaaS multi-tenant ni
  selección de "workspace".
- Multi-sucursal por negocio: un mismo negocio puede operar varias
  sucursales, cada una con su propia terminología de "orden" (Orden, Mesa,
  Pedido) y su propio flujo de caja.

## Capabilities and Constraints

- Cliente de referencia actual: un gimnasio con control de acceso físico
  administrado por un tercero. El sistema no debe contener lógica específica
  de rubro en el código: lo propio del gimnasio vive en los datos (planes,
  categorías) y en el modelo de suscripciones.
- Doble moneda (USD y VES) con conversión por tasa BCV (automática o
  manual); los métodos de pago (efectivo USD, efectivo Bs, pago móvil,
  transferencia, Zelle, etc.) son configurables, no hardcodeados.
- Histórico inmutable: una venta ya creada nunca se edita ni se recalcula
  con precios/costos/tasas actuales.
- Inventario como ledger append-only, no como contador editable.
- Cierre de caja diario sin montos digitados: total vendido (partido en
  productos y membresías) y desglose por método de pago con el dinero que
  efectivamente entró; las ventas a crédito no suman ahí, solo los abonos del
  día en que llegan.
- Todo el dominio (tablas, columnas, términos de negocio) está en español.
- Sin requisito de accesibilidad específico más allá de accesibilidad web
  estándar — el personal se adapta a interfaces de gestión normales, sin
  necesidad de simplificación adicional por baja alfabetización tecnológica.

## Brand Commitments

Se vende bajo la marca **Hiperfaz Gym**. Hoy no existe identidad visual definida
(logo, paleta) más allá del favicon genérico del starter kit — la identidad
visual queda abierta para trabajo de diseño posterior, no es una restricción
vinculante todavía.

## Evidence on Hand

No hay testimonios, casos de estudio, datos de demostración ni prensa reales
todavía — el trabajo de diseño futuro no debe inventar contenido de este
tipo (nombres de clientes, cifras de ventas de ejemplo con apariencia real,
etc.) para las superficies operativas o de marketing.

## Product Principles

1. **Nunca lógica de negocio hardcodeada por rubro.** Categorías, unidades
   de medida y si un producto controla inventario son siempre datos
   configurables, nunca ramas de código por tipo de negocio.
2. **El histórico no se toca.** Ventas, pagos y movimientos de inventario ya
   registrados son inmutables; correcciones y abonos posteriores se
   registran como filas nuevas, nunca como edición retroactiva.
3. **La economía real del negocio venezolano es un caso de primera clase.**
   Doble moneda, pagos mixtos en una sola venta y fiado informal no son
   excepciones a soportar — son el flujo esperado.
4. **Mismo usuario, dos contextos.** Toda pantalla operativa debe considerar
   que la misma persona puede estar usándola desde una tablet en el
   mostrador o desde un desktop en la oficina, en el mismo día.
5. **Cada negocio es una instalación aislada.** Multi-sucursal vive dentro
   de un negocio; no hay multi-tenancy compartido entre negocios distintos.
