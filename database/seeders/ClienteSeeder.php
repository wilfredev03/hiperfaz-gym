<?php

namespace Database\Seeders;

use App\Models\Cliente;
use Illuminate\Database\Seeder;

class ClienteSeeder extends Seeder
{
    public function run(): void
    {
        Cliente::factory()->count(12)->create();
        Cliente::factory()->count(3)->conSaldoPendiente()->create();
    }
}
