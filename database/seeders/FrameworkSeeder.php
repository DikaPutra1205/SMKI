<?php

namespace Database\Seeders;

use App\Models\Framework;
use Illuminate\Database\Seeder;

class FrameworkSeeder extends Seeder
{
    public function run(): void
    {
        $frameworks = [
            ['nama' => 'ISO/IEC 27001', 'versi' => '2022', 'url_file' => null],
            ['nama' => 'ISO/IEC 27701', 'versi' => '2019', 'url_file' => null],
        ];

        foreach ($frameworks as $fw) {
            Framework::updateOrCreate(
                ['nama' => $fw['nama'], 'versi' => $fw['versi']],
                $fw
            );
        }
    }
}
