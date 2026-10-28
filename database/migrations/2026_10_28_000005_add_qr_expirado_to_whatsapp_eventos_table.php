<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const TIPOS = ['servicio_iniciado', 'qr_pendiente', 'conectado', 'conexion_perdida', 'reconectado', 'sesion_cerrada', 'cierre_manual'];

    /** `qr_expirado` lo reporta el bot; `reconexion_manual` la anota Laravel con el usuario que pulsó el botón. */
    public function up(): void
    {
        $this->restringir([...self::TIPOS, 'qr_expirado', 'reconexion_manual']);
    }

    public function down(): void
    {
        DB::table('whatsapp_eventos')->whereIn('tipo', ['qr_expirado', 'reconexion_manual'])->delete();

        $this->restringir(self::TIPOS);
    }

    /** @param  list<string>  $tipos */
    private function restringir(array $tipos): void
    {
        $lista = "'".implode("', '", $tipos)."'";

        DB::statement('ALTER TABLE whatsapp_eventos DROP CONSTRAINT chk_whatsapp_evento_tipo');
        DB::statement("ALTER TABLE whatsapp_eventos ADD CONSTRAINT chk_whatsapp_evento_tipo CHECK (tipo IN ({$lista}))");
    }
};
