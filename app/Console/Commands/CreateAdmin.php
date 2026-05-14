<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

use function Laravel\Prompts\text;

class CreateAdmin extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:create
                            {--name= : The name of the admin}
                            {--email= : The email of the admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = $this->option('name') ?? text(
            label: 'What is the admin name?',
            required: true,
        );

        $email = $this->option('email') ?? text(
            label: 'What is the admin email?',
            required: true,
            validate: fn (string $value) => match (true) {
                ! filter_var($value, FILTER_VALIDATE_EMAIL) => 'Please enter a valid email address.',
                Admin::query()->where('email', $value)->exists() => 'An admin with this email already exists.',
                default => null,
            },
        );

        $password = Str::password(16);

        $admin = Admin::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'status' => true,
        ]);

        // Admin implements MustVerifyEmail — verify here so the CLI-created
        // admin can access the panels immediately.
        $admin->markEmailAsVerified();

        $this->components->info("Admin [{$admin->name}] created successfully.");
        $this->components->info("Password: {$password}");

        return self::SUCCESS;
    }
}
