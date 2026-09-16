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
            'level_risiko' => fake()->randomElement([Risk::LEVEL_LOW, Risk::LEVEL_MEDIUM, Risk::LEVEL_HIGH, Risk::LEVEL_CRITICAL]),
            'pemilik_risiko' => fake()->name(),
            'rencana_mitigasi' => fake()->sentence(),
            'status' => fake()->randomElement([Risk::STATUS_OPEN, Risk::STATUS_MITIGATED, Risk::STATUS_ACCEPTED]),
        ];
    }

    /**
     * Attach satu atau lebih kontrol ke pivot setelah risk dibuat.
     * Gunakan di test: Risk::factory()->withControls()->create()
     */
    public function withControls(int $count = 1): static
    {
        return $this->afterCreating(function (Risk $risk) use ($count) {
            $controls = Control::factory()->count($count)->create();
            $risk->controls()->syncWithoutDetaching($controls->pluck('id')->toArray());
        });
    }

    /**
     * Attach kontrol spesifik ke pivot setelah risk dibuat.
     * Gunakan di test: Risk::factory()->withControl($control)->create()
     */
    public function withControl(Control $control): static
    {
        return $this->afterCreating(function (Risk $risk) use ($control) {
            $risk->controls()->syncWithoutDetaching([$control->id]);
        });
    }
}
