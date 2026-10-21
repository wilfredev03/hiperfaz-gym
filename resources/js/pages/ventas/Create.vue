<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { Head, useForm, useHttp, usePage } from '@inertiajs/vue3';
import {
    AlertTriangle,
    Check,
    ChevronLeft,
    HandCoins,
    Leaf,
    Loader2,
    Milk,
    Plus,
    RefreshCw,
    Trash2,
    UserRound,
    Wallet,
    X,
} from '@lucide/vue';
import { toast } from 'vue-sonner';
import { store as guardarVenta } from '@/actions/App/Http/Controllers/VentaController';
import AlertError from '@/components/AlertError.vue';
import CantidadStepper from '@/components/ventas/CantidadStepper.vue';
import ProductoSelector from '@/components/ventas/ProductoSelector.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';
import { textoDuracion } from '@/lib/socios';
import { esMetodoEfectivo, etiquetaMetodo, iconoMetodo } from '@/lib/ventas';
import { create as crearVenta } from '@/routes/ventas';
import { cargar_automatica as cargarTasaAutomatica } from '@/routes/tasas_cambio';

interface Categoria {
    id: number;
    nombre: string;
}

interface UnidadMedida {
    id: number;
    nombre: string;
    abreviatura: string;
}

interface ProductoOption {
    id: number;
    nombre: string;
    precio_venta: string;
    stock_actual: string;
    controla_inventario: boolean;
    categoria: Categoria | null;
    unidad_medida: UnidadMedida;
    imagen_url: string | null;
    // presente solo en los planes de membresía
    plan: { duracion_dias: number } | null;
}

interface ClienteOption {
    id: number;
    nombre: string;
    documento: string | null;
    saldo_pendiente: string;
}

interface MetodoPagoOption {
    id: number;
    nombre: string;
    moneda: 'USD' | 'VES';
    requiere_referencia: boolean;
}

interface TasaCambioActual {
    tasa: string;
    fecha: string;
}

interface VentaCreada {
    numero_venta: string;
    total: string;
    vuelto: string | null;
}

interface NuevaVentaForm {
    es_credito: boolean;
    cliente_id: number | null;
    items: { producto_id: number; cantidad: number }[];
    pagos: { metodo_pago_id: number; monto: number; referencia?: string }[];
    monto_recibido: number | null;
}

const props = defineProps<{
    // socio preseleccionado cuando se llega desde su ficha
    clienteInicial: number | null;
    productos: ProductoOption[];
    clientes: ClienteOption[];
    metodosPago: MetodoPagoOption[];
    tasaCambio: TasaCambioActual | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Nueva venta', href: crearVenta() }],
    },
});

const page = usePage();

// Las unidades de peso/volumen aceptan cantidades decimales (0.850 kg); el
// resto se vende por pieza entera. No hay una bandera en unidades_medida
// para esto todavía, así que se infiere por la abreviatura.
const UNIDADES_FRACCIONABLES = new Set(['kg', 'g', 'l', 'ml']);

function esFraccionable(abreviatura: string): boolean {
    return UNIDADES_FRACCIONABLES.has(abreviatura.trim().toLowerCase());
}

// caso "Batido de Fresa" / "Batido de Fresa con Leche": son productos
// distintos en el catálogo (cada uno con su propio precio y costo), pero
// visualmente comparten nombre una vez se quita "con Leche" — para no
// mostrar dos cards idénticas se deja el nombre igual y la diferencia se
// marca con una etiqueta aparte ("Natural" / "Con leche"). Solo aplica
// cuando de verdad existen ambas variantes de un mismo producto base en la
// misma categoría; si no hay ambigüedad no se muestra ninguna etiqueta.
const SUFIJO_CON_LECHE = /\s+con\s+leche$/i;

function nombreBase(nombre: string): string {
    return nombre.replace(SUFIJO_CON_LECHE, '').trim();
}

function claveVariante(producto: ProductoOption): string | null {
    if (!producto.categoria) {
        return null;
    }
    return `${producto.categoria.id}:${nombreBase(producto.nombre).toLowerCase()}`;
}

const clavesConAmbasVariantes = computed(() => {
    const variantesPorClave = new Map<string, Set<'natural' | 'con-leche'>>();
    for (const producto of props.productos) {
        const clave = claveVariante(producto);
        if (!clave) {
            continue;
        }
        const variante = SUFIJO_CON_LECHE.test(producto.nombre)
            ? 'con-leche'
            : 'natural';
        if (!variantesPorClave.has(clave)) {
            variantesPorClave.set(clave, new Set());
        }
        variantesPorClave.get(clave)!.add(variante);
    }
    const conAmbas = new Set<string>();
    for (const [clave, variantes] of variantesPorClave) {
        if (variantes.size > 1) {
            conAmbas.add(clave);
        }
    }
    return conAmbas;
});

function etiquetaVariante(producto: ProductoOption): string | null {
    const clave = claveVariante(producto);
    if (!clave || !clavesConAmbasVariantes.value.has(clave)) {
        return null;
    }
    return SUFIJO_CON_LECHE.test(producto.nombre) ? 'Leche' : 'Natural';
}

function iconoVariante(etiqueta: string) {
    return etiqueta === 'Leche' ? Milk : Leaf;
}

// el cartón de leche es angosto dentro de su propio viewBox: a igual tamaño
// de caja pesa visualmente menos que la hoja, así que se agranda un poco
// para que ambos íconos se perciban del mismo tamaño
function tamanoIconoVariante(etiqueta: string): string {
    return etiqueta === 'Leche' ? 'scale-125' : '';
}

// nombre + etiqueta ya resueltos para mostrar en la card del producto
function nombreParaMostrar(producto: ProductoOption): string {
    return etiquetaVariante(producto)
        ? nombreBase(producto.nombre)
        : producto.nombre;
}

function fmtUsd(valor: number): string {
    return `$${valor.toFixed(2)}`;
}

function fmtBs(valor: number): string {
    return `Bs. ${valor.toFixed(2)}`;
}

// el cliente paga en Bs, así que en toda la pantalla el bolívar va primero y
// el dólar queda como referencia entre paréntesis
function fmtDual(valorUsd: number): string {
    if (!tasaActual.value) {
        return fmtUsd(valorUsd);
    }
    return `${fmtBs(valorUsd * tasaActual.value)} (${fmtUsd(valorUsd)})`;
}

const tasaActual = computed(() =>
    props.tasaCambio ? Number(props.tasaCambio.tasa) : null,
);

