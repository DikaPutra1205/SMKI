<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PicBackfillTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_new_pic_claims_units_null_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(3)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);

        $pic = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $unit->id,
        ]);

        $this->assertSame(3, ChecklistEntry::where('unit_id', $unit->id)
            ->where('pic_id', $pic->id)
            ->count());
    }

    public function test_pic_reassignment_keeps_already_owned_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        $picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => $picA->id,
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);

        // Saving picB triggers the back-fill.
        $picB->save();

        $this->assertSame(1, ChecklistEntry::where('pic_id', $picA->id)->count());
        $this->assertSame(1, ChecklistEntry::where('pic_id', $picB->id)->count());
    }

    public function test_non_pic_user_save_does_not_claim_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);

        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
            'unit_id' => $unit->id,
        ]);
        $admin->save();

        $this->assertSame(0, ChecklistEntry::where('unit_id', $unit->id)
            ->whereNotNull('pic_id')
            ->count());
    }

    public function test_existing_pic_keeps_entries_on_unrelated_update(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => $pic->id,
        ]);

        $pic->update(['name' => 'PIC Updated']);

        $this->assertSame(2, ChecklistEntry::where('unit_id', $unit->id)
            ->where('pic_id', $pic->id)
            ->count());
    }
}
