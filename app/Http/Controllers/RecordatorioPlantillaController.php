<?php

namespace App\Http\Controllers;

use App\Http\Requests\RecordatorioPlantillaRequest;
use App\Models\RecordatorioPlantilla;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class RecordatorioPlantillaController extends Controller
{
    public function store(RecordatorioPlantillaRequest $request): RedirectResponse
    {
        RecordatorioPlantilla::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla creada.')]);

        return to_route('recordatorios.index');
    }

    public function update(RecordatorioPlantillaRequest $request, RecordatorioPlantilla $plantilla): RedirectResponse
    {
        $plantilla->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla actualizada.')]);

        return to_route('recordatorios.index');
    }

    /** Los recordatorios ya enviados conservan su mensaje; solo pierden el vínculo a la plantilla. */
    public function destroy(RecordatorioPlantilla $plantilla): RedirectResponse
    {
        $plantilla->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plantilla eliminada.')]);

        return to_route('recordatorios.index');
    }
}