// el backend bloquea facturar si la tasa activa no es la de hoy (ver
// VentaController::store); esto solo adelanta el mismo aviso al cargar la
// pantalla, en vez de que el cajero arme todo el carrito y se entere recién
// al intentar cobrar
function esFechaDeHoyUtc(fechaIso: string): boolean {
    const fecha = new Date(fechaIso);
    const hoy = new Date();
    return (
        fecha.getUTCFullYear() === hoy.getFullYear() &&
        fecha.getUTCMonth() === hoy.getMonth() &&
        fecha.getUTCDate() === hoy.getDate()
    );
}

const tasaVigenteHoy = computed(
    () => !!props.tasaCambio && esFechaDeHoyUtc(props.tasaCambio.fecha),
);

// "Cargar tasa" intenta directamente la misma carga automática del BCV que
// usa Tasas de cambio > Cargar del BCV, sin abrir ningún modal: el cajero no
// tiene por qué saber la tasa de memoria.
const cargaTasaAutomatica = useForm({});

function cargarTasa() {
    cargaTasaAutomatica.post(cargarTasaAutomatica.url(), {
        preserveScroll: true,
    });
}

// --- Órdenes en curso --------------------------------------------------
// Pensado para un mostrador con varias ventas armándose a la vez: lo que se
// toca a la izquierda cae en la orden "activa" de la derecha. El término
// ("Orden", "Mesa"...) es configurable porque el mismo sistema genérico
// puede terminar sirviendo a cualquier negocio — nunca se hardcodea el rubro.

interface LineaCarrito {
    producto_id: number;
    nombre: string;
    etiquetaVariante: string | null;
    precioUnitario: number;
    cantidad: string;
    unidadAbreviatura: string;
    fraccionable: boolean;
    // en qué unidad teclea el cajero para productos fraccionables (kg): la
    // pesa electrónica muestra gramos, así que por defecto se captura en 'g'
    // y se convierte a kg (la unidad real del producto) al vuelo. Irrelevante
    // para productos no fraccionables.
    unidadEntrada: 'g' | 'kg';
    stockActual: number;
    controlaInventario: boolean;
    // días que dura la membresía si la línea es un plan; null en un producto común
    duracionDias: number | null;
}

interface BorradorPago {
    metodo: MetodoPagoOption;
    montoTexto: string | number;
    referencia: string;
    // lo que le corresponde a este método según el reparto (en USD), fijo
    // hasta el próximo reparto — es el techo hasta donde "montoTexto"
    // cuenta como pago; lo que pase de ahí es vuelto
    montoObjetivoUsd: number;
}

interface Orden {
    id: number;
    carrito: LineaCarrito[];
    // cada mesa recuerda su propia pantalla (carrito o cobro) y sus pagos a
    // medio marcar: cambiar de mesa nunca debe botar el cobro que ya se
    // estaba armando en otra — por eso vive aquí y no en un ref global.
    vistaPanel: 'carrito' | 'cobro';
    pagosEnEdicion: BorradorPago[];
    // fiado: se decide explícitamente por mesa, nunca como reacción a que el
    // monto no alcanzó — abrir crédito es una decisión de negocio, no un
    // fallback automático de un cobro incompleto.
    esCredito: boolean;
    clienteId: number | null;
}

const NUMERO_ORDENES = 6;

// se configura por sucursal en Sucursales > Editar, no aquí: así todos los
// cajeros de una misma sucursal ven el mismo término sin tener que
// escribirlo cada vez que entran
const terminoOrden = computed(
    () => page.props.sucursalActiva?.termino_orden || 'Orden',
);

function etiquetaOrden(orden: Orden): string {
    return `${terminoOrden.value} ${orden.id}`;
}

function subtituloOrden(orden: Orden): string {
    if (!orden.carrito.length) {
        return 'Vacía';
    }
    const cantidad = orden.carrito.length;
    const palabra = cantidad === 1 ? 'producto' : 'productos';
    return `${cantidad} ${palabra} · ${fmtUsd(subtotalOrden(orden))}`;
}

function crearOrden(id: number, carrito: LineaCarrito[] = []): Orden {
    return {
        id,
        carrito,
        vistaPanel: 'carrito',
        pagosEnEdicion: [],
        esCredito: false,
        clienteId: null,
    };
}

// Mesa 1 a NUMERO_ORDENES son posiciones fijas: cada una es `null`
// (placeholder, todavía no se usó) o una Orden con su propio carrito.
// Cualquier mesa vacía (menos la 1, que siempre debe existir para tener
// dónde agregar productos) se puede activar o devolver a placeholder de
// forma independiente, sin afectar la numeración de las demás.
const mesas = ref<(Orden | null)[]>(
    Array.from({ length: NUMERO_ORDENES }, (_, i) =>
        i === 0 ? crearOrden(1) : null,
    ),
);

const ordenActivaIndex = ref(0);
// Mesa 1 nunca se puede quitar (ver puedeVolverAPlaceholder), así que
// siempre hay una orden real a la que volver si el índice activo cae fuera.
const ordenActiva = computed(
    () => mesas.value[ordenActivaIndex.value] ?? (mesas.value[0] as Orden),
);

const CLAVE_ORDENES_GUARDADAS = 'ventas.ordenesEnCurso';

// Un refresh accidental de la página no debe borrar órdenes que ya tienen
// productos cargados. Se guarda solo en este dispositivo (nunca al
// servidor): es un borrador de trabajo, no una venta real.
onMounted(() => {
    try {
        const guardado = localStorage.getItem(CLAVE_ORDENES_GUARDADAS);
        if (guardado) {
            const datos: unknown = JSON.parse(guardado);
            if (Array.isArray(datos)) {
                mesas.value = Array.from(
                    { length: NUMERO_ORDENES },
                    (_, i): Orden | null => {
                        const valor = datos[i] as
                            | { carrito?: unknown }
                            | null
                            | undefined;
                        if (!valor || !Array.isArray(valor.carrito)) {
                            return null;
                        }
                        return crearOrden(
                            i + 1,
                            valor.carrito as LineaCarrito[],
                        );
                    },
                );
            }
        }
    } catch {
        // datos corruptos o localStorage no disponible: se sigue con el
        // arranque en blanco de arriba
    }
    // Mesa 1 siempre debe quedar activa, tenga o no productos guardados.
    if (!mesas.value[0]) {
        mesas.value[0] = crearOrden(1);
    }
    const primeraActiva = mesas.value.findIndex((m) => m !== null);
    ordenActivaIndex.value = primeraActiva === -1 ? 0 : primeraActiva;
});

