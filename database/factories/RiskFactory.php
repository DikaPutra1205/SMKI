<?php

namespace Database\Factories;

use App\Models\Control;
use App\Models\Risk;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Risk>
 */
class RiskFactory extends Factory
{
    protected $model = Risk::class;

    public function definition(): array
    {
        return [
            'control_id' => Control::factory(),
            'level_risiko' => fake()->randomElement([Risk::LEVEL_LOW, Risk::LEVEL_MEDIUM, Risk::LEVEL_HIGH, Risk::LEVEL_CRITICAL]),
            'pemilik_risiko' => fake()->name(),
            'rencana_mitigasi' => fake()->sentence(),
            'status' => fake()->randomElement([Risk::STATUS_OPEN, Risk::STATUS_MITIGATED, Risk::STATUS_ACCEPTED]),
        ];
    }
}
