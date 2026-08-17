<?php

namespace Database\Factories;

use App\Models\Framework;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Framework>
 */
class FrameworkFactory extends Factory
{
    protected $model = Framework::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->words(3, true),
            'versi' => (string) fake()->year(),
            'url_file' => fake()->optional()->url(),
        ];
    }
}
