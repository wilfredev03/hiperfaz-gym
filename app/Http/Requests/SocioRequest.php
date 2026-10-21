<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocioRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $socio = $this->route('socio');

        return [
            'nombre' => ['required', 'string', 'max:255'],
            'documento' => ['nullable', 'string', 'max:255', Rule::unique('clientes', 'documento')->ignore($socio)],
            'telefono' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'direccion' => ['nullable', 'string'],
            'fecha_nacimiento' => ['nullable', 'date', 'before_or_equal:today'],
            'contacto_emergencia_nombre' => ['nullable', 'string', 'max:255'],
            'contacto_emergencia_telefono' => ['nullable', 'string', 'max:255'],
            'id_acceso' => ['nullable', 'string', 'max:255', Rule::unique('clientes', 'id_acceso')->ignore($socio)],
            'activo' => ['required', 'boolean'],
        ];
    }
}
