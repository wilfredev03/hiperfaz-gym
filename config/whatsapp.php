<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Servicio de WhatsApp (bot de Baileys)
    |--------------------------------------------------------------------------
    |
    | El bot es un proceso Node aparte (carpeta whatsapp-bot/) que solo
    | ejecuta: mantiene la sesión de WhatsApp y envía lo que Laravel le pide.
    | Laravel decide a quién, cuándo y qué (módulo de recordatorios). El
    | servicio escucha únicamente en 127.0.0.1 y exige el token compartido.
    |
    */

    'url' => env('WHATSAPP_URL', 'http://127.0.0.1:3100'),

    'token' => env('WHATSAPP_TOKEN'),

    // segundos que Laravel espera la respuesta del bot al enviar un mensaje
    'timeout' => (int) env('WHATSAPP_TIMEOUT', 25),

];
