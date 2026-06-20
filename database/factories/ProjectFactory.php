<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProjectCategory;
use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    protected $model = Project::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'slug' => Str::slug($name),
            'name' => $name,
            'category' => ProjectCategory::LaravelPackage,
            'license' => 'MIT',
            'status' => ProjectStatus::Published,
            'stars' => fake()->numberBetween(0, 1000),
            'downloads' => fake()->numberBetween(0, 100000),
        ];
    }
}
