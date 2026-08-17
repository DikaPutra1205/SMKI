<?php

namespace Database\Factories;

use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WorkUnit>
 */
class WorkUnitFactory extends Factory
{
    protected $model = WorkUnit::class;

    public function definition(): array
    {
        return [
            'nama' => fake()->company(),
            'parent_id' => null,
        ];
    }
}
