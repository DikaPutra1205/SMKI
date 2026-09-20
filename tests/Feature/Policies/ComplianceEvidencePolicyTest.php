<?php

namespace Tests\Feature\Policies;

use App\Models\ChecklistEntry;
use App\Models\ComplianceEvidence;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class ComplianceEvidencePolicyTest extends TestCase
{
    private WorkUnit $unitA;

    private WorkUnit $unitB;

    private ChecklistEntry $entryA;

    private ChecklistEntry $entryB;

    private User $picA;

    private User $picB;

    private User $admin;

    private User $superadmin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitA = WorkUnit::create(['nama' => 'Unit A']);
        $this->unitB = WorkUnit::create(['nama' => 'Unit B']);

        $this->picA = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitA->id]);
        $this->picB = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unitB->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $fw = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Test', 'kategori' => 'teknologi',
        ]);

        $this->entryA = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $this->picA->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
        $this->entryB = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $this->unitB->id, 'pic_id' => $this->picB->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);
    }

    public function test_view_any_pic_own_unit(): void
    {
        $this->assertTrue(Gate::forUser($this->picA)->allows('viewAny', [ComplianceEvidence::class, $this->entryA]));
    }

    public function test_view_any_pic_cross_unit_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picB)->allows('viewAny', [ComplianceEvidence::class, $this->entryA]));
    }

    public function test_view_any_admin_any_entry(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('viewAny', [ComplianceEvidence::class, $this->entryA]));
    }

    public function test_view_own_evidence(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertTrue($this->picA->can('view', $evidence));
    }

    public function test_view_cross_unit_evidence_denied(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertFalse($this->picB->can('view', $evidence));
    }

    public function test_view_deleted_entry_evidence_returns_true(): void
    {
        $entry = $this->entryA->fresh();
        $this->entryA->delete();
        $evidence = ComplianceEvidence::create([
            'checklist_entry_id' => $entry->id, 'uploaded_by' => $this->picA->id,
            'file_url' => 'bukti/1/a.pdf', 'version_number' => 1,
            'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertTrue($this->picA->can('view', $evidence));
    }

    public function test_create_pic_own_entry(): void
    {
        $this->assertTrue(Gate::forUser($this->picA)->allows('create', [ComplianceEvidence::class, $this->entryA, $this->picA->id]));
    }

    public function test_create_pic_cross_entry_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picB)->allows('create', [ComplianceEvidence::class, $this->entryA, $this->picB->id]));
    }

    public function test_create_pic_spoofed_uploaded_by_denied(): void
    {
        $this->assertFalse(Gate::forUser($this->picA)->allows('create', [ComplianceEvidence::class, $this->entryA, $this->picB->id]));
    }

    public function test_create_admin_any_entry(): void
    {
        $this->assertTrue(Gate::forUser($this->admin)->allows('create', [ComplianceEvidence::class, $this->entryA]));
    }

    public function test_delete_own_entry_evidence(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertTrue($this->picA->can('delete', $evidence));
    }

    public function test_delete_cross_unit_evidence_denied(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertFalse($this->picB->can('delete', $evidence));
    }

    public function test_restore_own_entry_evidence(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertTrue($this->picA->can('restore', $evidence));
    }

    public function test_restore_cross_unit_evidence_denied(): void
    {
        $evidence = $this->entryA->evidences()->create([
            'uploaded_by' => $this->picA->id, 'file_url' => 'bukti/1/a.pdf',
            'version_number' => 1, 'is_active' => true, 'uploaded_at' => now(),
        ]);

        $this->assertFalse($this->picB->can('restore', $evidence));
    }
}
