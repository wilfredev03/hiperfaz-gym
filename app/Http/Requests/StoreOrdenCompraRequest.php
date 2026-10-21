<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrdenCompraRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'fecha' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
            // moneda en la que se digitaron los costos; se guardan siempre en USD
            'moneda' => ['nullable', 'in:USD,VES'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
