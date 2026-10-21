<?php

namespace Database\Factories;

use App\Models\Asistencia;
use App\Models\Cliente;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asistencia>
 */
class AsistenciaFactory extends Factory
{
    protected $model = Asistencia::class;

    public function definition(): array
    {
        return [
            'cliente_id' => Cliente::factory(),
            'fecha_hora' => now(),
            'dispositivo' => null,
            'resultado' => 'permitido',
            'origen' => 'manual',
        ];
    }

    public function denegada(): static
    {
        return $this->state(fn (array $attributes) => ['resultado' => 'denegado']);
    }

    public function delTerminal(string $dispositivo = 'terminal-1'): static
    {
        return $this->state(fn (array $attributes) => [
            'origen' => 'terminal',
            'dispositivo' => $dispositivo,
        ]);
    }
}
