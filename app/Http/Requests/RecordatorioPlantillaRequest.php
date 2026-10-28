<?php

namespace App\Http\Requests;

use App\Models\RecordatorioPlantilla;
use App\Services\Recordatorios\GeneradorMensaje;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecordatorioPlantillaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, ValidationRule|array<mixed>|string> */
    public function rules(): array
    {
        return [
            'tipo' => ['required', Rule::in(RecordatorioPlantilla::TIPOS)],
            'nombre' => ['required', 'string', 'max:100'],
            'cuerpo' => ['required', 'string', 'max:1000', $this->soloVariablesConocidas()],
            'activa' => ['required', 'boolean'],
        ];
    }

    /** Una variable mal escrita saldría tal cual ("{nombr}") en el WhatsApp del cliente. */
    private function soloVariablesConocidas(): Closure
    {
        return function (string $atributo, mixed $valor, Closure $fallar) {
            preg_match_all('/\{(\w+)\}/', (string) $valor, $coincidencias);

            $desconocidas = array_diff($coincidencias[1], array_keys(GeneradorMensaje::VARIABLES));

            if ($desconocidas) {
                $fallar('Variable desconocida: {'.implode('}, {', array_unique($desconocidas)).'}.');
            }
        };
    }
}
