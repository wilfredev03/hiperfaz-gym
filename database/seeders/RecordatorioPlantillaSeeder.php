<?php

namespace Database\Seeders;

use App\Models\RecordatorioPlantilla;
use Illuminate\Database\Seeder;

/**
 * Plantillas de arranque de los recordatorios por WhatsApp. Variables:
 * {nombre} {nombre_completo} {plan} {dias} {fecha}. Lo que va entre corchetes
 * con barras, [Hola|Buenas], se sortea en cada envío, así los mensajes no
 * salen idénticos. El dueño las edita desde Recordatorios > Plantillas.
 * Se corre desde GimnasioMenuSeeder; es idempotente (por nombre).
 */
class RecordatorioPlantillaSeeder extends Seeder
{
    public function run(): void
    {
        $plantillas = [
            ['por_vencer', 'Aviso amistoso', '[Hola|Buenas|Qué tal] {nombre}, [te recordamos|queremos recordarte|un aviso rápido:] que tu plan {plan} vence en {dias} ({fecha}). [Te esperamos para renovar|Pasa por recepción para renovar|Escríbenos si quieres renovar] 💪'],
            ['por_vencer', 'Renovación a tiempo', '{nombre}, [tu membresía|tu plan] {plan} [termina|vence] en {dias}. [Renueva a tiempo y sigue entrenando sin interrupciones|Renueva antes de que venza y no pierdas ni un día de entreno] 🏋️'],
            ['por_vencer', 'Recordatorio corto', '[Hola|Buenas] {nombre}! Tu plan {plan} vence el {fecha}, faltan {dias}. [¡Te esperamos!|¡Nos vemos en el gym!|¡Aquí estamos para ayudarte a renovar!]'],
            ['vence_hoy', 'Vence hoy', '[Hola|Buenas|Qué tal] {nombre}, [hoy vence|hoy termina] tu plan {plan}. [Pasa por recepción para renovar y seguir entrenando|Renueva hoy y no pierdas tu ritmo] 💪'],
            ['vence_hoy', 'Último día', '{nombre}, [hoy es el último día de|hoy se cumple tu acceso con] tu plan {plan}. [Te esperamos para renovarlo|Escríbenos o pasa por el gym para renovar] 🔥'],
        ];

        foreach ($plantillas as [$tipo, $nombre, $cuerpo]) {
            RecordatorioPlantilla::updateOrCreate(
                ['tipo' => $tipo, 'nombre' => $nombre],
                ['cuerpo' => $cuerpo, 'activa' => true],
            );
        }
    }
}
