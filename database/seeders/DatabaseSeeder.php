<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * No se siembran usuarios: el primer administrador se crea con
     * `php artisan usuarios:crear`.
     */
    public function run(): void
    {
        //
    }
}
