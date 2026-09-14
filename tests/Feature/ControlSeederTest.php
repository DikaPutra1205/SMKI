<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class ControlSeederTest extends TestCase
{
    public function test_seeds_new_master_control_set(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\FrameworkSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ControlSeeder']);

        $iso27001 = Framework::where('nama', 'ISO/IEC 27001')->firstOrFail();
        $iso27701 = Framework::where('nama', 'ISO/IEC 27701')->firstOrFail();

        $this->assertSame('2025', $iso27701->versi);
        $this->assertSame(93, $iso27001->controls()->count());
        $this->assertSame(30, $iso27701->controls()->count());

        // 27001 spot checks: new domain slugs, null peran
        $this->assertDatabaseHas('controls', [
            'framework_id' => $iso27001->id, 'kode_klausul' => 'A.5.1',
            'kategori' => 'organisasional', 'domain_peran' => null,
        ]);
        $this->assertDatabaseHas('controls', [
            'framework_id' => $iso27001->id, 'kode_klausul' => 'A.8.34', 'kategori' => 'teknologi',
        ]);
        // Klausul 4-10 rows retired
        $this->assertDatabaseMissing('controls', ['framework_id' => $iso27001->id, 'kode_klausul' => '4.1']);

        // 27701 spot checks: 7.x controller, 8.x processor
        $this->assertDatabaseHas('controls', [
            'framework_id' => $iso27701->id, 'kode_klausul' => '7.2.1', 'domain_peran' => 'controller',
        ]);
        $this->assertDatabaseHas('controls', [
            'framework_id' => $iso27701->id, 'kode_klausul' => '8.4.2', 'domain_peran' => 'processor',
        ]);
        // Old 2019 clause-5/6 rows retired
        $this->assertDatabaseMissing('controls', ['framework_id' => $iso27701->id, 'kode_klausul' => '5.2.1']);
    }

    public function test_seeder_is_idempotent(): void
    {
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\FrameworkSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ControlSeeder']);
        Artisan::call('db:seed', ['--class' => 'Database\\Seeders\\ControlSeeder']);

        $this->assertSame(123, Control::count());
    }
}
