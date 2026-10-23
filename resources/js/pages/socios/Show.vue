<script setup lang="ts">
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ArrowLeft, Pencil, ShoppingCart } from '@lucide/vue';
import { computed, onMounted, ref } from 'vue';
import {
    membresia as activarMembresia,
    update as actualizarSocio,
} from '@/actions/App/Http/Controllers/SocioController';
import { corregir as corregirFechas } from '@/actions/App/Http/Controllers/SuscripcionController';
import MembresiaFields from '@/components/socios/MembresiaFields.vue';
import SocioFormFields from '@/components/socios/SocioFormFields.vue';
import VigenciaBadge from '@/components/socios/VigenciaBadge.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import InputError from '@/components/InputError.vue';
import type { MetodoPagoOpcion, TasaVigente } from '@/lib/clientes';
import {
    CAMPOS_ERROR_MEMBRESIA,
    cobroEnEfectivo,
    ESTADO_SUSCRIPCION_ETIQUETAS,
    fmtDia,
    hayMembresia,
    membresiaVacia,
    textoRestante,
} from '@/lib/socios';
import type {
    CampoErrorMembresia,
    EstadoSuscripcion,
    PlanOpcion,
    Vigencia,
} from '@/lib/socios';
import { fmtHora, fmtUsd } from '@/lib/ventas';
import { index as clientesIndex } from '@/routes/clientes';
import { index } from '@/routes/socios';

interface Socio {
    id: number;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    id_acceso: string | null;
    saldo_pendiente: string;
    activo: boolean;
}

const props = defineProps<{
    socio: Socio;
    membresia: {
        vence_el: string | null;
        vigencia: Vigencia;
        dias_restantes: number | null;
    };
    suscripciones: {
        id: number;
        plan: string;
        fecha_inicio: string;
        fecha_vencimiento: string;
        estado: EstadoSuscripcion;
        numero_venta: string;
        corregida: boolean;
    }[];
    correcciones: {
        id: number;
        plan: string;
        usuario: string;
        motivo: string;
        anterior: [string, string];
        nueva: [string, string];
        fecha: string;
    }[];
    asistencias: {
        id: number;
        fecha_hora: string;
        resultado: 'permitido' | 'denegado';
        origen: 'manual' | 'terminal';
    }[];
    planes: PlanOpcion[];
    metodosPago: MetodoPagoOpcion[];
    tasaCambio: TasaVigente | null;
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: index() }],
    },
});

function fmtFechaHora(valor: string): string {
    const fecha = new Date(valor);

    return `${fecha.toLocaleDateString('es-VE', { day: '2-digit', month: 'short' })} ${fmtHora(valor)}`;
}

// --- Edición ----------------------------------------------------------------

const dialogAbierto = ref(false);

const form = useForm({
    nombre: '',
    documento: '',
    telefono: '',
    email: '',
    direccion: '',
    id_acceso: '',
    activo: true,
});

function abrirEditar() {
    form.clearErrors();
    form.nombre = props.socio.nombre;
    form.documento = props.socio.documento ?? '';
    form.telefono = props.socio.telefono ?? '';
    form.email = props.socio.email ?? '';
    form.direccion = props.socio.direccion ?? '';
    form.id_acceso = props.socio.id_acceso ?? '';
    form.activo = props.socio.activo;
    dialogAbierto.value = true;
}

function guardar() {
    form.put(actualizarSocio.url(props.socio.id), {
        preserveScroll: true,
        onSuccess: () => {
            dialogAbierto.value = false;
        },
    });
}

// --- Activar / renovar la membresía -------------------------------------------

const membresiaAbierta = ref(false);

const formMembresia = useForm(membresiaVacia());

const erroresMembresia = computed(() => {
    const errores = formMembresia.errors as Record<string, string>;

    return Object.fromEntries(
        CAMPOS_ERROR_MEMBRESIA.map((campo: CampoErrorMembresia) => [
            campo,
            errores[campo],
        ]),
    );
});

