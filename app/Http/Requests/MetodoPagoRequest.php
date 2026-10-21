<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class MetodoPagoRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombre' => ['required', 'string', 'max:255'],
            'moneda' => ['required', 'string', 'in:USD,VES'],
            'requiere_referencia' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
            // posición en la pantalla de cobro; si no se envía se conserva
            'orden' => ['sometimes', 'integer', 'min:0', 'max:65535'],
        ];
    }
}
