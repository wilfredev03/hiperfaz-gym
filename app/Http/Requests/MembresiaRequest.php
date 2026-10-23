<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ReglasMembresia;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Activar o renovar la membresía de un cliente desde su ficha.
 */
class MembresiaRequest extends FormRequest
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
        return $this->reglasMembresia('', true);
    }
}
