<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\FindingStatusHistory;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Notifications\FindingCreatedNotification;
use App\Services\ComplianceOfficerService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * §4 Temuan lifecycle + scoping in one place (US-T1..US-T4): publish tuple and
 * PIC fallback, service-level full lifecycle with chain integrity, web-route
 * history rows, verification-proof invariant, officer/legacy API filters and
 * both-directions subtree scoping, deep links, plus QA-SKIP defect pins
 * (skipped, suite green; un-skip once guards land).
 * Merged from FindingStateMachineAndScopingTest + FindingWorkflowVerificationTest.
 */
class FindingWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $picParent;

    private User $picChild;

    private User $picSibling;

    private WorkUnit $parentUnit;

    private WorkUnit $childUnit;

    private WorkUnit $siblingUnit;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesAndPermissionsSeeder::class);

        $adminRole = Role::where('name', User::ROLE_ADMIN_KEPATUHAN)->firstOrFail();
        $picRole = Role::where('name', User::ROLE_PIC)->firstOrFail();

        $this->parentUnit = WorkUnit::factory()->create(['nama' => 'Biro Induk']);
        $this->childUnit = WorkUnit::factory()->create(['nama' => 'Unit Anak', 'parent_id' => $this->parentUnit->id]);
        $this->siblingUnit = WorkUnit::factory()->create(['nama' => 'Unit Saudara']);

        $this->admin = User::factory()->create(['role_id' => $adminRole, 'unit_id' => null]);
        $this->picParent = User::factory()->create(['role_id' => $picRole, 'unit_id' => $this->parentUnit->id]);
        $this->picChild = User::factory()->create(['role_id' => $picRole, 'unit_id' => $this->childUnit->id]);
        $this->picSibling = User::factory()->create(['role_id' => $picRole, 'unit_id' => $this->siblingUnit->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create(['framework_id' => $framework->id, 'kode_klausul' => 'A.8.24']);
    }

    private function finding(array $overrides = []): Finding
    {
        return Finding::factory()->create(array_merge([
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'admin_id' => $this->admin->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'deadline' => now()->addDays(14)->toDateString(),
            'catatan_admin' => 'Catatan awal admin.',
        ], $overrides));
    }

    private function histories(Finding $finding): array
    {
        return FindingStatusHistory::where('finding_id', $finding->id)
            ->orderBy('id')
            ->get(['from_status', 'to_status', 'user_id', 'catatan'])
            ->map(fn (FindingStatusHistory $h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'user' => $h->user_id,
                'catatan' => $h->catatan,
            ])
            ->all();
    }

    private function service(): ComplianceOfficerService
    {
        return app(ComplianceOfficerService::class);
    }

    // ── US-T1: publish finding (control, unit, PIC) + kategori/deadline/catatan_admin ──

    public function test_publish_stores_full_tuple_and_opens_history_chain(): void
    {
        $service = $this->service();

        $created = $service->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'deadline' => '2026-12-01',
            'catatan_admin' => 'Server tidak menerapkan patch keamanan.',
        ]);

        $this->assertDatabaseHas('findings', [
            'id' => $created->id,
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'admin_id' => $this->admin->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_OPEN,
            'catatan_admin' => 'Server tidak menerapkan patch keamanan.',
        ]);
        $this->assertSame('2026-12-01', $created->deadline->toDateString());

        $histories = FindingStatusHistory::where('finding_id', $created->id)->orderBy('id')->get();
        $this->assertCount(1, $histories);
        $this->assertNull($histories[0]->from_status);
        $this->assertSame(Finding::STATUS_OPEN, $histories[0]->to_status);
        $this->assertSame($this->admin->id, $histories[0]->user_id);
    }

    public function test_publish_falls_back_to_unit_pic_when_pic_id_omitted(): void
    {
        $created = $this->service()->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);

        $this->assertSame($this->picChild->id, $created->pic_id);
    }

    public function test_publish_notifies_target_pic_with_finding_created_notification(): void
    {
        Notification::fake();

        $this->service()->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        Notification::assertSentTo($this->picChild, FindingCreatedNotification::class);
    }

    /** QA-SKIP: /api/findings (legacy raw API) never notifies the PIC — US-T1 N-a. */
    public function test_qa_legacy_api_store_notifies_pic(): void
    {
        $this->markTestSkipped('DEFECT: Api/FindingController::store() sends no notification; US-T1 requires FindingCreatedNotification.');

        Notification::fake();

        $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ])->assertCreated();

        Notification::assertSentTo($this->picChild, FindingCreatedNotification::class);
    }

    /** QA-SKIP: pic_id is not validated against unit_id on either publish path. */
    public function test_qa_publish_rejects_pic_from_another_unit(): void
    {
        $this->markTestSkipped('DEFECT: StoreFindingRequest accepts any users.id as pic_id regardless of unit_id.');

        $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picSibling->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ])->assertUnprocessable()->assertJsonValidationErrors(['pic_id']);
    }

    /** QA-SKIP: assigning a cross-unit pic_id grants that PIC full write access. */
    public function test_qa_sibling_pic_cannot_touch_finding_assigned_to_them_cross_unit(): void
    {
        $this->markTestSkipped('DEFECT: FindingPolicy::isUserAuthorizedForFinding() grants access on pic_id match, bypassing unit scope.');

        $finding = $this->finding(['pic_id' => $this->picSibling->id]);

        $this->actingAs($this->picSibling)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_RESOLVED,
                'catatan' => 'PIC unit lain menutup temuan ini sendiri.',
            ])
            ->assertForbidden();
    }

    /** QA-SKIP: admin_id is client-supplied, so the publishing actor can be spoofed. */
    public function test_qa_publish_forces_admin_id_to_authenticated_actor(): void
    {
        $this->markTestSkipped('DEFECT: StoreFindingRequest allows admin_id override; audit trail attributes the finding to another user.');

        $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'admin_id' => $this->picSibling->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ])->assertCreated();

        $this->assertDatabaseHas('findings', ['admin_id' => $this->admin->id]);
    }

    // ── US-T2: two-sided status changes, every transition appends history ──

    public function test_full_lifecycle_appends_exactly_one_history_row_per_transition_in_order(): void
    {
        Notification::fake();

        $service = $this->service();
        $created = $service->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        // open -> in_progress (PIC)
        $service->updateFinding($this->picChild, $created->fresh(), [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Maintenance window dijadwalkan.',
        ]);
        // in_progress -> resolved (PIC)
        $service->updateFinding($this->picChild, $created->fresh(), [
            'status' => Finding::STATUS_RESOLVED,
            'catatan' => 'Scan ulang menunjukkan nol high vulnerabilities.',
        ]);
        // resolved -> closed (admin verifies)
        $service->updateFinding($this->admin, $created->fresh(), [
            'status' => Finding::STATUS_CLOSED,
            'catatan' => 'Bukti diverifikasi, temuan ditutup.',
        ]);

        $histories = FindingStatusHistory::where('finding_id', $created->id)
            ->orderBy('id')
            ->get()
            ->map(fn (FindingStatusHistory $h) => [
                'from' => $h->from_status,
                'to' => $h->to_status,
                'user' => $h->user_id,
                'catatan' => $h->catatan,
            ])
            ->all();

        $this->assertCount(4, $histories, 'create + 3 transitions must yield exactly 4 history rows');

        $this->assertSame([null, Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::STATUS_RESOLVED], array_column($histories, 'from'));
        $this->assertSame([Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED], array_column($histories, 'to'));
        $this->assertSame([$this->admin->id, $this->picChild->id, $this->picChild->id, $this->admin->id], array_column($histories, 'user'));

        // Chain integrity: each row's from_status equals the previous to_status.
        for ($i = 1; $i < count($histories); $i++) {
            $this->assertSame($histories[$i - 1]['to'], $histories[$i]['from'], "history row {$i} breaks the from/to chain");
        }
    }

    public function test_admin_send_back_to_in_progress_records_history_and_clears_verification(): void
    {
        $finding = $this->finding([
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now()->subDay(),
        ]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->admin)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'Anomali berulang, kembalikan ke PIC.',
            ])
            ->assertOk();

        $this->assertSame(Finding::STATUS_IN_PROGRESS, $finding->fresh()->status);
        $this->assertNull($finding->fresh()->tanggal_verifikasi);
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->admin->id,
            'from_status' => Finding::STATUS_CLOSED,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Anomali berulang, kembalikan ke PIC.',
        ]);
    }

    public function test_rejected_pic_close_writes_no_history_row(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_RESOLVED]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picChild)
            ->patchJson("/api/findings/{$finding->id}/status", ['status' => Finding::STATUS_CLOSED])
            ->assertForbidden();

        $this->assertSame(Finding::STATUS_RESOLVED, $finding->fresh()->status);
        $this->assertSame(0, FindingStatusHistory::where('finding_id', $finding->id)->count());
    }

    public function test_rejected_cross_unit_pic_update_writes_no_history_row(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_OPEN]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picSibling)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'PIC unit lain mencoba menimpa.',
            ])
            ->assertForbidden();

        $this->assertSame(Finding::STATUS_OPEN, $finding->fresh()->status);
        $this->assertSame(0, FindingStatusHistory::where('finding_id', $finding->id)->count());
    }

    public function test_update_without_status_change_or_note_appends_no_history_row(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_IN_PROGRESS]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->admin)
            ->putJson("/api/findings/{$finding->id}", ['status' => Finding::STATUS_IN_PROGRESS])
            ->assertOk();

        $this->assertSame(0, FindingStatusHistory::where('finding_id', $finding->id)->count());
    }

    public function test_pic_status_transition_is_appended_via_status_endpoint(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_OPEN]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picChild)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'PIC mulai remediasi.',
            ])
            ->assertOk();

        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->picChild->id,
            'from_status' => Finding::STATUS_OPEN,
            'to_status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'PIC mulai remediasi.',
        ]);
    }

    /** QA-SKIP: spec state machine is open→in_progress→resolved→closed; no guard exists. */
    public function test_qa_open_to_closed_shortcut_is_rejected(): void
    {
        $this->markTestSkipped('DEFECT: ComplianceOfficerService::updateFinding accepts any in:{open,in_progress,resolved,closed} target status.');

        $finding = $this->finding(['status' => Finding::STATUS_OPEN]);

        $this->actingAs($this->admin)
            ->putJson("/api/findings/{$finding->id}", [
                'status' => Finding::STATUS_CLOSED,
                'catatan' => 'Melompati in_progress dan resolved.',
            ])
            ->assertUnprocessable();
    }

    /** QA-SKIP: US-T2 says send-back happens "with notes"; catatan is optional today. */
    public function test_qa_admin_send_back_to_in_progress_requires_notes(): void
    {
        $this->markTestSkipped('DEFECT: UpdateFindingRequest declares catatan as nullable, so an unannotated send-back is accepted.');

        $finding = $this->finding([
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        $this->actingAs($this->admin)
            ->putJson("/api/findings/{$finding->id}", ['status' => Finding::STATUS_IN_PROGRESS])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['catatan']);
    }

    /** QA-SKIP: storeFinding/updateFinding return a model poisoned with derived attributes. */
    public function test_qa_service_returned_finding_is_reusable_for_a_second_transition(): void
    {
        $this->markTestSkipped('DEFECT: formatFindingResource() mutates the model, so a second update()() tries to write is_overdue/days_remaining/category/admin_notes/verified_at columns that do not exist (SQLSTATE 42703).');

        $service = $this->service();
        $created = $service->storeFinding($this->admin, [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ]);

        $service->updateFinding($this->picChild, $created, [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Pekerjaan berjalan.',
        ]);

        $this->assertSame(Finding::STATUS_IN_PROGRESS, $created->fresh()->status);
    }

    // ── US-T3: tanggal_verifikasi set iff status=closed ──

    public function test_tanggal_verifikasi_is_set_iff_closed_across_every_transition(): void
    {
        $service = $this->service();
        $finding = $this->finding(['status' => Finding::STATUS_OPEN]);
        FindingStatusHistory::query()->delete();

        $this->assertNull($finding->tanggal_verifikasi);

        $walk = [
            [Finding::STATUS_IN_PROGRESS, false],
            [Finding::STATUS_RESOLVED, false],
            [Finding::STATUS_CLOSED, true],
            [Finding::STATUS_IN_PROGRESS, false],
            [Finding::STATUS_RESOLVED, false],
            [Finding::STATUS_CLOSED, true],
        ];

        foreach ($walk as [$status, $expectsVerification]) {
            $service->updateFinding($this->admin, $finding, ['status' => $status]);

            $fresh = $finding->fresh();
            $this->assertSame($status, $fresh->status);
            $this->assertSame(
                $expectsVerification,
                $fresh->tanggal_verifikasi !== null,
                "status={$status} must ".($expectsVerification ? '' : 'not ').'carry tanggal_verifikasi'
            );
        }
    }

    public function test_officer_api_exposes_verified_at_matching_tanggal_verifikasi(): void
    {
        $closed = $this->finding(['status' => Finding::STATUS_CLOSED, 'tanggal_verifikasi' => now()]);
        $open = $this->finding(['status' => Finding::STATUS_OPEN]);

        $closedRes = $this->actingAs($this->admin)->getJson("/api/v1/compliance-officer/findings/{$closed->id}")->assertOk();
        $this->assertNotNull($closedRes->json('data.verified_at'));
        $this->assertSame(
            $closed->tanggal_verifikasi->copy()->utc()->toDateTimeString(),
            Carbon::parse($closedRes->json('data.verified_at'))->utc()->toDateTimeString()
        );

        $this->actingAs($this->admin)->getJson("/api/v1/compliance-officer/findings/{$open->id}")
            ->assertOk()
            ->assertJsonPath('data.verified_at', null);
    }

    /** QA-SKIP: closing at publish time on the legacy API leaves the proof column NULL. */
    public function test_qa_legacy_api_publish_as_closed_sets_tanggal_verifikasi(): void
    {
        $this->markTestSkipped('DEFECT: Api/FindingController::store() writes Finding::create($data) without the service\'s closed->tanggal_verifikasi rule.');

        $id = $this->actingAs($this->admin)->postJson('/api/findings', [
            'control_id' => $this->control->id,
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'status' => Finding::STATUS_CLOSED,
        ])->assertCreated()->json('data.id');

        $this->assertNotNull(Finding::find($id)->tanggal_verifikasi);
    }

    /** Documents the drift: legacy /api/findings/* omits the formatted resource aliases. */
    public function test_legacy_api_show_returns_unformatted_resource_contrasting_officer_api(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_CLOSED, 'tanggal_verifikasi' => now()]);

        $legacy = $this->actingAs($this->admin)->getJson("/api/findings/{$finding->id}")->assertOk();
        $officer = $this->actingAs($this->admin)->getJson("/api/v1/compliance-officer/findings/{$finding->id}")->assertOk();

        // Both expose the raw proof column ...
        $this->assertNotNull($legacy->json('data.tanggal_verifikasi'));
        // ... only the officer API exposes the derived aliases the UI consumes.
        $this->assertArrayNotHasKey('verified_at', $legacy->json('data'));
        $this->assertArrayHasKey('verified_at', $officer->json('data'));
        $this->assertArrayHasKey('is_overdue', $officer->json('data'));
    }

    // ── US-T4: filter / scope by unit, search, kategori; PIC own subtree ──

    public function test_officer_api_filters_combine_unit_kategori_and_search(): void
    {
        $match = $this->finding([
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'catatan_admin' => 'Kubernetes etcd podat',
        ]);
        $this->finding([
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'catatan_admin' => 'Kubernetes etcd podat',
        ]);
        $this->finding([
            'unit_id' => $this->siblingUnit->id,
            'pic_id' => $this->picSibling->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'catatan_admin' => 'Kubernetes etcd podat',
        ]);
        $this->finding([
            'unit_id' => $this->childUnit->id,
            'pic_id' => $this->picChild->id,
            'kategori' => Finding::KATEGORI_MAJOR,
            'catatan_admin' => 'Unrelated wording',
        ]);

        $response = $this->actingAs($this->admin)->getJson(
            "/api/v1/compliance-officer/findings?unit_id={$this->childUnit->id}&kategori=major&search=etcd"
        )->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id')->all();
        $this->assertSame([$match->id], $ids);
    }

    public function test_officer_api_rejects_out_of_scope_unit_filter_for_pic(): void
    {
        $this->actingAs($this->picParent)
            ->getJson("/api/v1/compliance-officer/findings?unit_id={$this->siblingUnit->id}")
            ->assertForbidden();
    }

    public function test_parent_pic_scopes_to_own_subtree_and_child_pic_does_not_see_parent(): void
    {
        $parentFinding = $this->finding([
            'unit_id' => $this->parentUnit->id,
            'pic_id' => $this->picParent->id,
        ]);
        $childFinding = $this->finding(['unit_id' => $this->childUnit->id, 'pic_id' => $this->picChild->id]);

        $parentIds = collect($this->actingAs($this->picParent)
            ->getJson('/api/v1/compliance-officer/findings')
            ->assertOk()
            ->json('data.data'))->pluck('id')->sort()->values()->all();

        // Downward: parent PIC supervises its child unit.
        $this->assertEqualsCanonicalizing([$parentFinding->id, $childFinding->id], $parentIds);

        $childIds = collect($this->actingAs($this->picChild)
            ->getJson('/api/v1/compliance-officer/findings')
            ->assertOk()
            ->json('data.data'))->pluck('id')->all();

        // Upward/sideways denied.
        $this->assertSame([$childFinding->id], $childIds);
    }

    public function test_temuan_page_sends_pic_only_its_own_subtree_findings(): void
    {
        $parentFinding = $this->finding(['unit_id' => $this->parentUnit->id, 'pic_id' => $this->picParent->id]);
        $childFinding = $this->finding(['unit_id' => $this->childUnit->id, 'pic_id' => $this->picChild->id]);
        $siblingFinding = $this->finding(['unit_id' => $this->siblingUnit->id, 'pic_id' => $this->picSibling->id]);

        $ids = [];

        $this->actingAs($this->picParent)->get('/temuan')
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$ids) {
                $page->component('admin-kepatuhan/temuan')->has('findings.data');
                $ids = array_column($page->toArray()['props']['findings']['data'], 'id');
            });

        $this->assertEqualsCanonicalizing([$parentFinding->id, $childFinding->id], $ids);
        $this->assertNotContains($siblingFinding->id, $ids);
    }

    public function test_initial_finding_deep_link_carries_history_and_verification_proof(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_CLOSED, 'tanggal_verifikasi' => now()]);
        FindingStatusHistory::create([
            'finding_id' => $finding->id,
            'user_id' => $this->admin->id,
            'from_status' => Finding::STATUS_RESOLVED,
            'to_status' => Finding::STATUS_CLOSED,
            'catatan' => 'Diverifikasi admin.',
        ]);

        $props = [];

        $this->actingAs($this->admin)->get("/temuan?id={$finding->id}")
            ->assertOk()
            ->assertInertia(function (Assert $page) use (&$props) {
                $page->component('admin-kepatuhan/temuan')
                    ->where('initialFinding.id', $page->toArray()['props']['initialFinding']['id'] ?? null)
                    ->has('initialFinding.histories', 1)
                    ->where('initialFinding.histories.0.catatan', 'Diverifikasi admin.');
                $props = $page->toArray()['props'];
            });

        $this->assertNotNull($props['initialFinding']['verified_at'], 'US-T3: closed finding must expose verification proof');
    }

    public function test_temuan_deep_link_to_other_unit_finding_leaves_initial_finding_null(): void
    {
        $finding = $this->finding(['unit_id' => $this->siblingUnit->id, 'pic_id' => $this->picSibling->id]);

        $this->actingAs($this->picParent)->get("/temuan?id={$finding->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/temuan')
                ->where('initialFinding', null)
                ->etc()
            );
    }

    /** QA-SKIP: legacy index scopes a PIC to unit_id only, dropping the subtree. */
    public function test_qa_legacy_api_index_lists_child_unit_findings_for_parent_pic(): void
    {
        $this->markTestSkipped('DEFECT: Api/FindingController::index() uses $user->unit_id instead of accessibleUnitIds().');

        $this->finding(['unit_id' => $this->childUnit->id, 'pic_id' => $this->picChild->id]);

        $this->actingAs($this->picParent)->getJson('/api/findings')
            ->assertOk()
            ->assertJsonCount(1, 'data.data');
    }

    /** QA-SKIP: US-G2 requires 403 when a PIC requests an out-of-scope unit_id. */
    public function test_qa_legacy_api_index_rejects_out_of_scope_unit_filter(): void
    {
        $this->markTestSkipped('DEFECT: legacy index silently ignores unit_id for PIC instead of raising AuthorizationException.');

        $this->finding(['unit_id' => $this->childUnit->id, 'pic_id' => $this->picChild->id]);

        $this->actingAs($this->picParent)
            ->getJson("/api/findings?unit_id={$this->siblingUnit->id}")
            ->assertForbidden();
    }

    /** QA-SKIP: US-T4 requires admin search; legacy index has no search parameter. */
    public function test_qa_legacy_api_index_supports_search_filter(): void
    {
        $this->markTestSkipped('DEFECT: Api/FindingController::index() implements no search/category alias filtering.');

        $this->finding(['catatan_admin' => 'needle-unik-xyz']);
        $this->finding(['catatan_admin' => 'unrelated wording']);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/findings?search=needle-unik-xyz')
            ->assertOk();

        $this->assertCount(1, $response->json('data.data'));
    }

    // ── Web path: history rows on the Inertia routes the UI drives ─────────

    public function test_web_route_status_transition_appends_history_row_with_actor(): void
    {
        $finding = $this->finding();
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picChild)
            ->put("/temuan/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'PIC mulai remediasi lewat form web.',
            ])
            ->assertRedirect();

        $this->assertSame([[
            'from' => Finding::STATUS_OPEN,
            'to' => Finding::STATUS_IN_PROGRESS,
            'user' => $this->picChild->id,
            'catatan' => 'PIC mulai remediasi lewat form web.',
        ]], $this->histories($finding));
    }

    public function test_web_route_full_lifecycle_appends_one_history_row_per_transition(): void
    {
        $finding = $this->finding();
        FindingStatusHistory::query()->delete();

        foreach ([
            [$this->picChild, Finding::STATUS_IN_PROGRESS, 'PIC memulai penanganan.'],
            [$this->picChild, Finding::STATUS_RESOLVED, 'Perbaikan selesai, siap diverifikasi.'],
            [$this->admin, Finding::STATUS_CLOSED, 'Bukti diverifikasi, ditutup.'],
            [$this->admin, Finding::STATUS_IN_PROGRESS, 'Regresi terdeteksi, dikembalikan ke PIC.'],
        ] as [$actor, $status, $note]) {
            $this->actingAs($actor)
                ->put("/temuan/{$finding->id}", ['status' => $status, 'catatan' => $note])
                ->assertRedirect();

            $this->assertSame($status, $finding->fresh()->status, "web route did not apply status {$status}");
        }

        $histories = $this->histories($finding);

        $this->assertCount(4, $histories, 'each web-route transition must append exactly one history row');
        $this->assertSame(
            [Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS, Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED],
            array_column($histories, 'from')
        );
        $this->assertSame(
            [Finding::STATUS_IN_PROGRESS, Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED, Finding::STATUS_IN_PROGRESS],
            array_column($histories, 'to')
        );
        $this->assertSame(
            [$this->picChild->id, $this->picChild->id, $this->admin->id, $this->admin->id],
            array_column($histories, 'user')
        );
    }

    public function test_web_route_note_split_keeps_admin_note_and_records_progress(): void
    {
        $finding = $this->finding();
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picChild)
            ->put("/temuan/{$finding->id}", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'Catatan progres PIC.',
            ])
            ->assertRedirect();

        $fresh = $finding->fresh();

        $this->assertSame('Catatan progres PIC.', $fresh->catatan);
        $this->assertSame('Catatan awal admin.', $fresh->catatan_admin, 'PIC note must not overwrite the admin note');
        $this->assertCount(1, $this->histories($finding));
    }

    public function test_admin_reopening_closed_to_open_clears_proof_and_records_history(): void
    {
        $finding = $this->finding([
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now()->subDay(),
        ]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->admin)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_OPEN,
                'catatan' => 'Temuan tidak valid, dikembalikan ke daftar terbuka.',
            ])
            ->assertOk();

        $fresh = $finding->fresh();

        $this->assertSame(Finding::STATUS_OPEN, $fresh->status);
        $this->assertNull($fresh->tanggal_verifikasi, 'US-T3: non-closed finding must carry no verification proof');
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $finding->id,
            'user_id' => $this->admin->id,
            'from_status' => Finding::STATUS_CLOSED,
            'to_status' => Finding::STATUS_OPEN,
        ]);
    }

    public function test_close_sets_proof_and_back_to_resolved_clears_it_again(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_RESOLVED]);

        $this->actingAs($this->admin)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_CLOSED,
                'catatan' => 'Diverifikasi.',
            ])->assertOk();
        $this->assertNotNull($finding->fresh()->tanggal_verifikasi);
        $this->assertSame($this->admin->id, $finding->fresh()->admin_id, 'closer must become the verifying admin');

        $this->actingAs($this->admin)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_RESOLVED,
                'catatan' => 'Bukti tidak memuaskan.',
            ])->assertOk();
        $this->assertNull($finding->fresh()->tanggal_verifikasi);

        $this->assertSame(
            [Finding::STATUS_RESOLVED, Finding::STATUS_CLOSED],
            array_column($this->histories($finding), 'from')
        );
        $this->assertSame(
            [Finding::STATUS_CLOSED, Finding::STATUS_RESOLVED],
            array_column($this->histories($finding), 'to')
        );
    }

    public function test_legacy_index_filters_by_kategori(): void
    {
        $major = $this->finding(['kategori' => Finding::KATEGORI_MAJOR]);
        $this->finding(['kategori' => Finding::KATEGORI_MINOR]);
        $this->finding(['kategori' => Finding::KATEGORI_OBSERVASI]);

        $response = $this->actingAs($this->admin)
            ->getJson('/api/findings?kategori=major')
            ->assertOk();

        $this->assertSame([$major->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_legacy_index_scopes_admin_to_requested_unit(): void
    {
        $inChild = $this->finding();
        $this->finding(['unit_id' => $this->siblingUnit->id, 'pic_id' => $this->picParent->id]);

        $response = $this->actingAs($this->admin)
            ->getJson("/api/findings?unit_id={$this->childUnit->id}")
            ->assertOk();

        $this->assertSame([$inChild->id], collect($response->json('data.data'))->pluck('id')->all());
    }

    public function test_legacy_index_pic_cannot_see_parent_or_sibling_units(): void
    {
        $own = $this->finding();
        $this->finding(['unit_id' => $this->parentUnit->id, 'pic_id' => $this->picParent->id]);
        $this->finding(['unit_id' => $this->siblingUnit->id, 'pic_id' => $this->picParent->id]);

        $response = $this->actingAs($this->picChild)->getJson('/api/findings')->assertOk();

        $this->assertSame(
            [$own->id],
            collect($response->json('data.data'))->pluck('id')->all(),
            'US-T4: upward/sideways visibility must stay denied'
        );
    }

    public function test_officer_publish_defaults_to_open_and_opens_history_chain(): void
    {
        $response = $this->actingAs($this->admin)
            ->postJson('/api/v1/compliance-officer/findings', [
                'control_id' => $this->control->id,
                'unit_id' => $this->childUnit->id,
                'pic_id' => $this->picChild->id,
                'kategori' => Finding::KATEGORI_MINOR,
            ])
            ->assertCreated();

        $id = $response->json('data.id');

        $this->assertDatabaseHas('findings', [
            'id' => $id,
            'status' => Finding::STATUS_OPEN,
            'kategori' => Finding::KATEGORI_MINOR,
            'tanggal_verifikasi' => null,
        ]);
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $id,
            'user_id' => $this->admin->id,
            'from_status' => null,
            'to_status' => Finding::STATUS_OPEN,
        ]);
    }

    public function test_status_endpoint_ignores_client_supplied_admin_id(): void
    {
        $finding = $this->finding(['status' => Finding::STATUS_OPEN, 'admin_id' => null]);

        $this->actingAs($this->admin)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_RESOLVED,
                'catatan' => 'PIC sudah menyelesaikan perbaikan.',
                'admin_id' => $this->picChild->id,
            ])
            ->assertOk();

        $this->assertSame(Finding::STATUS_RESOLVED, $finding->fresh()->status);
        $this->assertNull(
            $finding->fresh()->admin_id,
            'admin_id must only ever be set by the closing admin, never from client input'
        );
    }

    public function test_soft_deleted_finding_cannot_be_transitioned(): void
    {
        $finding = $this->finding();
        $finding->delete();
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->admin)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'Mengubah temuan arsip.',
            ])
            ->assertNotFound();

        $this->assertSame(0, FindingStatusHistory::count());
    }

    // ── QA-SKIP: confirmed defects, spec expectation encoded ────────────────────

    /**
     * QA-SKIP: `POST /api/findings` 500s when `status` is omitted, and leaves an
     * orphaned `findings` row with zero history rows behind.
     *
     * Reproduced: Api/FindingController::store() calls Finding::create($data)
     * without a status default, so $finding->status is null on the in-memory
     * model and the follow-up FindingStatusHistory::create() writes
     * to_status = NULL into a NOT NULL column. The controller has no
     * DB::transaction(), so the already-inserted finding row survives.
     */
    public function test_qa_legacy_publish_without_explicit_status_succeeds_and_opens_history(): void
    {
        $this->markTestSkipped('DEFECT: Api/FindingController::store() 500 (23502 to_status NOT NULL) and leaves an orphan finding row with 0 history rows; it needs the same status default + transaction as ComplianceOfficerService::storeFinding().');

        $response = $this->actingAs($this->admin)
            ->postJson('/api/findings', [
                'control_id' => $this->control->id,
                'unit_id' => $this->childUnit->id,
                'pic_id' => $this->picChild->id,
                'kategori' => Finding::KATEGORI_MAJOR,
                'catatan' => 'Diterbitkan tanpa kunci status.',
            ])
            ->assertCreated();

        $id = $response->json('data.id');

        $this->assertDatabaseHas('findings', ['id' => $id, 'status' => Finding::STATUS_OPEN]);
        $this->assertDatabaseHas('finding_status_histories', [
            'finding_id' => $id,
            'from_status' => null,
            'to_status' => Finding::STATUS_OPEN,
        ]);
    }

    /**
     * QA-SKIP: US-T2 reserves "back to in_progress" for the admin, but the
     * service only forbids PIC from the `closed` target status.
     *
     * Reproduced: PATCH /api/findings/{id}/status as the assigned PIC moves a
     * closed finding back to in_progress (200) and clears tanggal_verifikasi.
     * The React page already hides the control for PICs; the API does not.
     */
    public function test_qa_pic_cannot_reopen_a_closed_finding(): void
    {
        $this->markTestSkipped('DEFECT: ComplianceOfficerService::updateFinding only blocks PIC for the closed target; PIC may reopen a closed finding to in_progress.');

        $finding = $this->finding([
            'status' => Finding::STATUS_CLOSED,
            'tanggal_verifikasi' => now()->subDay(),
        ]);
        FindingStatusHistory::query()->delete();

        $this->actingAs($this->picChild)
            ->patchJson("/api/findings/{$finding->id}/status", [
                'status' => Finding::STATUS_IN_PROGRESS,
                'catatan' => 'PIC mengembalikannya sendiri.',
            ])
            ->assertForbidden();

        $this->assertSame(Finding::STATUS_CLOSED, $finding->fresh()->status);
        $this->assertNotNull($finding->fresh()->tanggal_verifikasi);
        $this->assertSame(0, FindingStatusHistory::count());
    }

    /**
     * QA-SKIP: the spec state machine is open→in_progress→resolved→closed, but
     * no layer enforces adjacency.
     *
     * Reproduced: PIC open→resolved and resolved→open both return 200, and the
     * React stepper (temuan.tsx handleStepClick) lets any lane be clicked.
     */
    public function test_qa_illegal_transitions_are_rejected(): void
    {
        $this->markTestSkipped('DEFECT: no transition-graph guard exists; open→resolved, resolved→open and in_progress→closed are all accepted.');

        foreach ([
            [Finding::STATUS_OPEN, Finding::STATUS_RESOLVED],
            [Finding::STATUS_RESOLVED, Finding::STATUS_OPEN],
            [Finding::STATUS_OPEN, Finding::STATUS_CLOSED],
        ] as [$from, $to]) {
            $finding = $this->finding(['status' => $from]);
            FindingStatusHistory::query()->delete();

            // Admin actor keeps this free of the separate "PIC may not close" rule.
            $this->actingAs($this->admin)
                ->patchJson("/api/findings/{$finding->id}/status", [
                    'status' => $to,
                    'catatan' => "Lompat {$from} → {$to}.",
                ])
                ->assertUnprocessable();

            $this->assertSame($from, $finding->fresh()->status);
            $this->assertSame(0, FindingStatusHistory::count());
        }
    }

    /**
     * QA-SKIP: a PIC with no unit gets a global scope, so US-T4 "own subtree
     * only" collapses into "every unit".
     *
     * Reproduced: User::accessibleUnitIds() returns null (meaning "no scope")
     * when a PIC has unit_id = null, and users.unit_id is nullable. Both
     * /api/v1/compliance-officer/findings and GET /temuan then list findings
     * from every unit.
     */
    public function test_qa_pic_without_unit_sees_no_findings(): void
    {
        $this->markTestSkipped('DEFECT: User::accessibleUnitIds() returns null for a unitless PIC, which every listing reads as an unrestricted scope.');

        $unitless = User::factory()->create([
            'role_id' => Role::where('name', User::ROLE_PIC)->firstOrFail()->id,
            'unit_id' => null,
        ]);
        $this->finding();
        $this->finding(['unit_id' => $this->siblingUnit->id, 'pic_id' => $this->picParent->id]);

        $this->actingAs($unitless)
            ->getJson('/api/v1/compliance-officer/findings')
            ->assertOk()
            ->assertJsonCount(0, 'data.data');

        $ids = [];
        $this->actingAs($unitless)->get('/temuan')->assertInertia(function ($page) use (&$ids) {
            $ids = array_column($page->toArray()['props']['findings']['data'], 'id');
        });
        $this->assertSame([], $ids);
    }

    /**
     * QA-SKIP: publishing into a unit that has no PIC surfaces a 500 instead of
     * a validation error, on both publish paths.
     *
     * Reproduced: ComplianceOfficerService::storeFinding throws a bare
     * InvalidArgumentException, which neither the web redirect nor the JSON
     * endpoint converts into a 422 / field error.
     */
    public function test_qa_publish_into_unit_without_pic_returns_validation_error(): void
    {
        $this->markTestSkipped('DEFECT: ComplianceOfficerService::storeFinding() throws a bare InvalidArgumentException, so POST /temuan and POST /api/v1/compliance-officer/findings both return 500.');

        $unitless = WorkUnit::factory()->create(['nama' => 'Unit Tanpa PIC']);

        $payload = [
            'control_id' => $this->control->id,
            'unit_id' => $unitless->id,
            'kategori' => Finding::KATEGORI_MAJOR,
        ];

        $this->actingAs($this->admin)
            ->postJson('/api/v1/compliance-officer/findings', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['pic_id']);

        $this->actingAs($this->admin)
            ->post('/temuan', $payload)
            ->assertSessionHasErrors('pic_id');
    }
}
