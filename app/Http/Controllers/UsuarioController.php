<?php

namespace App\Http\Controllers;

use App\Enums\Rol;
use App\Http\Requests\UsuarioRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class UsuarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/usuarios/Index', [
            'usuarios' => User::orderBy('name')->get(['id', 'name', 'email', 'rol']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('settings/usuarios/Create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(UsuarioRequest $request): RedirectResponse
    {
        $datos = $request->validated();
        $datos['email_verified_at'] = now();

        User::create($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario creado.')]);

        return to_route('usuarios.index');
    }

    public function edit(User $usuario): Response
    {
        return Inertia::render('settings/usuarios/Edit', [
            'usuario' => $usuario->only(['id', 'name', 'email', 'rol']),
            'roles' => $this->roles(),
        ]);
    }

    public function update(UsuarioRequest $request, User $usuario): RedirectResponse
    {
        $datos = $request->validated();

        if (empty($datos['password'])) {
            unset($datos['password']);
        }

        if ($usuario->esAdmin() && $datos['rol'] !== Rol::Admin->value && $this->esUltimoAdmin($usuario)) {
            return $this->rechazar(__('Debe quedar al menos un administrador.'));
        }

        $usuario->update($datos);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario actualizado.')]);

        return to_route('usuarios.index');
    }

    public function destroy(User $usuario): RedirectResponse
    {
        if ($usuario->is(auth()->user())) {
            return $this->rechazar(__('No puedes eliminar tu propio usuario.'));
        }

        if ($usuario->esAdmin() && $this->esUltimoAdmin($usuario)) {
            return $this->rechazar(__('Debe quedar al menos un administrador.'));
        }

        $usuario->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Usuario eliminado.')]);

        return to_route('usuarios.index');
    }

    private function esUltimoAdmin(User $usuario): bool
    {
        return ! User::where('rol', Rol::Admin)->whereKeyNot($usuario->id)->exists();
    }

    private function rechazar(string $mensaje): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'error', 'message' => $mensaje]);

        return back();
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function roles(): array
    {
        return array_map(
            fn (Rol $rol) => ['value' => $rol->value, 'label' => $rol->etiqueta()],
            Rol::cases(),
        );
    }
}