// en efectivo/divisas no se cobra si lo recibido no cubre el total
const recibidoInsuficiente = computed(
    () =>
        (cobroEnEfectivo(
            formMembresia,
            props.planes,
            props.metodosPago,
            props.tasaCambio,
        )?.falta ?? 0) > 0,
);

// --- Corregir las fechas de una membresía ---------------------------------------

const correccionAbierta = ref(false);
const suscripcionEnCorreccion = ref<(typeof props.suscripciones)[number] | null>(
    null,
);

const formCorreccion = useForm({
    fecha_inicio: '',
    fecha_vencimiento: '',
    motivo: '',
});

function abrirCorreccion(suscripcion: (typeof props.suscripciones)[number]) {
    suscripcionEnCorreccion.value = suscripcion;
    formCorreccion.reset();
    formCorreccion.clearErrors();
    formCorreccion.fecha_inicio = suscripcion.fecha_inicio.slice(0, 10);
    formCorreccion.fecha_vencimiento = suscripcion.fecha_vencimiento.slice(0, 10);
    correccionAbierta.value = true;
}

function guardarCorreccion() {
    if (!suscripcionEnCorreccion.value) {
        return;
    }

    formCorreccion.put(corregirFechas.url(suscripcionEnCorreccion.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            correccionAbierta.value = false;
        },
    });
}

function abrirMembresia() {
    formMembresia.reset();
    formMembresia.clearErrors();
    membresiaAbierta.value = true;
}

function cobrarMembresia() {
    formMembresia.post(activarMembresia.url(props.socio.id), {
        preserveScroll: true,
        onSuccess: () => {
            membresiaAbierta.value = false;
        },
    });
}

// "Renovar" en Por vencer llega aquí con ?renovar=1 y deja el diálogo abierto
onMounted(() => {
    if (new URLSearchParams(window.location.search).has('renovar')) {
        abrirMembresia();
    }
});

const textoVigente = computed(() =>
    props.membresia.vigencia === 'sin_membresia'
        ? 'Activar membresía'
        : 'Renovar membresía',
);

const DATOS: { etiqueta: string; valor: () => string | null }[] = [
    { etiqueta: 'Cédula', valor: () => props.socio.documento },
    { etiqueta: 'Teléfono', valor: () => props.socio.telefono },
    { etiqueta: 'Correo', valor: () => props.socio.email },
    { etiqueta: 'Dirección', valor: () => props.socio.direccion },
    { etiqueta: 'ID de acceso', valor: () => props.socio.id_acceso },
];
</script>