watch(
    mesas,
    (valor) => {
        localStorage.setItem(CLAVE_ORDENES_GUARDADAS, JSON.stringify(valor));
    },
    { deep: true },
);

// El panel de la derecha siempre muestra la orden activa: o su carrito, o
// (al tocar "Cobrar") sus métodos de pago — eso ahora es un dato de la
// propia Orden (ver interface), no un ref global: cambiar de mesa nunca
// debe botar un cobro que ya se estaba armando en otra.
function seleccionarOrden(index: number) {
    ordenActivaIndex.value = index;
}

function activarOrden(numero: number) {
    const index = numero - 1;
    if (!mesas.value[index]) {
        mesas.value[index] = crearOrden(numero);
    }
    ordenActivaIndex.value = index;
}

function limpiarOrden(index: number) {
    const orden = mesas.value[index];
    if (orden) {
        orden.carrito = [];
    }
}

// La mesa 1 nunca se puede quitar (siempre debe haber dónde agregar
// productos); el resto se puede devolver a placeholder mientras esté vacía.
function puedeVolverAPlaceholder(index: number): boolean {
    const orden = mesas.value[index];
    return index !== 0 && orden !== null && orden.carrito.length === 0;
}

function volverAPlaceholder(index: number) {
    if (!puedeVolverAPlaceholder(index)) {
        return;
    }
    mesas.value[index] = null;
    if (ordenActivaIndex.value === index) {
        seleccionarOrden(0);
    }
}

// --- Buscador de productos -------------------------------------------------
// La búsqueda, los chips de categoría y la cuadrícula viven en
// ProductoSelector (compartido con la venta a mayoristas); aquí solo se decide
// cómo se ordenan y se nombran en el mostrador.

// los chips de categoría van en orden alfabético: el código nunca conoce los
// nombres de las categorías de un negocio (principio #4)
function compararCategoriasChips(a: Categoria, b: Categoria): number {
    return a.nombre.localeCompare(b.nombre, 'es');
}

// --- Carrito (siempre el de la orden activa) --------------------------------

function agregarProducto(producto: ProductoOption) {
    const carrito = ordenActiva.value.carrito;
    const existente = carrito.find((l) => l.producto_id === producto.id);
    if (existente) {
        const actual = Number(existente.cantidad) || 0;
        const nueva = existente.fraccionable
            ? actual + 1
            : Math.round(actual) + 1;
        existente.cantidad = String(nueva);
    } else {
        const fraccionable = esFraccionable(producto.unidad_medida.abreviatura);
        carrito.push({
            producto_id: producto.id,
            nombre: producto.plan
                ? `${nombreParaMostrar(producto)} · ${textoDuracion(producto.plan.duracion_dias)}`
                : nombreParaMostrar(producto),
            etiquetaVariante: etiquetaVariante(producto),
            precioUnitario: Number(producto.precio_venta),
            cantidad: '1',
            unidadAbreviatura: producto.unidad_medida.abreviatura,
            fraccionable,
            unidadEntrada: fraccionable ? 'g' : 'kg',
            stockActual: Number(producto.stock_actual),
            controlaInventario: producto.controla_inventario,
            duracionDias: producto.plan?.duracion_dias ?? null,
        });
    }
}

function cantidadEnCarrito(productoId: number): number {
    const linea = ordenActiva.value.carrito.find(
        (l) => l.producto_id === productoId,
    );
    return linea ? Number(linea.cantidad) || 0 : 0;
}

function quitarLinea(orden: Orden, index: number) {
    orden.carrito.splice(index, 1);
}

// etiqueta del badge "ya en el carrito" sobre la tarjeta del producto: en
// gramos para fraccionables (igual que el input), unidad entera para el resto
function etiquetaCantidadCarrito(productoId: number): string {
    const linea = ordenActiva.value.carrito.find(
        (l) => l.producto_id === productoId,
    );
    if (!linea) return '';
    if (linea.fraccionable) {
        return linea.unidadEntrada === 'g'
            ? `${Math.round((Number(linea.cantidad) || 0) * 1000)}g`
            : `${Number(linea.cantidad) || 0}kg`;
    }
    return String(Number(linea.cantidad) || 0);
}

function badgeEnCarrito(productoId: number): string {
    return cantidadEnCarrito(productoId) > 0
        ? etiquetaCantidadCarrito(productoId)
        : '';
}

function subtotalLinea(linea: LineaCarrito): number {
    return (Number(linea.cantidad) || 0) * linea.precioUnitario;
}

function excedeStock(linea: LineaCarrito): boolean {
    return (
        linea.controlaInventario &&
        (Number(linea.cantidad) || 0) > linea.stockActual
    );
}

function subtotalOrden(orden: Orden): number {
    return orden.carrito.reduce((acc, linea) => acc + subtotalLinea(linea), 0);
}

// --- Cobro dentro del panel de la orden activa ------------------------------
// "Cobrar" no abre una pantalla nueva: dentro del mismo panel aparecen el
// total y los métodos de pago. Se pueden marcar varios métodos a la vez
// (ej. Pago Móvil + Efectivo) — cada uno marcado muestra su propia
// gestión (referencia y/o monto) debajo de la grilla, y un solo botón
// "Cobrar" procesa todos los pagos marcados juntos.
// Mientras tanto el buscador de productos queda atenuado para no distraer.
// NOTA: esta vista rápida no cubre crédito/fiado; eso necesitará su propio
// flujo más adelante.

function iniciarCobro() {
    if (!ordenActiva.value.carrito.length) {
        toast.error('Agrega al menos un producto a la orden.');
        return;
    }
    ordenActiva.value.pagosEnEdicion = [];
    ordenActiva.value.esCredito = false;
    ordenActiva.value.clienteId = carritoTieneMembresia.value
        ? props.clienteInicial
        : null;
    ordenActiva.value.vistaPanel = 'cobro';
}

function cancelarCobro() {
    ordenActiva.value.vistaPanel = 'carrito';
    ordenActiva.value.pagosEnEdicion = [];
    ordenActiva.value.esCredito = false;
    ordenActiva.value.clienteId = null;
}

// no hay descuento en esta pantalla; el total es el subtotal de la orden activa
const total = computed(() => subtotalOrden(ordenActiva.value));

