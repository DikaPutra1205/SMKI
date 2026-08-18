<?php

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

    public function definition(): array
    {
        return [
            'entity_type' => fake()->randomElement(['ChecklistEntry', 'Finding', 'Risk', 'ChecklistSession']),
            'entity_id' => fake()->randomNumber(3),
            'actor_id' => User::factory(),
            'aksi' => fake()->randomElement(['create', 'update', 'delete', 'verify', 'upload']),
            'detail_perubahan' => [
                'before' => ['status' => 'in_progress'],
                'after' => ['status' => 'compliant'],
            ],
            'created_at' => now(),
        ];
    }
}
