<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FindingAuthzGapTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $picA;

    private User $picB;

    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::factory()->create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::factory()->create(['nama' => 'Unit B']);

        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
            'unit_id' => $this->unitA->id,
        ]);

        $this->picA = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $this->unitA->id,
        ]);

        $this->picB = User::factory()->create([
            'role' => User::ROLE_PIC,
            'unit_id' => $this->unitB->id,
        ]);

        $framework = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.5.1']);
    }

    private function makeFinding(array $overrides = []): Finding
    {
        return Finding::factory()->create(array_merge([
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'pic_id' => $this->picA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => '2026-10-01',
            'catatan_admin' => 'Catatan awal dari admin.',
        ], $overrides));
    }

    public function test_pic_cannot_change_deadline_category_or_pic_id_via_legacy_api(): void
    {
        $finding = $this->makeFinding();

        $response = $this->actingAs($this->picA)->putJson("/api/findings/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'deadline' => '2027-01-01',
            'category' => Finding::KATEGORI_MAJOR,
            'pic_id' => $this->picB->id,
            'catatan' => 'Sedang ditindaklanjuti oleh PIC.',
        ]);

        $response->assertOk();

        $fresh = $finding->fresh();
        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertSame($finding->deadline->toDateString(), $fresh->deadline?->toDateString());
        $this->assertEquals(Finding::KATEGORI_MINOR, $fresh->kategori);
        $this->assertEquals($this->picA->id, $fresh->pic_id);
    }

    public function test_pic_cannot_change_deadline_via_officer_api(): void
    {
        $finding = $this->makeFinding();

        $response = $this->actingAs($this->picA)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_RESOLVED,
            'deadline' => '2027-01-01',
            'catatan' => 'Selesai ditindaklanjuti.',
        ]);

        $response->assertOk();

        $fresh = $finding->fresh();
        $this->assertEquals(Finding::STATUS_RESOLVED, $fresh->status);
        $this->assertSame($finding->deadline->toDateString(), $fresh->deadline?->toDateString());
    }

    public function test_pic_cannot_change_deadline_via_web_route(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->picA)
            ->from('/temuan')
            ->put("/temuan/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'deadline' => '2027-01-01',
                'catatan' => 'Perbaikan sedang berjalan.',
            ])
            ->assertRedirect('/temuan');

        $fresh = $finding->fresh();
        $this->assertEquals(Finding::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertSame($finding->deadline->toDateString(), $fresh->deadline?->toDateString());

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picA->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Perbaikan sedang berjalan.',
        ]);
    }

    public function test_pic_cannot_close_finding_via_legacy_api(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->picA)
            ->putJson("/api/findings/{$finding->id}", ['status' => Finding::STATUS_CLOSED])
            ->assertForbidden();

        $this->assertEquals(Finding::STATUS_OPEN, $finding->fresh()->status);
    }

    public function test_pic_cannot_close_finding_via_legacy_status_endpoint(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->picA)
            ->patchJson("/api/findings/{$finding->id}/status", ['status' => Finding::STATUS_CLOSED])
            ->assertForbidden();

        $this->assertEquals(Finding::STATUS_OPEN, $finding->fresh()->status);
    }

    public function test_pic_can_attach_note_without_status_change_via_web_route(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->picA)
            ->from('/temuan')
            ->put("/temuan/{$finding->id}", [
                'status' => Finding::STATUS_OPEN,
                'catatan' => 'Catatan progres PIC tanpa ganti status.',
                'deadline' => '2027-01-01',
            ])
            ->assertRedirect('/temuan');

        // Same-status note is persisted as a history/comment entry...
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picA->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_OPEN,
            'catatan' => 'Catatan progres PIC tanpa ganti status.',
        ]);

        // ...and the initial admin note is preserved, not overwritten.
        $fresh = $finding->fresh();
        $this->assertSame('Catatan awal dari admin.', $fresh->catatan_admin);
        $this->assertSame($finding->deadline->toDateString(), $fresh->deadline?->toDateString());
    }

    public function test_note_only_update_preserves_initial_catatan_admin(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->admin)->putJson("/api/v1/compliance-officer/findings/{$finding->id}", [
            'status' => Finding::STATUS_OPEN,
            'catatan' => 'Catatan admin tanpa ganti status.',
        ])->assertOk();

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_OPEN,
            'catatan' => 'Catatan admin tanpa ganti status.',
        ]);

        $this->assertSame('Catatan awal dari admin.', $finding->fresh()->catatan_admin);
    }

    public function test_admin_can_still_change_deadline_and_category_via_legacy_api(): void
    {
        $finding = $this->makeFinding();

        $this->actingAs($this->admin)->putJson("/api/findings/{$finding->id}", [
            'deadline' => '2027-01-01',
            'category' => Finding::KATEGORI_MAJOR,
        ])->assertOk();

        $fresh = $finding->fresh();
        $this->assertSame('2027-01-01', $fresh->deadline?->toDateString());
        $this->assertEquals(Finding::KATEGORI_MAJOR, $fresh->kategori);
    }
}