// --- Métodos marcados ----------------------------------------------------
// Cada método marcado en la grilla tiene su propio borrador de pago: un
// único campo de monto (en la moneda del método — Bs para los que son en
// bolívares) y referencia si aplica. Al marcar o desmarcar un método se
// reparte el total en partes iguales entre los marcados, como punto de
// partida — cada monto se puede editar a mano después.
//
// En efectivo/divisas ese campo es "lo que entregó el cliente": si es
// justo lo que le corresponde a ese método, se aplica todo a la venta; si
// es más, el excedente es vuelto (nunca se aplica de más a la venta) y se
// muestra en la propia moneda del método (Bs para Efectivo, USD para
// Divisas) — nunca mezclado en una sola cifra. En Punto de Venta y Pago
// Móvil no hay vuelto: el campo es directamente lo que se aplica.

function estaMarcado(metodo: MetodoPagoOption): boolean {
    return ordenActiva.value.pagosEnEdicion.some(
        (p) => p.metodo.id === metodo.id,
    );
}

// convierte un monto en USD a la moneda del método (Bs si es VES)
function montoEnMonedaDe(valorUsd: number, metodo: MetodoPagoOption): number {
    if (metodo.moneda === 'VES' && tasaActual.value) {
        return Math.round(valorUsd * tasaActual.value * 100) / 100;
    }
    return Math.round(valorUsd * 100) / 100;
}

// convierte el monto escrito (en la moneda del método) a USD
function montoAUsd(valor: string | number, metodo: MetodoPagoOption): number {
    const numero = Number(valor);
    if (!Number.isFinite(numero) || numero < 0) {
        return 0;
    }
    if (metodo.moneda === 'VES' && tasaActual.value) {
        return numero / tasaActual.value;
    }
    return numero;
}

function redistribuirMontos() {
    const pagos = ordenActiva.value.pagosEnEdicion;
    const cantidad = pagos.length;
    if (cantidad === 0) {
        return;
    }
    const parteUsd = Math.round((total.value / cantidad) * 10000) / 10000;
    pagos.forEach((pago) => {
        pago.montoObjetivoUsd = parteUsd;
        pago.montoTexto = montoEnMonedaDe(parteUsd, pago.metodo);
    });
}

// sin tasa activa, montoEnMonedaDe/montoAUsd no pueden convertir Bs <-> USD
// y devolverían el número tal cual — cobrando en bolívares el mismo monto
// que en dólares. Mejor bloquear el método hasta que se registre una tasa
// (Tasas de cambio > Nueva) en vez de dejar que el cajero cobre de menos.
function requiereTasa(metodo: MetodoPagoOption): boolean {
    return metodo.moneda === 'VES' && !tasaActual.value;
}

// --- Crédito / fiado -----------------------------------------------------
// El switch es la única puerta de entrada: nunca se activa solo porque el
// monto asignado no alcanzó el total (ver comentario en la interface Orden).

const hayClientes = computed(() => props.clientes.length > 0);

// una membresía se le vende a un socio concreto (genera su suscripción), así
// que con un plan en el carrito el cliente es obligatorio también al contado
const carritoTieneMembresia = computed(() =>
    ordenActiva.value.carrito.some((linea) => linea.duracionDias !== null),
);

const necesitaCliente = computed(
    () => ordenActiva.value.esCredito || carritoTieneMembresia.value,
);

const clienteOptions = computed(() =>
    props.clientes.map((cliente) => ({
        value: String(cliente.id),
        label: cliente.documento
            ? `${cliente.nombre} (${cliente.documento})`
            : cliente.nombre,
    })),
);

const clienteSeleccionado = computed(
    () =>
        props.clientes.find(
            (cliente) => cliente.id === ordenActiva.value.clienteId,
        ) ?? null,
);

// el Combobox trabaja con string; el modelo de negocio (clienteId) es number|null
const clienteIdTexto = computed({
    get: () =>
        ordenActiva.value.clienteId !== null
            ? String(ordenActiva.value.clienteId)
            : '',
    set: (valor: string) => {
        ordenActiva.value.clienteId = valor ? Number(valor) : null;
    },
});

function alternarCredito(valor: boolean) {
    if (valor && !hayClientes.value) {
        toast.error(
            'No hay clientes registrados: crea uno en Clientes antes de fiar.',
        );
        return;
    }
    ordenActiva.value.esCredito = valor;
    if (!valor && !carritoTieneMembresia.value) {
        ordenActiva.value.clienteId = null;
    }
}

function alternarMetodo(metodo: MetodoPagoOption) {
    if (requiereTasa(metodo)) {
        toast.error(
            'No hay una tasa de cambio activa: registra una en Tasas de cambio antes de cobrar en bolívares.',
        );
        return;
    }
    const pagos = ordenActiva.value.pagosEnEdicion;
    const indice = pagos.findIndex((p) => p.metodo.id === metodo.id);
    if (indice !== -1) {
        pagos.splice(indice, 1);
    } else {
        pagos.push({
            metodo,
            montoTexto: 0,
            referencia: '',
            montoObjetivoUsd: 0,
        });
    }
    redistribuirMontos();
}

// lo que este método aplica a la venta: en efectivo/divisas nunca más de
// lo que le corresponde (el resto es vuelto); en el resto, tal cual se
// escribió
function montoAplicadoUsd(pago: BorradorPago): number {
    const escritoUsd = montoAUsd(pago.montoTexto, pago.metodo);
    if (!esMetodoEfectivo(pago.metodo)) {
        return escritoUsd;
    }
    return Math.min(escritoUsd, pago.montoObjetivoUsd);
}

// vuelto de esta línea puntual, en USD (para sumar al monto_recibido de
// la venta) — 0 en métodos que no son efectivo/divisas
function vueltoDeLineaUsd(pago: BorradorPago): number {
    if (!esMetodoEfectivo(pago.metodo)) {
        return 0;
    }
    const escritoUsd = montoAUsd(pago.montoTexto, pago.metodo);
    return Math.max(0, escritoUsd - pago.montoObjetivoUsd);
}

// el mismo vuelto de la línea, pero en la moneda del propio método (Bs
// para Efectivo, USD para Divisas) — así se muestra sin mezclar monedas
function vueltoDeLineaEnMoneda(pago: BorradorPago): number {
    return montoEnMonedaDe(vueltoDeLineaUsd(pago), pago.metodo);
}

// suma de lo que cada método marcado aplica a la venta (no lo que el
// cliente entregó de más, eso es vuelto y no cuenta como pago)
const totalAsignadoUsd = computed(() =>
    ordenActiva.value.pagosEnEdicion.reduce(
        (acc, pago) => acc + montoAplicadoUsd(pago),
        0,
    ),
);

