<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('rol', 20)->default('vendedor')->after('password');
        });

        // los usuarios que ya existen son los dueños de la instalación; el
        // "email" pasa a ser un nombre de usuario sin distinción de mayúsculas
        DB::table('users')->update(['rol' => 'admin', 'email' => DB::raw('LOWER(email)')]);

        DB::statement("ALTER TABLE users ADD CONSTRAINT users_rol_check CHECK (rol IN ('admin', 'vendedor'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE users DROP CONSTRAINT users_rol_check');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('rol');
        });
    }
};
