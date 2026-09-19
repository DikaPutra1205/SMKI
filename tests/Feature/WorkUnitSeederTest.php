<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WorkUnit;
use Database\Seeders\WorkUnitSeeder;
use Tests\TestCase;

class WorkUnitSeederTest extends TestCase
{
    public function test_seeder_creates_3_root_units(): void
    {
        $this->seed(WorkUnitSeeder::class);

        $roots = WorkUnit::whereNull('parent_id')->get();
        $this->assertCount(3, $roots);
        $this->assertContains('BALAI BESAR PELATIHAN SDM KOMUNIKASI DAN DIGITAL', $roots->pluck('nama'));
        $this->assertContains('BALAI PELATIHAN SDM KOMUNIKASI DAN DIGITAL', $roots->pluck('nama'));
        $this->assertContains('BALAI PELATIHAN TALENTA KOMUNIKASI DAN DIGITAL', $roots->pluck('nama'));
    }

    public function test_seeder_creates_children_under_balai_besar(): void
    {
        $this->seed(WorkUnitSeeder::class);

        $bbRoot = WorkUnit::where('nama', 'BALAI BESAR PELATIHAN SDM KOMUNIKASI DAN DIGITAL')->first();
        $children = WorkUnit::where('parent_id', $bbRoot->id)->get();
        $this->assertCount(2, $children);
        $this->assertContains('BBP SDM Komdigi Medan', $children->pluck('nama'));
        $this->assertContains('BBP SDM Komdigi Makassar', $children->pluck('nama'));
    }

    public function test_seeder_creates_children_under_balai_pelatihan_sdm(): void
    {
        $this->seed(WorkUnitSeeder::class);

        $bpsdmRoot = WorkUnit::where('nama', 'BALAI PELATIHAN SDM KOMUNIKASI DAN DIGITAL')->first();
        $children = WorkUnit::where('parent_id', $bpsdmRoot->id)->get();
        $this->assertCount(6, $children);
        $this->assertContains('BPSDM Komdigi Jakarta', $children->pluck('nama'));
        $this->assertContains('BPSDM Komdigi Bandung', $children->pluck('nama'));
        $this->assertContains('BPSDM Komdigi Surabaya', $children->pluck('nama'));
        $this->assertContains('BPSDM Komdigi Yogyakarta', $children->pluck('nama'));
        $this->assertContains('BPSDM Komdigi Banjarmasin', $children->pluck('nama'));
        $this->assertContains('BPSDM Komdigi Manado', $children->pluck('nama'));
    }

    public function test_seeder_creates_child_under_balai_talenta(): void
    {
        $this->seed(WorkUnitSeeder::class);

        $talentaRoot = WorkUnit::where('nama', 'BALAI PELATIHAN TALENTA KOMUNIKASI DAN DIGITAL')->first();
        $children = WorkUnit::where('parent_id', $talentaRoot->id)->get();
        $this->assertCount(1, $children);
        $this->assertEquals('Balai Pelatihan Talenta Komdigi', $children->first()->nama);
    }

    public function test_seeder_total_12_units(): void
    {
        $this->seed(WorkUnitSeeder::class);

        $this->assertCount(12, WorkUnit::all());
    }

    public function test_seeder_assigns_pic_to_balai_besar(): void
    {
        User::factory()->create(['email' => 'pic@smki.test']);
        $this->seed(WorkUnitSeeder::class);

        $bbRoot = WorkUnit::where('nama', 'BALAI BESAR PELATIHAN SDM KOMUNIKASI DAN DIGITAL')->first();
        $pic = User::where('email', 'pic@smki.test')->first();
        $this->assertNotNull($pic);
        $this->assertEquals($bbRoot->id, $pic->unit_id);
    }

    public function test_seeder_is_idempotent(): void
    {
        $this->seed(WorkUnitSeeder::class);
        $countAfterFirst = WorkUnit::count();

        $this->seed(WorkUnitSeeder::class);
        $this->assertEquals($countAfterFirst, WorkUnit::count());
    }
}
