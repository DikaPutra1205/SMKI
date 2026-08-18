<?php

namespace Database\Factories;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ChecklistEntry>
 */
class ChecklistEntryFactory extends Factory
{
    protected $model = ChecklistEntry::class;

    public function definition(): array
    {
        return [
            'session_id' => ChecklistSession::factory(),
            'control_id' => Control::factory(),
            'unit_id' => WorkUnit::factory(),
            'pic_id' => User::factory(),
            'admin_id' => null,
            'status' => fake()->randomElement([
                ChecklistEntry::STATUS_COMPLIANT,
                ChecklistEntry::STATUS_PARTIAL,
                ChecklistEntry::STATUS_NON_COMPLIANT,
                ChecklistEntry::STATUS_NA,
            ]),
            'catatan' => fake()->optional()->sentence(),
            'catatan_admin' => fake()->optional()->sentence(),
            'tanggal_input' => now(),
            'tanggal_verifikasi' => null,
        ];
    }
}
