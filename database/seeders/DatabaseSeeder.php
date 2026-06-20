<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Admin;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Test Admin / Test User are local/dev fixtures only — never seeded in
     * production. Model::create() is used instead of factories: factories
     * depend on fakerphp/faker (require-dev), absent from the production image.
     */
    public function run(): void
    {
        if (! app()->isProduction()) {
            $admin = Admin::query()->create([
                'name' => 'Test Admin',
                'email' => 'admin@filakit.com',
                'password' => 'password',
                'status' => true,
            ]);

            $admin->markEmailAsVerified();

            $user = User::query()->create([
                'name' => 'Test User',
                'email' => 'user@filakit.com',
                'password' => 'password',
                'status' => true,
            ]);

            $user->markEmailAsVerified();
        }
    }
}
