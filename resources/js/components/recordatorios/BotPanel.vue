<script setup lang="ts">
import { router, useForm } from '@inertiajs/vue3';
import { LogOut, RefreshCw, Send, Smartphone } from '@lucide/vue';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    bot,
    desconectarBot,
    prueba,
    reconectarBot,
} from '@/actions/App/Http/Controllers/RecordatorioController';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { EstadoBot } from '@/lib/recordatorios';

const emit = defineEmits<{
    conectado: [valor: boolean];
    // el estado de la sesión cambió (QR, conectado, caída...): hay algo nuevo en el historial
    cambio: [];
}>();

const estado = ref<EstadoBot>({
    conectado: false,
    estado: 'conectando',
    qr: null,
    qr_expira_en: null,
    numero: null,
});
let temporizador: number | undefined;
let reloj: number | undefined;
let primeraConsulta = true;

// cuenta regresiva del QR: el bot informa los segundos que quedan en cada
// consulta (cada 4 s) y entre consultas se descuenta aquí cada segundo
const restante = ref<number | null>(null);

const textoRestante = computed(() => {
    if (restante.value === null) {
        return '';
    }

    const m = Math.floor(restante.value / 60);
    const s = String(restante.value % 60).padStart(2, '0');

    return `${m}:${s}`;
});

