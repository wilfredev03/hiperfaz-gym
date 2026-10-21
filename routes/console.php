<?php

use App\Console\Commands\MarcarSuscripcionesVencidasCommand;
use App\Console\Commands\ObtenerTasaBcvCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// el BCV publica en la tarde la tasa con "fecha valor" del día hábil
// siguiente, así que a las 06:00 ya debería estar la de hoy; los intentos
// de cada hora hasta el mediodía cubren caídas de la página o de la API
// (el comando no hace nada si la tasa de hoy ya está cargada).
// Requiere en el VPS: * * * * * cd /ruta/al/proyecto && php artisan schedule:run
Schedule::command(ObtenerTasaBcvCommand::class)
    ->hourly()
    ->between('06:00', '12:00')
    ->timezone('America/Caracas')
    ->withoutOverlapping();

// al día siguiente del último día de acceso la suscripción pasa a "vencida";
// el acceso ya se calcula por fechas, esto solo ordena el estado guardado
Schedule::command(MarcarSuscripcionesVencidasCommand::class)
    ->dailyAt('00:05')
    ->timezone('America/Caracas')
    ->withoutOverlapping();
