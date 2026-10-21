<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ProductoRequest extends FormRequest
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
            'categoria_id' => ['required', 'integer', 'exists:categorias,id'],
            'unidad_medida_id' => ['required', 'integer', 'exists:unidades_medida,id'],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'imagen' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'precio_venta' => ['required', 'numeric', 'gt:0'],
            'costo_promedio' => ['nullable', 'numeric', 'min:0'],
            'controla_inventario' => ['required', 'boolean'],
            'activo' => ['required', 'boolean'],
        ];
    }
}
