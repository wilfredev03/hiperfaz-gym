<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasMembresia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SocioRequest extends FormRequest
{
    use ReglasMembresia;

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
            'id_acceso' => ['nullable', 'string', 'max:255', Rule::unique('clientes', 'id_acceso')->ignore($socio)],
            'activo' => ['required', 'boolean'],

            // al registrar un cliente se puede activar su membresía de una vez
            'membresia' => ['nullable', 'array'],
            ...($this->isMethod('POST') ? $this->reglasMembresia('membresia.', false) : []),
        ];
    }
}
