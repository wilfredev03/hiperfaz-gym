<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait ReglasMembresia
{
    /**
     * Reglas del bloque de membresía (plan o membresía personalizada, y forma
     * de pago). Con prefijo sirven también para el alta de cliente
     * (`membresia.plan_id`, ...). Sin plan ni personalizada no se pide nada
     * más; con alguna de las dos, el método de pago es obligatorio salvo a crédito.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function reglasMembresia(string $prefijo, bool $obligatoria): array
    {
        $personalizada = fn (): bool => $this->boolean($prefijo.'personalizada');
        $hayMembresia = fn (): bool => $personalizada() || filled($this->input($prefijo.'plan_id'));
        $porFechas = fn (): bool => $personalizada() && $this->input($prefijo.'modo') === 'fechas';
        $porDias = fn (): bool => $personalizada() && $this->input($prefijo.'modo') !== 'fechas';

        return [
            $prefijo.'plan_id' => [
                $obligatoria ? Rule::requiredIf(fn () => ! $personalizada()) : 'nullable',
                'nullable',
                Rule::exists('planes', 'id')->where(fn ($query) => $query->whereRaw('not personalizado')),
            ],
            $prefijo.'personalizada' => ['nullable', 'boolean'],
            $prefijo.'precio' => [Rule::requiredIf($personalizada), 'nullable', 'numeric', 'gt:0', 'max:999999'],
            $prefijo.'modo' => [Rule::requiredIf($personalizada), 'nullable', Rule::in(['dias', 'fechas'])],
            $prefijo.'dias' => [Rule::requiredIf($porDias), 'nullable', 'integer', 'min:1', 'max:3650'],
            $prefijo.'fecha_inicio' => [Rule::requiredIf($porFechas), 'nullable', 'date'],
            $prefijo.'fecha_fin' => [Rule::requiredIf($porFechas), 'nullable', 'date', 'after_or_equal:'.$prefijo.'fecha_inicio'],
            $prefijo.'es_credito' => ['nullable', 'boolean'],
            $prefijo.'metodo_pago_id' => [
                Rule::requiredIf(fn () => $hayMembresia() && ! $this->boolean($prefijo.'es_credito')),
                'nullable',
                Rule::exists('metodos_pago', 'id')->where('activo', true),
            ],
            $prefijo.'referencia' => ['nullable', 'string', 'max:100'],
            $prefijo.'monto_recibido' => ['nullable', 'numeric', 'min:0', 'max:99999999'],
        ];
    }
}
