<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import PasswordInput from '@/components/PasswordInput.vue';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

interface RolOption {
    value: string;
    label: string;
}

defineProps<{
    form: {
        name: string;
        email: string;
        rol: string;
        password: string;
        errors: Partial<Record<'name' | 'email' | 'rol' | 'password', string>>;
    };
    roles: RolOption[];
    // al editar, la contraseña es opcional: vacía = no cambia
    editando?: boolean;
}>();
</script>

<template>
    <div class="grid gap-4">
        <div class="grid gap-2">
            <Label for="name">Nombre</Label>
            <Input
                id="name"
                v-model="form.name"
                placeholder="Nombre completo"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="grid gap-2">
            <Label for="email">Usuario</Label>
            <Input
                id="email"
                v-model="form.email"
                autocapitalize="none"
                autocomplete="off"
                placeholder="usuario"
            />
            <p class="text-muted-foreground text-xs">
                Con el que inicia sesión. No distingue mayúsculas de minúsculas.
            </p>
            <InputError :message="form.errors.email" />
        </div>

        <div class="grid gap-2">
            <Label for="rol">Rol</Label>
            <select
                id="rol"
                v-model="form.rol"
                class="border-input bg-background h-9 rounded-md border px-3 text-sm"
            >
                <option
                    v-for="rol in roles"
                    :key="rol.value"
                    :value="rol.value"
                >
                    {{ rol.label }}
                </option>
            </select>
            <InputError :message="form.errors.rol" />
        </div>

        <div class="grid gap-2">
            <Label for="password">
                {{ editando ? 'Nueva contraseña (opcional)' : 'Contraseña' }}
            </Label>
            <PasswordInput
                id="password"
                v-model="form.password"
                autocomplete="new-password"
                placeholder="Mínimo 4 caracteres"
            />
            <InputError :message="form.errors.password" />
        </div>
    </div>
</template>
