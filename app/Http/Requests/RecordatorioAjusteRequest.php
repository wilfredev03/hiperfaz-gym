<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class RecordatorioAjusteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Los mínimos son un piso de seguridad para el número de WhatsApp: una
     * espera de segundos entre mensajes es la forma más rápida de que lo bloqueen.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fin' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'espera_min_seg' => ['required', 'integer', 'min:30', 'max:3600'],
            'espera_max_seg' => ['required', 'integer', 'gte:espera_min_seg', 'max:3600'],
            'mensajes_descanso_min' => ['required', 'integer', 'min:1', 'max:200'],
            'mensajes_descanso_max' => ['required', 'integer', 'gte:mensajes_descanso_min', 'max:200'],
            'descanso_min' => ['required', 'integer', 'min:1', 'max:240'],
            'descanso_max' => ['required', 'integer', 'gte:descanso_min', 'max:240'],
            'dias_aviso' => ['required', 'integer', 'min:1', 'max:30'],
            'duracion_minima_dias' => ['required', 'integer', 'min:1', 'max:365'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'espera_min_seg.min' => 'La espera mínima no puede ser menor a 30 segundos (protege el número de WhatsApp).',
            'hora_fin.after' => 'La hora de fin debe ser posterior a la de inicio.',
        ];
    }
}
