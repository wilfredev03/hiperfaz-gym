<script setup lang="ts">
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, DoorOpen, Pencil, ShoppingCart } from '@lucide/vue';
import { ref } from 'vue';
import { store as registrarEntrada } from '@/actions/App/Http/Controllers/AsistenciaController';
import { update as actualizarSocio } from '@/actions/App/Http/Controllers/SocioController';
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
import {
    ESTADO_SUSCRIPCION_ETIQUETAS,
    fmtDia,
    textoRestante,
} from '@/lib/socios';
import type { EstadoSuscripcion, Vigencia } from '@/lib/socios';
import { fmtHora, fmtUsd } from '@/lib/ventas';
import { index as clientesIndex } from '@/routes/clientes';
import { index } from '@/routes/socios';
import { create as crearVenta } from '@/routes/ventas';

interface Socio {
    id: number;
    nombre: string;
    documento: string | null;
    telefono: string | null;
    email: string | null;
    direccion: string | null;
    fecha_nacimiento: string | null;
    contacto_emergencia_nombre: string | null;
    contacto_emergencia_telefono: string | null;
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
    }[];
    asistencias: {
        id: number;
        fecha_hora: string;
        resultado: 'permitido' | 'denegado';
        origen: 'manual' | 'terminal';
    }[];
}>();

defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Clientes', href: index() }],
    },
});

const entrada = useForm({ cliente_id: props.socio.id });

function registrar() {
    entrada.post(registrarEntrada.url(), { preserveScroll: true });
}

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
    fecha_nacimiento: '',
    contacto_emergencia_nombre: '',
    contacto_emergencia_telefono: '',
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
    form.fecha_nacimiento = props.socio.fecha_nacimiento?.slice(0, 10) ?? '';
    form.contacto_emergencia_nombre =
        props.socio.contacto_emergencia_nombre ?? '';
    form.contacto_emergencia_telefono =
        props.socio.contacto_emergencia_telefono ?? '';
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

function irAVender() {
    router.get(crearVenta.url({ query: { cliente: props.socio.id } }));
}

const DATOS: { etiqueta: string; valor: () => string | null }[] = [
    { etiqueta: 'Cédula', valor: () => props.socio.documento },
    { etiqueta: 'Teléfono', valor: () => props.socio.telefono },
    { etiqueta: 'Correo', valor: () => props.socio.email },
    {
        etiqueta: 'Nacimiento',
        valor: () =>
            props.socio.fecha_nacimiento
                ? fmtDia(props.socio.fecha_nacimiento)
                : null,
    },
    { etiqueta: 'Dirección', valor: () => props.socio.direccion },
    {
        etiqueta: 'Emergencia',
        valor: () =>
            [
                props.socio.contacto_emergencia_nombre,
                props.socio.contacto_emergencia_telefono,
            ]
                .filter(Boolean)
                .join(' · ') || null,
    },
    { etiqueta: 'ID de acceso', valor: () => props.socio.id_acceso },
];
</script>

<template>
    <Head :title="socio.nombre" />

    <div class="flex flex-1 flex-col gap-4 p-4 sm:p-6">
        <Link
            :href="index.url()"
            class="text-muted-foreground hover:text-foreground inline-flex w-fit items-center gap-1.5 text-sm"
        >
            <ArrowLeft class="size-4" />
            Clientes
        </Link>

        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <h1 class="text-xl font-semibold">{{ socio.nombre }}</h1>
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
                <Button
                    size="sm"
                    variant="outline"
                    :disabled="entrada.processing"
                    @click="registrar"
                >
                    <DoorOpen class="size-4" />
                    Registrar entrada
                </Button>
                <Button size="sm" @click="irAVender">
                    <ShoppingCart class="size-4" />
                    Vender membresía
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
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="suscripcion in suscripciones"
                            :key="suscripcion.id"
                            class="border-t"
                        >
                            <td class="py-2">{{ suscripcion.plan }}</td>
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
                        </tr>
                    </tbody>
                </table>
            </section>
        </div>

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