<template>
    <Head :title="`Detalles del cliente: ${socio.nombre}`" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <Link
            :href="index.url()"
            class="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-sm"
        >
            <ArrowLeft class="size-4" />
            Regresar a clientes
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">
                        Detalles del cliente: {{ socio.nombre }}
                    </h1>
                    <VigenciaBadge :vigencia="membresia.vigencia" />
                    <Badge v-if="!socio.activo" variant="secondary">
                        Inactivo
                    </Badge>
                </div>
                <p class="text-muted-foreground text-sm">
                    {{
                        textoRestante(
                            membresia.vigencia,
                            membresia.dias_restantes,
                        )
                    }}
                    <template v-if="membresia.vence_el">
                        · hasta el {{ fmtDia(membresia.vence_el) }}
                    </template>
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <Button size="sm" @click="abrirMembresia">
                    <ShoppingCart class="size-4" />
                    {{ textoVigente }}
                </Button>
                <Button size="sm" variant="outline" @click="abrirEditar">
                    <Pencil class="size-4" />
                    Editar
                </Button>
            </div>
        </div>

        <div
            v-if="Number(socio.saldo_pendiente) > 0"
            class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-amber-600/30 bg-amber-600/10 px-4 py-3 text-sm text-amber-700 dark:text-amber-400"
        >
            <span>
                Debe
                <strong>{{ fmtUsd(socio.saldo_pendiente) }}</strong>
            </span>
            <Link
                :href="clientesIndex.url({ query: { cliente: socio.id } })"
                class="font-medium underline"
            >
                Ver en cuentas por cobrar
            </Link>
        </div>

        <div class="grid gap-4 lg:grid-cols-3">
            <section class="rounded-xl border p-4">
                <h2 class="mb-3 text-sm font-semibold">Datos</h2>
                <dl class="grid gap-2 text-sm">
                    <div
                        v-for="dato in DATOS"
                        :key="dato.etiqueta"
                        class="flex justify-between gap-4"
                    >
                        <dt class="text-muted-foreground">
                            {{ dato.etiqueta }}
                        </dt>
                        <dd class="text-right">{{ dato.valor() ?? '—' }}</dd>
                    </div>
                </dl>
            </section>

            <section class="rounded-xl border p-4 lg:col-span-2">
                <h2 class="mb-3 text-sm font-semibold">Membresías</h2>
                <p
                    v-if="suscripciones.length === 0"
                    class="text-muted-foreground text-sm"
                >
                    Todavía no ha comprado ninguna membresía.
                </p>
                <table v-else class="w-full text-sm">
                    <thead>
                        <tr class="text-muted-foreground text-left">
                            <th class="py-1.5 font-medium">Plan</th>
                            <th class="py-1.5 font-medium">Desde</th>
                            <th class="py-1.5 font-medium">Hasta</th>
                            <th class="py-1.5 font-medium">Estado</th>
                            <th class="hidden py-1.5 font-medium sm:table-cell">
                                Venta
                            </th>
                            <th class="py-1.5"><span class="sr-only">Acciones</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="suscripcion in suscripciones"
                            :key="suscripcion.id"
                            class="border-t"
                        >
                            <td class="py-2">
                                {{ suscripcion.plan }}
                                <span
                                    v-if="suscripcion.corregida"
                                    class="text-muted-foreground block text-xs"
                                    >Fechas corregidas</span
                                >
                            </td>
                            <td class="py-2">
                                {{ fmtDia(suscripcion.fecha_inicio) }}
                            </td>
                            <td class="py-2">
                                {{ fmtDia(suscripcion.fecha_vencimiento) }}
                            </td>
                            <td class="py-2">
                                {{
                                    ESTADO_SUSCRIPCION_ETIQUETAS[
                                        suscripcion.estado
                                    ]
                                }}
                            </td>
                            <td
                                class="text-muted-foreground hidden py-2 sm:table-cell"
                            >
                                {{ suscripcion.numero_venta }}
                            </td>
                            <td class="py-2 text-right">
                                <Button
                                    v-if="suscripcion.estado !== 'cancelada'"
                                    size="sm"
                                    variant="ghost"
                                    @click="abrirCorreccion(suscripcion)"
                                >
                                    Corregir fechas
                                </Button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

        <section v-if="correcciones.length" class="rounded-xl border p-4">
            <h2 class="mb-3 text-sm font-semibold">
                Correcciones de fechas
            </h2>
            <ul class="grid gap-3 text-sm">
                <li
                    v-for="correccion in correcciones"
                    :key="correccion.id"
                    class="grid gap-0.5"
                >
                    <span>
                        {{ correccion.plan }}:
                        {{ fmtDia(correccion.anterior[0]) }} –
                        {{ fmtDia(correccion.anterior[1]) }}
                        →
                        <strong class="font-medium"
                            >{{ fmtDia(correccion.nueva[0]) }} –
                            {{ fmtDia(correccion.nueva[1]) }}</strong
                        >
                    </span>
                    <span class="text-muted-foreground text-xs">
                        {{ correccion.motivo }} · {{ correccion.usuario }} ·
                        {{ fmtFechaHora(correccion.fecha) }}
                    </span>
                </li>
            </ul>
        </section>

        <section class="rounded-xl border p-4">
            <h2 class="mb-3 text-sm font-semibold">Últimas entradas</h2>
            <p
                v-if="asistencias.length === 0"
                class="text-muted-foreground text-sm"
            >
                Sin entradas registradas.
            </p>
            <ul v-else class="grid gap-1.5 text-sm sm:grid-cols-2">
                <li
                    v-for="asistencia in asistencias"
                    :key="asistencia.id"
                    class="flex items-center justify-between gap-3"
                >
                    <span>{{ fmtFechaHora(asistencia.fecha_hora) }}</span>
                    <span
                        :class="
                            asistencia.resultado === 'permitido'
                                ? 'text-emerald-700 dark:text-emerald-400'
                                : 'text-red-700 dark:text-red-400'
                        "
                    >
                        {{
                            asistencia.resultado === 'permitido'
                                ? 'Permitida'
                                : 'Denegada'
                        }}
                    </span>
                </li>
            </ul>
        </section>
    </div>

    <Dialog v-model:open="correccionAbierta">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Corregir fechas</DialogTitle>
                <DialogDescription>
                    {{ suscripcionEnCorreccion?.plan }} · solo cambia las
                    fechas de la membresía, no lo cobrado. Queda registrado quién
                    lo corrigió y por qué.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-4" @submit.prevent="guardarCorreccion">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid gap-2">
                        <Label for="correccion-inicio">Desde</Label>
                        <Input
                            id="correccion-inicio"
                            v-model="formCorreccion.fecha_inicio"
                            type="date"
                        />
                        <InputError :message="formCorreccion.errors.fecha_inicio" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="correccion-fin">Hasta (último día)</Label>
                        <Input
                            id="correccion-fin"
                            v-model="formCorreccion.fecha_vencimiento"
                            type="date"
                            :min="formCorreccion.fecha_inicio || undefined"
                        />
                        <InputError
                            :message="formCorreccion.errors.fecha_vencimiento"
                        />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="correccion-motivo">Motivo</Label>
                    <Input
                        id="correccion-motivo"
                        v-model="formCorreccion.motivo"
                        maxlength="255"
                        placeholder="Ej.: se cargó un día de menos"
                        autocomplete="off"
                    />
                    <InputError :message="formCorreccion.errors.motivo" />
                </div>

                <Button type="submit" :disabled="formCorreccion.processing">
                    {{
                        formCorreccion.processing
                            ? 'Guardando...'
                            : 'Guardar corrección'
                    }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="membresiaAbierta">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-xl">
            <DialogHeader>
                <DialogTitle>{{ textoVigente }}</DialogTitle>
                <DialogDescription>
                    {{ socio.nombre }} · elige el plan y cómo paga. Si ya tiene
                    una membresía vigente, la nueva empieza al día siguiente de
                    que venza.
                </DialogDescription>
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="cobrarMembresia">
                <MembresiaFields
                    :modelo="formMembresia"
                    :planes="planes"
                    :metodos-pago="metodosPago"
                    :tasa="tasaCambio"
                    :errores="erroresMembresia"
                />

                <Button
                    type="submit"
                    :disabled="
                        formMembresia.processing ||
                        !hayMembresia(formMembresia) ||
                        recibidoInsuficiente ||
                        !tasaCambio
                    "
                >
                    {{
                        formMembresia.processing
                            ? 'Activando...'
                            : formMembresia.es_credito
                              ? 'Activar a crédito'
                              : 'Cobrar y activar'
                    }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog v-model:open="dialogAbierto">
        <DialogContent class="max-h-[90vh] overflow-y-auto sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Editar cliente</DialogTitle>
                <DialogDescription
                    >Actualiza los datos de
                    {{ socio.nombre }}.</DialogDescription
                >
            </DialogHeader>

            <form class="grid gap-6" @submit.prevent="guardar">
                <SocioFormFields :form="form" />

                <Button type="submit" :disabled="form.processing">
                    {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                </Button>
            </form>
        </DialogContent>
    </Dialog>
</template>
