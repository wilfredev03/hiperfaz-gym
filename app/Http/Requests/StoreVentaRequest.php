<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVentaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'es_credito' => ['required', 'boolean'],
            // obligatorio si la venta es a crédito, opcional si es al contado
            'cliente_id' => ['required_if:es_credito,true', 'nullable', 'exists:clientes,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'exists:productos,id'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],

            // pagos es opcional: una venta a crédito puede registrarse sin ningún pago inicial
            'pagos' => ['nullable', 'array'],
            'pagos.*.metodo_pago_id' => ['required_with:pagos', 'exists:metodos_pago,id'],
            'pagos.*.monto' => ['required_with:pagos', 'numeric', 'gt:0'],
            'pagos.*.referencia' => ['nullable', 'string', 'max:100'],

            'monto_recibido' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}
