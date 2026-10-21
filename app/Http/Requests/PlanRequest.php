<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PlanRequest extends FormRequest
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
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'duracion_dias' => ['required', 'integer', 'min:1', 'max:3650'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
