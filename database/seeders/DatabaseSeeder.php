<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * A fresh install does NOT seed a user — the /setup wizard creates the first
     * admin. Run `php artisan db:seed --class=DemoStoreSeeder` to populate a
     * demo catalog on a throwaway instance.
     */
    public function run(): void
    {
        $this->call(DemoStoreSeeder::class);
    }
}