const faltaPorAsignarUsd = computed(() =>
    Math.max(
        0,
        Math.round((total.value - totalAsignadoUsd.value) * 10000) / 10000,
    ),
);

// dinero asignado de más que NO es vuelto: solo efectivo/divisas pueden
// recibir de más (el excedente se resta arriba en montoAplicadoUsd y se
// muestra como vuelto). Un método sin vuelto (pago móvil, transferencia,
// punto de venta, Zelle...) no tiene tope — si el cajero teclea más de lo
// que le toca, ese exceso no tiene cómo devolverse y antes quedaba
// escondido porque faltaPorAsignarUsd solo mira diferencias positivas.
const sobranteSinVueltoUsd = computed(() => {
    const exceso = totalAsignadoUsd.value - total.value;
    return exceso > 0.005 ? Math.round(exceso * 10000) / 10000 : 0;
});

// dinero de más entregado, sumado en USD sin importar en qué moneda se
// entregó cada uno — solo para calcular el vuelto total de la venta que
// se manda al backend; lo que se le muestra al cajero es por método (ver
// vueltoDeLineaEnMoneda)
const vueltoTotalUsd = computed(() =>
    ordenActiva.value.pagosEnEdicion.reduce(
        (acc, pago) => acc + vueltoDeLineaUsd(pago),
        0,
    ),
);

// lo que le quedará debiendo el cliente si se confirma tal cual está el
// reparto — solo tiene sentido mostrarlo cuando la venta es a crédito
const quedaACreditoUsd = computed(() => faltaPorAsignarUsd.value);

// saldo del cliente después de esta venta (lo que ya debía + lo que queda
// a crédito de esta venta), para que el cajero vea la deuda acumulada antes
// de confirmar
const saldoClienteTrasVentaUsd = computed(() => {
    if (!clienteSeleccionado.value) {
        return 0;
    }
    return (
        Number(clienteSeleccionado.value.saldo_pendiente) +
        quedaACreditoUsd.value
    );
});

// el vaivén Bs -> USD al convertir puede dejar el reparto unos centavos
// por debajo del total por puro redondeo; con menos de medio centavo de
// diferencia ya se considera cubierto.
// En crédito el monto asignado puede ser parcial o cero a propósito (fiado
// completo): lo único obligatorio ahí es tener un cliente elegido.
const puedeCobrar = computed(() => {
    if (!tasaVigenteHoy.value) {
        return false;
    }
    if (necesitaCliente.value && ordenActiva.value.clienteId === null) {
        return false;
    }
    if (ordenActiva.value.esCredito) {
        return true;
    }
    return (
        ordenActiva.value.pagosEnEdicion.length > 0 &&
        faltaPorAsignarUsd.value <= 0.005 &&
        sobranteSinVueltoUsd.value <= 0.005
    );
});

// el cobro con métodos de pago se oculta por completo en una venta a
// crédito (ver template), así que ahí "falta por asignar" nunca aplica
const tituloMontoCobro = computed(() => {
    if (ordenActiva.value.esCredito) {
        return 'Total a crédito';
    }
    return ordenActiva.value.pagosEnEdicion.length
        ? 'Falta por asignar'
        : 'Total a cobrar';
});

function cobrar() {
    if (!puedeCobrar.value || http.processing) {
        toast.error(
            !tasaVigenteHoy.value
                ? 'No se puede facturar: falta registrar la tasa de cambio de hoy.'
                : necesitaCliente.value && ordenActiva.value.clienteId === null
                  ? carritoTieneMembresia.value
                      ? 'Selecciona el cliente al que se le vende la membresía.'
                      : 'Selecciona el cliente para registrar el crédito.'
                  : sobranteSinVueltoUsd.value > 0.005
                    ? 'Hay un monto de más en un método sin vuelto (pago móvil, transferencia, punto de venta...): ajústalo antes de cobrar.'
                    : 'Completa el monto de cada método antes de cobrar.',
        );
        return;
    }
    const pagos = ordenActiva.value.pagosEnEdicion
        .map((pago) => ({
            metodo_pago_id: pago.metodo.id,
            monto: Math.round(montoAplicadoUsd(pago) * 10000) / 10000,
            referencia: pago.referencia.trim() || undefined,
        }))
        // un método marcado con monto en 0 no aporta nada a la venta
        .filter((pago) => pago.monto > 0);

    const esCredito = ordenActiva.value.esCredito;
    // el vuelto solo existe en ventas al contado; en crédito el backend
    // ignora monto_recibido y nunca calcula vuelto
    const montoRecibido =
        !esCredito && vueltoTotalUsd.value > 0
            ? Math.round((total.value + vueltoTotalUsd.value) * 10000) / 10000
            : null;
    enviarVenta(pagos, montoRecibido, esCredito, ordenActiva.value.clienteId);
}

// --- Envío --------------------------------------------------------------

const http = useHttp<NuevaVentaForm, VentaCreada>({
    es_credito: false,
    cliente_id: null,
    items: [],
    pagos: [],
    monto_recibido: null,
});

const erroresGenerales = computed(() => Object.values(http.errors));

// compartida por el cobro de un solo método y el cobro dividido: arma la
// venta a partir de la orden activa y los pagos ya decididos
function enviarVenta(
    pagos: NuevaVentaForm['pagos'],
    montoRecibido: number | null = null,
    esCredito = false,
    clienteId: number | null = null,
) {
    const orden = ordenActiva.value;
    if (!orden.carrito.length || http.processing) {
        return;
    }

    http.es_credito = esCredito;
    http.cliente_id =
        esCredito || carritoTieneMembresia.value ? clienteId : null;
    http.items = orden.carrito.map((linea) => ({
        producto_id: linea.producto_id,
        cantidad: Number(linea.cantidad) || 0,
    }));
    http.pagos = pagos;
    http.monto_recibido = montoRecibido;

    http.post(guardarVenta.url(), {
        onSuccess: (data) => {
            const vuelto = Number(data.vuelto ?? 0);
            toast.success(
                esCredito
                    ? `Venta ${data.numero_venta} registrada a crédito.`
                    : vuelto > 0
                      ? `Venta ${data.numero_venta} registrada. Vuelto: ${fmtDual(vuelto)}`
                      : `Venta ${data.numero_venta} registrada.`,
            );
            orden.carrito = [];
            cancelarCobro();
        },
        onError: () => {
            toast.error('Revisa los datos de la venta.');
        },
    });
}
</script>