// el QR de WhatsApp caduca a los pocos segundos y se renueva solo: hay que
// seguir preguntando mientras la página esté abierta
async function consultar() {
    if (document.visibilityState === 'hidden') {
        return;
    }

    try {
        const respuesta = await fetch(bot.url(), {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        if (respuesta.ok) {
            const anterior = estado.value.estado;
            estado.value = await respuesta.json();
            restante.value = estado.value.qr_expira_en;
            emit('conectado', estado.value.conectado);

            if (estado.value.estado !== anterior && !primeraConsulta) {
                emit('cambio');
            }
            primeraConsulta = false;
        }
    } catch {
        // sin red: se queda el último estado conocido
    }
}

onMounted(() => {
    consultar();
    temporizador = window.setInterval(consultar, 4000);
    reloj = window.setInterval(() => {
        if (restante.value !== null && restante.value > 0) {
            restante.value -= 1;
        }
    }, 1000);
});
onBeforeUnmount(() => {
    window.clearInterval(temporizador);
    window.clearInterval(reloj);
});

const ETIQUETAS: Record<EstadoBot['estado'], string> = {
    conectado: 'Conectado',
    esperando_qr: 'Esperando QR',
    conectando: 'Conectando...',
    desconectado: 'Desconectado',
    qr_expirado: 'QR expirado',
    sin_servicio: 'Servicio apagado',
    no_autorizado: 'Token incorrecto',
};

const reconectando = ref(false);

function generarQrNuevo() {
    router.post(
        reconectarBot.url(),
        {},
        {
            preserveScroll: true,
            onStart: () => (reconectando.value = true),
            onFinish: () => {
                reconectando.value = false;
                consultar();
            },
        },
    );
}

function cerrarSesion() {
    if (
        confirm(
            '¿Cerrar la sesión de WhatsApp? Los envíos se detendrán hasta que vincules un número escaneando el QR.',
        )
    ) {
        router.post(desconectarBot.url(), {}, { preserveScroll: true });
    }
}

// --- Mensaje de prueba -------------------------------------------------------

const dialogPrueba = ref(false);
const formPrueba = useForm({ telefono: '' });

function enviarPrueba() {
    formPrueba.post(prueba.url(), {
        preserveScroll: true,
        onSuccess: () => {
            dialogPrueba.value = false;
            formPrueba.reset();
        },
    });
}
</script>

<template>
    <div class="flex h-full flex-col gap-3 rounded-xl border p-4">
        <div class="flex items-center justify-between gap-2">
            <h2 class="flex items-center gap-2 font-semibold">
                <Smartphone class="size-4" />
                Sesión de WhatsApp
            </h2>
            <Badge :variant="estado.conectado ? 'default' : 'outline'">
                {{ ETIQUETAS[estado.estado] }}
            </Badge>
        </div>

        <template v-if="estado.conectado">
            <p class="text-sm">
                Número vinculado:
                <span class="font-medium">+{{ estado.numero }}</span>
            </p>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" size="sm" @click="dialogPrueba = true">
                    <Send class="size-4" />
                    Enviar prueba
                </Button>
                <Button variant="ghost" size="sm" @click="cerrarSesion">
                    <LogOut class="size-4" />
                    Cerrar sesión
                </Button>
            </div>
        </template>

        <template v-else-if="estado.qr">
            <img
                :src="estado.qr"
                alt="Código QR para vincular WhatsApp"
                class="mx-auto size-48 rounded-lg bg-white p-2"
            />
            <p class="text-center text-sm tabular-nums">
                Vence en
                <span
                    class="font-semibold"
                    :class="{ 'text-destructive': (restante ?? 99) <= 20 }"
                    >{{ textoRestante }}</span
                >
            </p>
            <p class="text-muted-foreground text-center text-xs">
                En el teléfono: WhatsApp > Dispositivos vinculados > Vincular un
                dispositivo, y escanea este código. La imagen se renueva sola;
                si se acaba el tiempo podrás pedir uno nuevo.
            </p>
        </template>

        <template v-else-if="estado.estado === 'qr_expirado'">
            <p class="text-muted-foreground text-sm">
                El código QR expiró sin que nadie lo escaneara. Por seguridad
                no se deja abierto: genera uno nuevo cuando tengas el teléfono a
                la mano.
            </p>
            <Button :disabled="reconectando" @click="generarQrNuevo">
                <RefreshCw class="size-4" :class="{ 'animate-spin': reconectando }" />
                Generar nuevo QR
            </Button>
        </template>

        <template v-else-if="estado.estado === 'desconectado'">
            <p class="text-muted-foreground text-sm">
                La sesión está cerrada. Genera un QR para vincular el número.
            </p>
            <Button :disabled="reconectando" @click="generarQrNuevo">
                <RefreshCw class="size-4" :class="{ 'animate-spin': reconectando }" />
                Generar nuevo QR
            </Button>
        </template>

        <p
            v-else-if="estado.estado === 'no_autorizado'"
            class="text-muted-foreground text-sm"
        >
            El bot está en marcha pero rechaza la conexión: el
            <code>WHATSAPP_TOKEN</code> del <code>.env</code> de Laravel no
            coincide con el de <code>whatsapp-bot/.env</code>. Iguálalos y
            reinicia el servidor de Laravel.
        </p>

        <p
            v-else-if="estado.estado === 'sin_servicio'"
            class="text-muted-foreground text-sm"
        >
            El servicio de WhatsApp no responde. Verifica que el bot esté en
            marcha (carpeta <code>whatsapp-bot</code>, ver su README) y que
            <code>WHATSAPP_TOKEN</code> coincida en ambos lados.
        </p>

        <p v-else class="text-muted-foreground text-sm">
            Conectando con WhatsApp, un momento...
        </p>

        <Dialog v-model:open="dialogPrueba">
            <DialogContent class="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>Mensaje de prueba</DialogTitle>
                    <DialogDescription>
                        Envía un mensaje de prueba ahora mismo, fuera de la cola,
                        para comprobar que el bot funciona.
                    </DialogDescription>
                </DialogHeader>

                <form class="grid gap-4" @submit.prevent="enviarPrueba">
                    <div class="grid gap-2">
                        <Label for="telefono_prueba">Celular</Label>
                        <Input
                            id="telefono_prueba"
                            v-model="formPrueba.telefono"
                            placeholder="0414-1234567"
                            inputmode="tel"
                        />
                        <InputError :message="formPrueba.errors.telefono" />
                    </div>
                    <DialogFooter>
                        <Button type="submit" :disabled="formPrueba.processing">
                            {{ formPrueba.processing ? 'Enviando...' : 'Enviar' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    </div>
</template>
