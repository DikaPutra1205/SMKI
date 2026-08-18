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
            'nama_sesi' => 'Audit Kepatuhan SMKI '.fake()->monthName().' '.fake()->year(),
            'unit_id' => WorkUnit::factory(),
            'framework_id' => Framework::factory(),
            'created_by' => User::factory(),
            'auditor_id' => User::factory(),
            'start_date' => now()->startOfMonth(),
            'end_date' => now()->endOfMonth(),
            'status' => fake()->randomElement(['draft', 'in_progress', 'submitted', 'verified']),
            'catatan' => fake()->optional()->sentence(),
        ];
    }
}