<template>
    <Head title="Nueva venta" />

    <div class="flex h-[calc(100dvh-4rem)] flex-col gap-3 p-4">
        <AlertError v-if="erroresGenerales.length" :errors="erroresGenerales" />

        <!-- aviso desde que se abre la pantalla, no recién al intentar
        cobrar: el backend bloquea facturar sin la tasa de hoy (ver
        VentaController::store), así que el cajero debe saberlo antes de
        armar todo el carrito -->
        <div
            v-if="!tasaVigenteHoy"
            class="flex flex-shrink-0 flex-wrap items-center gap-2"
        >
            <div
                class="flex flex-1 items-center gap-2 rounded-md border border-amber-600/30 bg-amber-600/10 px-3 py-2 text-sm text-amber-700"
            >
                <AlertTriangle class="size-4 shrink-0" />
                <span>No se puede facturar hasta colocar la tasa de hoy</span>
            </div>
            <Button
                class="h-auto flex-shrink-0 gap-1.5"
                :disabled="cargaTasaAutomatica.processing"
                @click="cargarTasa"
            >
                <Loader2
                    v-if="cargaTasaAutomatica.processing"
                    class="size-4 animate-spin"
                />
                <RefreshCw v-else class="size-4" />
                {{
                    cargaTasaAutomatica.processing
                        ? 'Cargando tasa...'
                        : 'Cargar tasa'
                }}
            </Button>
        </div>

        <!-- ================= tira de órdenes ================= -->
        <!-- una sola fila: caben ~4 chips, el resto se ve deslizando -->
        <div class="flex flex-shrink-0 gap-2 overflow-x-auto pb-1">
            <template v-for="(orden, idx) in mesas" :key="idx">
                <button
                    v-if="orden"
                    type="button"
                    class="relative flex w-[calc((100%-1.5rem)/4)] min-w-[132px] flex-none flex-col gap-0.5 rounded-lg border px-3.5 py-2.5 text-left transition-colors"
                    :class="
                        idx === ordenActivaIndex
                            ? 'border-primary bg-primary/5 shadow-sm'
                            : 'hover:bg-accent'
                    "
                    @click="seleccionarOrden(idx)"
                >
                    <span
                        class="text-sm font-semibold"
                        :class="idx === ordenActivaIndex ? 'text-primary' : ''"
                    >
                        {{ etiquetaOrden(orden) }}
                    </span>
                    <span class="text-muted-foreground text-xs">
                        {{ subtituloOrden(orden) }}
                    </span>

                    <button
                        v-if="puedeVolverAPlaceholder(idx)"
                        type="button"
                        title="Quitar esta orden vacía"
                        class="text-muted-foreground hover:bg-accent hover:text-foreground bg-background absolute -top-1.5 -right-1.5 flex size-5 items-center justify-center rounded-full border"
                        @click.stop="volverAPlaceholder(idx)"
                    >
                        <X class="size-3" />
                    </button>
                </button>

                <button
                    v-else
                    type="button"
                    class="border-muted-foreground/30 text-muted-foreground hover:border-primary/50 hover:bg-accent hover:text-primary flex w-[calc((100%-1.5rem)/4)] min-w-[132px] flex-none items-center gap-1.5 rounded-lg border-2 border-dashed bg-neutral-500/10 px-3.5 py-2.5 transition-colors"
                    @click="activarOrden(idx + 1)"
                >
                    <Plus class="size-4" />
                    <span class="text-sm font-medium">
                        {{ terminoOrden }} {{ idx + 1 }}
                    </span>
                </button>
            </template>
        </div>

        <!-- ================= productos + orden activa, altura fija ================= -->
        <div
            class="grid min-h-0 flex-1 grid-cols-2 gap-3 md:grid-cols-[1fr_1.3fr] md:gap-6"
        >
            <!-- panel de productos -->
            <Card
                class="flex min-h-0 min-w-0 flex-col py-3 transition-opacity duration-200 md:py-4"
                :class="
                    ordenActiva.vistaPanel === 'cobro'
                        ? 'pointer-events-none opacity-40'
                        : ''
                "
            >
                <CardContent
                    class="flex min-h-0 min-w-0 flex-1 flex-col gap-3 px-3 md:px-6"
                >
                    <ProductoSelector
                        :productos="productos"
                        :cantidad-label="badgeEnCarrito"
                        :nombre-de="nombreParaMostrar"
                        :comparar-categorias="compararCategoriasChips"
                        @agregar="agregarProducto"
                    >
                        <template #etiqueta="{ producto }">
                            <span
                                v-if="etiquetaVariante(producto)"
                                class="bg-accent/80 text-accent-foreground absolute top-1.5 left-1.5 z-10 flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[10px] font-medium backdrop-blur-sm"
                            >
                                {{ etiquetaVariante(producto) }}
                                <component
                                    :is="
                                        iconoVariante(
                                            etiquetaVariante(producto)!,
                                        )
                                    "
                                    class="size-3"
                                    :class="
                                        tamanoIconoVariante(
                                            etiquetaVariante(producto)!,
                                        )
                                    "
                                />
                            </span>
                        </template>
                    </ProductoSelector>
                </CardContent>
            </Card>

            <!-- panel de la orden activa -->
            <Card class="flex min-h-0 flex-col py-3 md:py-4">
                <CardContent
                    class="flex min-h-0 flex-1 flex-col gap-3 px-3 md:px-6"
                >
                    <div
                        class="flex flex-shrink-0 items-start justify-between gap-2"
                    >
                        <div>
                            <p class="text-muted-foreground text-xs">
                                Agregando a
                            </p>
                            <p class="text-base font-semibold">
                                {{ etiquetaOrden(ordenActiva) }}
                            </p>
                        </div>
                        <button
                            v-if="
                                ordenActiva.vistaPanel === 'carrito' &&
                                ordenActiva.carrito.length
                            "
                            type="button"
                            title="Vaciar esta orden"
                            class="text-destructive hover:bg-destructive/10 rounded p-1.5"
                            @click="limpiarOrden(ordenActivaIndex)"
                        >
                            <Trash2 class="size-4" />
                        </button>
                        <Label
                            v-if="ordenActiva.vistaPanel === 'cobro'"
                            for="switch-credito"
                            class="flex shrink-0 items-center gap-1.5 text-xs font-medium"
                        >
                            Crédito
                            <Switch
                                id="switch-credito"
                                class="scale-90"
                                :model-value="ordenActiva.esCredito"
                                @update:model-value="alternarCredito"
                            />
                        </Label>
                    </div>

                    <!-- ===== vista carrito ===== -->
                    <template v-if="ordenActiva.vistaPanel === 'carrito'">
                        <div class="min-h-0 flex-1 overflow-y-auto">
                            <p
                                v-if="!ordenActiva.carrito.length"
                                class="text-muted-foreground flex h-full items-center justify-center text-sm"
                            >
                                Sin productos todavía
                            </p>

                            <ul v-else class="divide-y rounded-xl border">
                                <li
                                    v-for="(
                                        linea, index
                                    ) in ordenActiva.carrito"
                                    :key="linea.producto_id"
                                    class="grid grid-cols-[1fr_auto] items-start gap-x-3 gap-y-1 px-3 py-2.5"
                                >
                                    <div class="min-w-0">
                                        <span
                                            v-if="linea.etiquetaVariante"
                                            class="bg-accent/80 text-accent-foreground mb-0.5 flex w-fit items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-medium backdrop-blur-sm"
                                        >
                                            {{ linea.etiquetaVariante }}
                                            <component
                                                :is="
                                                    iconoVariante(
                                                        linea.etiquetaVariante,
                                                    )
                                                "
                                                class="size-2.5"
                                                :class="
                                                    tamanoIconoVariante(
                                                        linea.etiquetaVariante,
                                                    )
                                                "
                                            />
                                        </span>
                                        <p
                                            class="flex min-w-0 items-center gap-1.5 text-sm font-medium"
                                        >
                                            <span>{{ linea.nombre }}</span>
                                            <AlertTriangle
                                                v-if="excedeStock(linea)"
                                                class="size-3.5 shrink-0 text-amber-600"
                                            />
                                        </p>
                                    </div>
                                    <p
                                        class="text-right text-sm font-semibold tabular-nums"
                                    >
                                        {{
                                            tasaActual
                                                ? fmtBs(
                                                      subtotalLinea(linea) *
                                                          tasaActual,
                                                  )
                                                : fmtUsd(subtotalLinea(linea))
                                        }}
                                    </p>

                                    <CantidadStepper
                                        v-model:cantidad="linea.cantidad"
                                        v-model:unidad-entrada="
                                            linea.unidadEntrada
                                        "
                                        :fraccionable="linea.fraccionable"
                                        :nombre="linea.nombre"
                                        @quitar="
                                            quitarLinea(ordenActiva, index)
                                        "
                                    />
                                    <p
                                        v-if="tasaActual"
                                        class="text-muted-foreground text-right text-xs tabular-nums"
                                    >
                                        {{ fmtUsd(subtotalLinea(linea)) }}
                                    </p>
                                </li>
                            </ul>
                        </div>

                        <div
                            class="flex flex-shrink-0 flex-col gap-2.5 border-t pt-3"
                        >
                            <div class="flex items-center justify-between">
                                <span class="text-muted-foreground text-sm">
                                    Total
                                </span>
                                <span class="text-xl font-bold">
                                    {{ fmtDual(subtotalOrden(ordenActiva)) }}
                                </span>
                            </div>
                            <Button
                                type="button"
                                size="lg"
                                class="h-11 w-full gap-2"
                                :disabled="!ordenActiva.carrito.length"
                                @click="iniciarCobro"
                            >
                                <Wallet class="size-4.5" />
                                Cobrar
                            </Button>
                        </div>
                    </template>

                    <!-- ===== vista cobro ===== -->
                    <!-- se pueden marcar varios métodos a la vez; cada uno
                    marcado muestra su gestión debajo de la grilla, y un
                    solo botón "Cobrar" procesa todos juntos -->
                    <template v-else>
                        <div
                            class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto"
                        >
                            <!-- fiado: puerta de entrada explícita, nunca un
                            fallback reactivo a que el monto no alcanzó (el
                            switch vive en la cabecera del panel, ver arriba).
                            Con ella activa esta pantalla solo abre el crédito
                            (sin cobro); el abono se registra después en
                            Cuentas por cobrar. -->
                            <!-- cliente: obligatorio solo si la venta es a
                            crédito; muestra su deuda actual y en cuánto
                            quedará tras esta venta, para que el cajero no
                            fíe a ciegas -->
                            <div
                                v-if="necesitaCliente"
                                class="flex flex-shrink-0 flex-col gap-1.5 rounded-lg border p-3"
                            >
                                <Label
                                    for="cliente-credito"
                                    class="flex items-center gap-1.5 text-xs"
                                >
                                    <UserRound class="size-3.5" />
                                    {{
                                        'Cliente'
                                    }}
                                </Label>
                                <Combobox
                                    id="cliente-credito"
                                    v-model="clienteIdTexto"
                                    :options="clienteOptions"
                                    placeholder="Busca un cliente..."
                                    empty-text="No se encontraron clientes."
                                />
                                <p
                                    v-if="
                                        clienteSeleccionado &&
                                        Number(
                                            clienteSeleccionado.saldo_pendiente,
                                        ) > 0
                                    "
                                    class="text-xs text-amber-700 dark:text-amber-400"
                                >
                                    Debe
                                    {{
                                        fmtUsd(
                                            Number(
                                                clienteSeleccionado.saldo_pendiente,
                                            ),
                                        )
                                    }}
                                    <template v-if="quedaACreditoUsd > 0.005">
                                        · con esta venta quedará en
                                        <span class="font-semibold">
                                            {{
                                                fmtUsd(saldoClienteTrasVentaUsd)
                                            }}
                                        </span>
                                    </template>
                                </p>
                            </div>

                            <div
                                class="flex flex-shrink-0 items-center justify-between"
                            >
                                <span class="text-muted-foreground text-sm">
                                    {{ tituloMontoCobro }}
                                </span>
                                <span class="text-xl font-bold">
                                    {{ fmtDual(faltaPorAsignarUsd) }}
                                </span>
                            </div>

                            <!-- el cobro con métodos de pago no aplica a una
                            venta a crédito: esta pantalla solo la abre, el
                            abono se registra aparte en Cuentas por cobrar -->
                            <template v-if="!ordenActiva.esCredito">
                                <p
                                    v-if="!tasaActual"
                                    class="flex flex-shrink-0 items-center gap-1.5 rounded-md border border-amber-600/30 bg-amber-600/10 px-2.5 py-1.5 text-xs text-amber-700"
                                >
                                    <AlertTriangle class="size-3.5 shrink-0" />
                                    <span class="truncate">
                                        Sin tasa activa: métodos en bolívares
                                        deshabilitados.
                                    </span>
                                </p>

                                <div
                                    class="grid flex-shrink-0 grid-cols-2 gap-2"
                                >
                                    <button
                                        v-for="metodo in metodosPago"
                                        :key="metodo.id"
                                        type="button"
                                        class="flex flex-col items-center justify-center gap-1.5 rounded-lg border p-4 transition-colors disabled:pointer-events-none disabled:opacity-50"
                                        :class="
                                            estaMarcado(metodo)
                                                ? 'border-primary bg-primary/5 text-primary shadow-sm'
                                                : 'hover:border-primary/50 hover:bg-accent'
                                        "
                                        :disabled="
                                            http.processing ||
                                            requiereTasa(metodo)
                                        "
                                        :title="
                                            requiereTasa(metodo)
                                                ? 'No hay tasa de cambio activa'
                                                : undefined
                                        "
                                        @click="alternarMetodo(metodo)"
                                    >
                                        <AlertTriangle
                                            v-if="requiereTasa(metodo)"
                                            class="size-5 text-amber-600"
                                        />
                                        <component
                                            v-else
                                            :is="iconoMetodo(metodo)"
                                            class="size-5"
                                        />
                                        <span class="text-xs font-medium">
                                            {{ etiquetaMetodo(metodo) }}
                                        </span>
                                    </button>
                                </div>

                                <!-- gestión de cada método marcado -->
                                <div
                                    v-if="ordenActiva.pagosEnEdicion.length"
                                    class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto"
                                >
                                    <div
                                        v-for="pago in ordenActiva.pagosEnEdicion"
                                        :key="pago.metodo.id"
                                        class="flex flex-col gap-2 rounded-lg border p-3"
                                    >
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <span class="text-sm font-semibold">
                                                {{
                                                    etiquetaMetodo(pago.metodo)
                                                }}
                                            </span>
                                            <button
                                                type="button"
                                                title="Quitar este método"
                                                class="text-muted-foreground hover:text-destructive"
                                                @click="
                                                    alternarMetodo(pago.metodo)
                                                "
                                            >
                                                <X class="size-3.5" />
                                            </button>
                                        </div>

                                        <div
                                            v-if="
                                                pago.metodo.requiere_referencia
                                            "
                                            class="grid gap-1"
                                        >
                                            <Label
                                                :for="`referencia-${pago.metodo.id}`"
                                                class="text-xs"
                                            >
                                                Referencia (opcional)
                                            </Label>
                                            <Input
                                                :id="`referencia-${pago.metodo.id}`"
                                                v-model="pago.referencia"
                                                class="h-9"
                                                placeholder="Ej. 001234"
                                                autocomplete="off"
                                            />
                                        </div>

                                        <div class="grid gap-1">
                                            <Label
                                                :for="`monto-${pago.metodo.id}`"
                                                class="text-xs"
                                            >
                                                {{
                                                    esMetodoEfectivo(
                                                        pago.metodo,
                                                    )
                                                        ? 'Monto recibido'
                                                        : 'Monto'
                                                }}
                                                ({{
                                                    pago.metodo.moneda === 'VES'
                                                        ? 'Bs'
                                                        : 'USD'
                                                }})
                                            </Label>
                                            <Input
                                                :id="`monto-${pago.metodo.id}`"
                                                v-model="pago.montoTexto"
                                                type="number"
                                                min="0"
                                                step="0.01"
                                                class="h-9"
                                            />
                                            <p
                                                v-if="
                                                    vueltoDeLineaEnMoneda(
                                                        pago,
                                                    ) > 0
                                                "
                                                class="text-xs"
                                            >
                                                Vuelto:
                                                <span class="font-semibold">
                                                    {{
                                                        pago.metodo.moneda ===
                                                        'VES'
                                                            ? fmtBs(
                                                                  vueltoDeLineaEnMoneda(
                                                                      pago,
                                                                  ),
                                                              )
                                                            : fmtUsd(
                                                                  vueltoDeLineaEnMoneda(
                                                                      pago,
                                                                  ),
                                                              )
                                                    }}
                                                </span>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <div
                                class="mt-auto flex flex-shrink-0 flex-col gap-2"
                            >
                                <p
                                    v-if="
                                        !ordenActiva.esCredito &&
                                        ordenActiva.pagosEnEdicion.length &&
                                        faltaPorAsignarUsd > 0.005
                                    "
                                    class="text-destructive text-xs"
                                >
                                    Falta {{ fmtDual(faltaPorAsignarUsd) }} por
                                    asignar entre los métodos marcados.
                                </p>
                                <p
                                    v-else-if="
                                        !ordenActiva.esCredito &&
                                        sobranteSinVueltoUsd > 0.005
                                    "
                                    class="text-destructive text-xs"
                                >
                                    Sobran
                                    {{ fmtDual(sobranteSinVueltoUsd) }} en un
                                    método sin vuelto (pago móvil,
                                    transferencia...): revisa los montos.
                                </p>
                                <p
                                    v-else-if="
                                        necesitaCliente &&
                                        !ordenActiva.clienteId
                                    "
                                    class="text-destructive text-xs"
                                >
                                    {{
                                        carritoTieneMembresia
                                            ? 'Selecciona el cliente para poder vender la membresía.'
                                            : 'Selecciona un cliente para poder registrar el crédito.'
                                    }}
                                </p>

                                <Button
                                    v-if="
                                        ordenActiva.esCredito ||
                                        ordenActiva.pagosEnEdicion.length
                                    "
                                    type="button"
                                    size="lg"
                                    class="h-11 w-full gap-2"
                                    :disabled="!puedeCobrar || http.processing"
                                    @click="cobrar"
                                >
                                    <Loader2
                                        v-if="http.processing"
                                        class="size-4.5 animate-spin"
                                    />
                                    <component
                                        v-else
                                        :is="
                                            ordenActiva.esCredito
                                                ? HandCoins
                                                : Check
                                        "
                                        class="size-4.5"
                                    />
                                    {{
                                        http.processing
                                            ? 'Procesando...'
                                            : ordenActiva.esCredito
                                              ? 'Registrar venta a crédito'
                                              : 'Cobrar'
                                    }}
                                </Button>
                                <button
                                    v-else
                                    type="button"
                                    class="text-muted-foreground hover:text-foreground flex items-center gap-1 self-start text-xs"
                                    @click="cancelarCobro"
                                >
                                    <ChevronLeft class="size-3.5" />
                                    Volver al carrito
                                </button>
                            </div>
                        </div>
                    </template>
                </CardContent>
            </Card>
        </div>
    </div>
</template>
