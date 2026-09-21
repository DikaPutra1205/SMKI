<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingCatatanSplitTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $picA;

    private WorkUnit $unitA;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
            'unit_id' => $this->unitA->id,
        ]);
        $this->picA = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $this->unitA->id,
        ]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create(['framework_id' => $framework->id]);
    }

    public function test_pic_note_persists_to_catatan_and_preserves_catatan_admin(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'catatan_admin' => 'Instruksi awal admin.',
        ]);

        $this->actingAs($this->picA)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'Progres PIC: patch applied.',
            ])
            ->assertOk();

        $fresh = $finding->fresh();
        $this->assertSame('Progres PIC: patch applied.', $fresh->catatan);
        $this->assertSame('Instruksi awal admin.', $fresh->catatan_admin);
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picA->id,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Progres PIC: patch applied.',
        ]);
    }

    public function test_admin_note_updates_catatan_admin_and_preserves_catatan(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan_admin' => 'Instruksi awal admin.',
            'catatan' => 'Progres PIC sebelumnya.',
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan_admin' => 'Revisi admin: lampirkan bukti.',
            ])
            ->assertOk();

        $fresh = $finding->fresh();
        $this->assertSame('Revisi admin: lampirkan bukti.', $fresh->catatan_admin);
        $this->assertSame('Progres PIC sebelumnya.', $fresh->catatan);
    }

    public function test_pic_cannot_overwrite_catatan_admin(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'status' => Finding::STATUS_OPEN,
            'catatan_admin' => 'Instruksi awal admin.',
        ]);

        $this->actingAs($this->picA)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_OPEN,
                'catatan_admin' => 'PIC coba timpa.',
                'catatan' => 'Progres sah PIC.',
            ])
            ->assertOk();

        $fresh = $finding->fresh();
        $this->assertSame('Instruksi awal admin.', $fresh->catatan_admin);
        $this->assertSame('Progres sah PIC.', $fresh->catatan);
    }
}
