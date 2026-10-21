<?php

namespace Database\Seeders;

use App\Enums\Rol;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Por ahora el gimnasio opera con un único usuario admin, con acceso a
     * todo. La clave es de desarrollo/demo, no apta para producción.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin'],
            ['name' => 'Admin', 'password' => 'admin', 'rol' => Rol::Admin],
        );
    }
}
