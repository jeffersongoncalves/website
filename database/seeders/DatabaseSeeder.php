<?php

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        Admin::factory()->create([
            'name' => 'Test Admin',
            'email' => 'admin@filakit.com',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'user@filakit.com',
        ]);

        $this->call([
            ProjectSeeder::class,
            PostSeeder::class,
        ]);
    }
}
