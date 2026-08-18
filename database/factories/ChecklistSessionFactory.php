<?php

namespace Database\Factories;

use App\Models\ChecklistSession;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistSession>
 */
class ChecklistSessionFactory extends Factory
{
    protected $model = ChecklistSession::class;

    public function definition(): array
    {
        return [
            'konteks_penilaian' => 'Evaluasi Kepatuhan SMKI '.fake()->monthName().' '.fake()->year(),
            'periode' => now()->format('Y-m'),
            'unit_id' => WorkUnit::factory(),
            'framework_id' => Framework::factory(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
