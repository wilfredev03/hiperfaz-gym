<?php

namespace App\Http\Requests;

use App\Concerns\ProfileValidationRules;
use App\Enums\Rol;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UsuarioRequest extends FormRequest
{
    use ProfileValidationRules;

    public function authorize(): bool
    {
        return true;
    }

    // el usuario no distingue mayúsculas: se compara ya en minúsculas
    protected function prepareForValidation(): void
    {
        if (is_string($this->email)) {
            $this->merge(['email' => mb_strtolower(trim($this->email))]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $usuario = $this->route('usuario');

        return [
            'name' => $this->nameRules(),
            'email' => $this->emailRules($usuario?->id),
            'rol' => ['required', Rule::enum(Rol::class)],
            // al editar, la contraseña solo se envía si se quiere cambiar
            // no lleva confirmación: el admin la escribe y puede verla
            'password' => [$usuario ? 'nullable' : 'required', 'string', Password::default()],
        ];
    }

    public function attributes(): array
    {
        return ['email' => 'usuario'];
    }
}
