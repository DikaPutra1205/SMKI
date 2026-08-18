<?php

namespace Database\Factories;

use App\Models\Control;
use App\Models\Finding;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Finding>
 */
class FindingFactory extends Factory
{
    protected $model = Finding::class;

    public function definition(): array
    {
        return [
            'control_id' => Control::factory(),
            'unit_id' => WorkUnit::factory(),
            'pic_id' => User::factory(),
            'admin_id' => null,
            'kategori' => fake()->randomElement([Finding::KATEGORI_MAJOR, Finding::KATEGORI_MINOR, Finding::KATEGORI_OBSERVASI]),
            'status' => fake()->randomElement([Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::STATUS_CLOSED]),
            'deadline' => now()->addDays(14),
            'catatan_admin' => fake()->optional()->sentence(),
            'tanggal_verifikasi' => null,
        ];
    }
}
