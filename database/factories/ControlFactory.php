<?php

namespace Database\Factories;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Control>
 */
class ControlFactory extends Factory
{
    protected $model = Control::class;

    public function definition(): array
    {
        return [
            'framework_id' => Framework::factory(),
            'kode_klausul' => 'A.'.fake()->unique()->numerify('#.#'),
            'judul' => fake()->sentence(3),
            'kategori' => fake()->randomElement(Control::KATEGORIS),
            'domain_peran' => fake()->optional()->randomElement(Control::PERANS),
            'deskripsi' => fake()->optional()->paragraph(),
        ];
    }
}
