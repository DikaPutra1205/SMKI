<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Risk;
use App\Models\User;
use App\Models\WorkUnit;
use App\Services\Concerns\ResolvesUnitScope;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * US-G2 + §11 PIC scoping matrix in one place: the ResolvesUnitScope seam
 * contract (unit filter narrowing, 403 outside grants) plus the both-directions
 * subtree matrix (parent PIC sees child-unit rows everywhere; child PIC never
 * sees parent/sibling rows) across risks, findings, dashboard, reports, and
 * the work-units picker prop.
 * Merged from PicSubtreeScopeMatrixTest + ResolvesUnitScopeTest.
 */
class UnitScopeTest extends TestCase
{
    use RefreshDatabase;

    private function seam(): object
    {
        return new class
        {
            use ResolvesUnitScope { resolveScopedUnitIds as public; }
        };
    }

    private function tree(): array
    {
        $root = WorkUnit::create(['nama' => 'Root']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $root->id]);
        $other = WorkUnit::create(['nama' => 'Unrelated']);

        return compact('root', 'child', 'other');
    }

    private WorkUnit $root;

    private WorkUnit $child;

    private WorkUnit $grandchild;

    private WorkUnit $sibling;

    private User $parentPic;

    private User $childPic;

    private User $siblingPic;

    private User $admin;

    private Framework $fw;

    private Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $this->root = WorkUnit::factory()->create(['nama' => 'Root']);
        $this->child = WorkUnit::factory()->create(['nama' => 'Child', 'parent_id' => $this->root->id]);
        $this->grandchild = WorkUnit::factory()->create(['nama' => 'Grandchild', 'parent_id' => $this->child->id]);
        $this->sibling = WorkUnit::factory()->create(['nama' => 'Sibling']);

        $this->parentPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->root->id]);
        $this->childPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->child->id]);
        $this->siblingPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->sibling->id]);
        $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $this->fw = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->control = $this->fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'teknologi',
        ]);
    }

    private function riskFor(WorkUnit $unit, string $owner): Risk
    {
        return Risk::factory()->withControl($this->control)->create([
            'unit_id' => $unit->id,
            'pemilik_risiko' => $owner,
            'level_risiko' => Risk::LEVEL_HIGH,
            'status' => Risk::STATUS_OPEN,
        ]);
    }

    // ── accessibleUnitIds is the shared basis for both surfaces ────────────

    public function test_accessible_unit_ids_resolve_the_subtree_in_both_directions(): void
    {
        $this->assertEqualsCanonicalizing(
            [$this->root->id, $this->child->id, $this->grandchild->id],
            $this->parentPic->accessibleUnitIds(),
            'parent PIC harus melihat unit turunannya'
        );
        $this->assertEqualsCanonicalizing(
            [$this->child->id, $this->grandchild->id],
            $this->childPic->accessibleUnitIds(),
            'child PIC hanya melihat unit turunannya, bukan induknya'
        );
        $this->assertEquals([$this->sibling->id], $this->siblingPic->accessibleUnitIds());
    }

    // ── Risks: list ────────────────────────────────────────────────────────

    public function test_parent_pic_risk_register_lists_own_child_and_grandchild_units(): void
    {
        $own = $this->riskFor($this->root, 'Risiko Root');
        $childRisk = $this->riskFor($this->child, 'Risiko Child');
        $grandchildRisk = $this->riskFor($this->grandchild, 'Risiko Grandchild');
        $siblingRisk = $this->riskFor($this->sibling, 'Risiko Sibling');

        foreach (['/api/risks', '/api/v1/compliance-officer/risks'] as $endpoint) {
            $ids = collect($this->actingAs($this->parentPic)->getJson($endpoint)->assertOk()->json('data.data'))
                ->pluck('id')
                ->all();

            $this->assertContains($own->id, $ids, "{$endpoint}: unit sendiri");
            $this->assertContains($childRisk->id, $ids, "{$endpoint}: unit anak");
            $this->assertContains($grandchildRisk->id, $ids, "{$endpoint}: unit cucu");
            $this->assertNotContains($siblingRisk->id, $ids, "{$endpoint}: unit saudara di luar lingkup");
        }
    }

    public function test_child_pic_risk_register_never_lists_parent_or_sibling_units(): void
    {
        $parentRisk = $this->riskFor($this->root, 'Risiko Root');
        $ownRisk = $this->riskFor($this->child, 'Risiko Child');
        $grandchildRisk = $this->riskFor($this->grandchild, 'Risiko Grandchild');
        $siblingRisk = $this->riskFor($this->sibling, 'Risiko Sibling');

        foreach (['/api/risks', '/api/v1/compliance-officer/risks'] as $endpoint) {
            $ids = collect($this->actingAs($this->childPic)->getJson($endpoint)->assertOk()->json('data.data'))
                ->pluck('id')
                ->all();

            $this->assertContains($ownRisk->id, $ids, "{$endpoint}: unit sendiri");
            $this->assertContains($grandchildRisk->id, $ids, "{$endpoint}: unit cucu");
            $this->assertNotContains($parentRisk->id, $ids, "{$endpoint}: unit induk");
            $this->assertNotContains($siblingRisk->id, $ids, "{$endpoint}: unit saudara");
        }
    }

    // ── Risks: show / update ───────────────────────────────────────────────

    public function test_parent_pic_can_open_and_update_child_unit_risk(): void
    {
        $childRisk = $this->riskFor($this->child, 'Risiko Child');
        $siblingRisk = $this->riskFor($this->sibling, 'Risiko Sibling');

        foreach (['/api/risks', '/api/v1/compliance-officer/risks'] as $base) {
            $this->actingAs($this->parentPic)->getJson("{$base}/{$childRisk->id}")->assertOk();
            $this->actingAs($this->parentPic)->getJson("{$base}/{$siblingRisk->id}")->assertForbidden();
        }

        $this->actingAs($this->parentPic)
            ->putJson("/api/risks/{$childRisk->id}", ['rencana_mitigasi' => 'Mitigasi oleh parent PIC'])
            ->assertOk();

        $this->assertSame('Mitigasi oleh parent PIC', $childRisk->fresh()->rencana_mitigasi);
    }

    public function test_child_pic_cannot_open_or_update_parent_unit_risk(): void
    {
        $parentRisk = $this->riskFor($this->root, 'Risiko Root');
        $ownRisk = $this->riskFor($this->child, 'Risiko Child');

        foreach (['/api/risks', '/api/v1/compliance-officer/risks'] as $base) {
            $this->actingAs($this->childPic)->getJson("{$base}/{$parentRisk->id}")->assertForbidden();
            $this->actingAs($this->childPic)->getJson("{$base}/{$ownRisk->id}")->assertOk();
        }

        $this->actingAs($this->childPic)
            ->putJson("/api/risks/{$parentRisk->id}", ['rencana_mitigasi' => '不应 bisa'])
            ->assertForbidden();

        $this->assertNotSame('不应 bisa', $parentRisk->fresh()->rencana_mitigasi);
    }

    // ── Dashboard: unit_comparisons / summary scoping ──────────────────────

    private function seedSessionFor(WorkUnit $unit, string $status = ChecklistEntry::WORKFLOW_SELESAI, ?string $label = null): ChecklistSession
    {
        $session = ChecklistSession::factory()->create([
            'konteks_penilaian' => $label ?? 'Sesi '.$unit->nama,
            'unit_id' => $unit->id,
            'framework_id' => $this->fw->id,
            'periode' => now()->format('Y-m'),
        ]);

        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'unit_id' => $unit->id,
            'status' => $status,
        ]);

        return $session;
    }

    /** @return array<string, mixed> the `summary` prop of the PIC dashboard */
    private function picSummary(User $pic): array
    {
        return $this->picDashboardProps($pic)['summary'];
    }

    /** @return array<string, mixed> every prop of the PIC dashboard */
    private function picDashboardProps(User $pic): array
    {
        $response = $this->actingAs($pic)->get('/admin/pic/dashboard');
        $response->assertOk()->assertInertia(fn (Assert $page) => $page->component('pic/dashboard')->has('summary'));

        return (array) $response->viewData('page')['props'];
    }

    public function test_parent_pic_dashboard_unit_comparisons_cover_the_whole_subtree(): void
    {
        $this->seedSessionFor($this->root);
        $this->seedSessionFor($this->child);
        $this->seedSessionFor($this->grandchild);
        $this->seedSessionFor($this->sibling);

        $ids = collect($this->unitComparisonIds($this->parentPic))->all();

        $this->assertContains($this->root->id, $ids, 'unit sendiri');
        $this->assertContains($this->child->id, $ids, 'unit anak');
        $this->assertContains($this->grandchild->id, $ids, 'unit cucu');
        $this->assertNotContains($this->sibling->id, $ids, 'unit saudara di luar lingkup');
    }

    public function test_child_pic_dashboard_excludes_parent_and_sibling_units(): void
    {
        $this->seedSessionFor($this->root);
        $this->seedSessionFor($this->child);
        $this->seedSessionFor($this->grandchild);
        $this->seedSessionFor($this->sibling);

        $ids = collect($this->unitComparisonIds($this->childPic))->all();

        $this->assertContains($this->child->id, $ids, 'unit sendiri');
        $this->assertContains($this->grandchild->id, $ids, 'unit cucu');
        $this->assertNotContains($this->root->id, $ids, 'unit induk');
        $this->assertNotContains($this->sibling->id, $ids, 'unit saudara');
    }

    /** @return array<int, int> unit ids from GET /api/v1/dashboard/unit-comparison */
    private function unitComparisonIds(User $pic): array
    {
        return collect($this->actingAs($pic)
            ->getJson('/api/v1/dashboard/unit-comparison')
            ->assertOk()
            ->json('data'))
            ->pluck('unit_id')
            ->all();
    }

    public function test_parent_pic_dashboard_counts_only_subtree_findings(): void
    {
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->child->id,
            'status' => Finding::STATUS_OPEN,
        ]);
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->grandchild->id,
            'status' => Finding::STATUS_OPEN,
        ]);
        Finding::factory()->create([
            'control_id' => $this->control->id,
            'unit_id' => $this->sibling->id,
            'status' => Finding::STATUS_OPEN,
        ]);

        $summary = $this->picSummary($this->parentPic);

        $this->assertSame(2, $summary['findings_summary']['total_active'], 'dua temuan dalam subtree, bukan tiga');
    }

    public function test_dashboard_compliance_rate_ignores_units_outside_the_pic_subtree(): void
    {
        // Root and sibling are 0% compliant; child and grandchild are 100%.
        $this->seedSessionFor($this->root, ChecklistEntry::WORKFLOW_BELUM_DIMULAI);
        $this->seedSessionFor($this->sibling, ChecklistEntry::WORKFLOW_BELUM_DIMULAI);
        $this->seedSessionFor($this->child, ChecklistEntry::WORKFLOW_SELESAI);
        $this->seedSessionFor($this->grandchild, ChecklistEntry::WORKFLOW_SELESAI);

        $this->assertSame(100, $this->picSummary($this->childPic)['overall_completion_rate']);

        // Parent scope = root + child + grandchild → 2 of 3 units compliant.
        $this->assertSame(
            67,
            $this->picSummary($this->parentPic)['overall_completion_rate'],
            'rata-rata subtree (2 dari 3 unit patuh) — unit saudara tidak boleh ikut dihitung'
        );
    }

    public function test_dashboard_trends_endpoint_is_scoped_in_both_directions(): void
    {
        $this->seedSessionFor($this->root, ChecklistEntry::WORKFLOW_BELUM_DIMULAI);
        $this->seedSessionFor($this->sibling, ChecklistEntry::WORKFLOW_BELUM_DIMULAI);
        $this->seedSessionFor($this->child, ChecklistEntry::WORKFLOW_SELESAI);

        $parentRows = $this->actingAs($this->parentPic)
            ->getJson('/api/v1/dashboard/trends?months=3')
            ->assertOk()
            ->json('data');

        $childRows = $this->actingAs($this->childPic)
            ->getJson('/api/v1/dashboard/trends?months=3')
            ->assertOk()
            ->json('data');

        $this->assertCount(3, $parentRows);
        $this->assertCount(3, $childRows);

        $current = now()->format('Y-m');

        // Child PIC: only the child's session is in scope → fully compliant.
        $this->assertSame(100, collect($childRows)->firstWhere('period', $current)['overall_rate']);

        // Parent PIC: root's 0% session is in scope next to the child's 100% one,
        // so the subtree average is 50%. Were the sibling to leak in it would be 33%.
        $this->assertSame(50, collect($parentRows)->firstWhere('period', $current)['overall_rate']);
    }

    /**
     * GAP: PicDashboardController@index builds `recent_sessions` with
     * `where('unit_id', $user->unit_id)` (PicDashboardController.php:31) — the
     * user's own unit only — while the same page's `summary` prop is scoped by
     * `accessibleUnitIds()` (own + descendants). §11 requires a parent PIC to
     * see child-unit rows on the dashboard, so a supervising PIC sees the child
     * units' aggregate compliance rate in the summary but cannot open or find
     * their sessions in the list right below it.
     *
     * The assertions below pin the current (own-unit-only) behaviour.
     */
    public function test_gap_pic_dashboard_recent_sessions_lists_only_the_pics_own_unit(): void
    {
        $this->seedSessionFor($this->root, ChecklistEntry::WORKFLOW_SELESAI, 'Sesi Root');
        $this->seedSessionFor($this->child, ChecklistEntry::WORKFLOW_SELESAI, 'Sesi Child');
        $this->seedSessionFor($this->sibling, ChecklistEntry::WORKFLOW_SELESAI, 'Sesi Sibling');

        $props = $this->picDashboardProps($this->parentPic);
        $labels = collect($props['recent_sessions'])->pluck('konteks_penilaian')->all();

        $this->assertContains('Sesi Root', $labels, 'unit sendiri tetap tampil');
        $this->assertNotContains(
            'Sesi Child',
            $labels,
            'GAP: sesi unit anak tidak muncul meski ringkasan menjumlahkannya'
        );
        $this->assertNotContains('Sesi Sibling', $labels);
    }

    /**
     * GAP: DashboardAnalyticsService::getTrends() hardcodes `framework_id = 1`
     * for the `iso27001_rate` column and `framework_id = 2` for `iso27701_rate`
     * (DashboardAnalyticsService.php:274-277) instead of resolving the framework
     * by name. The ids happen to be 1 and 2 on a freshly seeded database, but
     * §10 puts "add new Frameworks at runtime" IN SCOPE — so as soon as a
     * superadmin creates a framework (or a framework was soft-deleted and
     * re-seeded, see FrameworkSeeder), the per-standard trend columns are
     * attributed to the wrong standard while `overall_rate` keeps counting them.
     *
     * The assertions below pin the current (id-addressed) behaviour.
     */
    public function test_gap_trends_attaches_standard_columns_by_framework_id_not_name(): void
    {
        // Push both standards off ids 1 and 2 by seeding unrelated frameworks first.
        Framework::factory()->count(2)->create();
        $iso27001 = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $iso27701 = Framework::factory()->create(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);
        $this->assertGreaterThan(2, $iso27001->id);
        $this->assertGreaterThan(2, $iso27701->id);

        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create([
            'unit_id' => $unit->id,
            'framework_id' => $iso27001->id,
            'periode' => now()->format('Y-m'),
        ]);
        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'control_id' => $this->control->id,
            'unit_id' => $unit->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
        ]);

        $row = collect($this->actingAs($this->admin)
            ->getJson('/api/v1/dashboard/trends?months=1')
            ->assertOk()
            ->json('data'))->first();

        // GAP: the fully-compliant ISO 27001 entry lands in `overall_rate` but
        // never in `iso27001_rate`, because the query looks at framework_id 1.
        $this->assertSame(100, $row['overall_rate']);
        $this->assertSame(0, $row['iso27001_rate'], 'GAP: lajur ISO 27001 kosong karena id bukan 1');
        $this->assertSame(0, $row['iso27701_rate']);
    }

    // ── Seam branches (ResolvesUnitScope.php:18) ──────────────────────────

    public function test_no_filter_returns_the_whole_accessible_scope(): void
    {
        ['root' => $root, 'child' => $child, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->assertEqualsCanonicalizing(
            [$root->id, $child->id],
            $this->seam()->resolveScopedUnitIds($pic)
        );
    }

    public function test_in_scope_filter_narrows_to_the_requested_unit(): void
    {
        ['root' => $root, 'child' => $child] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->assertSame([$child->id], $this->seam()->resolveScopedUnitIds($pic, ['unit_id' => $child->id]));
    }

    public function test_out_of_scope_filter_throws_authorization_exception(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->expectException(AuthorizationException::class);
        $this->expectExceptionMessage('Unit di luar lingkup akses Anda.');

        $this->seam()->resolveScopedUnitIds($pic, ['unit_id' => $other->id]);
    }

    public function test_numeric_string_filter_is_accepted_for_in_scope_unit(): void
    {
        ['root' => $root, 'child' => $child] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->assertSame([$child->id], $this->seam()->resolveScopedUnitIds($pic, ['unit_id' => (string) $child->id]));
    }

    public function test_blank_filter_is_treated_as_absent(): void
    {
        ['root' => $root, 'child' => $child] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->assertEqualsCanonicalizing(
            [$root->id, $child->id],
            $this->seam()->resolveScopedUnitIds($pic, ['unit_id' => ''])
        );
    }

    public function test_global_user_may_request_any_unit(): void
    {
        ['other' => $other] = $this->tree();
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->assertSame([$other->id], $this->seam()->resolveScopedUnitIds($superadmin, ['unit_id' => $other->id]));
        $this->assertNull($this->seam()->resolveScopedUnitIds($superadmin));
    }

    // ── HTTP contract: 403 outside grants, 200 inside ─────────────────────

    public function test_dashboard_summary_rejects_out_of_scope_unit(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)
            ->getJson("/api/v1/dashboard/summary?unit_id={$other->id}")
            ->assertForbidden();
    }

    public function test_dashboard_summary_accepts_own_and_descendant_units(): void
    {
        ['root' => $root, 'child' => $child] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)->getJson("/api/v1/dashboard/summary?unit_id={$root->id}")->assertOk();
        $this->actingAs($pic)->getJson("/api/v1/dashboard/summary?unit_id={$child->id}")->assertOk();
    }

    public function test_dashboard_trends_rejects_out_of_scope_unit(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)
            ->getJson("/api/v1/dashboard/trends?unit_id={$other->id}")
            ->assertForbidden();
    }

    public function test_compliance_report_rejects_out_of_scope_unit(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)
            ->getJson("/api/v1/reports/compliance-summary?unit_id={$other->id}")
            ->assertForbidden();
    }

    public function test_global_role_may_request_any_unit_on_dashboard(): void
    {
        ['other' => $other] = $this->tree();
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $this->actingAs($admin)
            ->getJson("/api/v1/dashboard/summary?unit_id={$other->id}")
            ->assertOk();
    }

    /**
     * Divergence from the ResolvesUnitScope contract: the risk listing scopes
     * with accessibleUnitIds() directly (Api/RiskController.php:26) and silently
     * drops a PIC's `unit_id` filter instead of raising 403. Scope still holds —
     * only the status code differs.
     */
    public function test_pic_unit_filter_cannot_widen_risk_listing_scope(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);
        $mine = Risk::factory()->create(['unit_id' => $root->id]);
        $theirs = Risk::factory()->create(['unit_id' => $other->id]);

        $response = $this->actingAs($pic)->getJson("/api/risks?unit_id={$other->id}")->assertOk();

        $ids = collect($response->json('data.data'))->pluck('id');
        $this->assertTrue($ids->contains($mine->id));
        $this->assertFalse($ids->contains($theirs->id));
    }

    public function test_web_risk_page_rejects_out_of_scope_unit_filter(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)
            ->get("/risks?unit_id={$other->id}")
            ->assertForbidden();

        $this->actingAs($pic)
            ->get("/admin/kepatuhan/risks?unit_id={$other->id}")
            ->assertForbidden();
    }

    public function test_web_finding_page_rejects_out_of_scope_unit_filter(): void
    {
        ['root' => $root, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)
            ->get("/temuan?unit_id={$other->id}")
            ->assertForbidden();

        $this->actingAs($pic)
            ->get("/admin/kepatuhan/temuan?unit_id={$other->id}")
            ->assertForbidden();
    }

    public function test_web_risk_page_accepts_own_and_descendant_unit_filter(): void
    {
        ['root' => $root, 'child' => $child] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $this->actingAs($pic)->get("/risks?unit_id={$root->id}")->assertOk();
        $this->actingAs($pic)->get("/risks?unit_id={$child->id}")->assertOk();
    }

    public function test_web_risk_page_without_filter_returns_the_pics_whole_subtree(): void
    {
        ['root' => $root, 'child' => $child, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $own = Risk::factory()->create(['unit_id' => $root->id]);
        $childRisk = Risk::factory()->create(['unit_id' => $child->id]);
        $theirs = Risk::factory()->create(['unit_id' => $other->id]);

        $props = $this->actingAs($pic)->get('/risks')
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['risks']['data'] ?? $props['risks'])->pluck('id');

        $this->assertTrue($ids->contains($own->id), 'risiko unit sendiri harus terlihat');
        $this->assertTrue($ids->contains($childRisk->id), 'risiko unit anak harus terlihat');
        $this->assertFalse($ids->contains($theirs->id), 'risiko unit di luar lingkup tidak boleh bocor');
    }

    /**
     * ComplianceService::getWorkUnits() (app/Services/ComplianceService.php:54) is
     * the only unit listing that is NOT routed through ResolvesUnitScope — it scopes
     * inline with accessibleUnitIds() — and it feeds the `workUnits` prop on the risk
     * page. Nothing asserted its subtree direction before this.
     */
    public function test_work_units_picker_prop_covers_the_parent_pics_whole_subtree(): void
    {
        ['root' => $root, 'child' => $child, 'other' => $other] = $this->tree();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $root->id]);

        $props = $this->actingAs($pic)->get('/risks')
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['workUnits'])->pluck('id');

        $this->assertTrue($ids->contains($root->id), 'unit sendiri harus ada di picker');
        $this->assertTrue($ids->contains($child->id), 'unit anak harus ada di picker');
        $this->assertFalse($ids->contains($other->id), 'unit saudara tidak boleh bocor ke picker');
    }

    public function test_work_units_picker_prop_excludes_parent_and_sibling_for_child_pic(): void
    {
        $root = WorkUnit::create(['nama' => 'Root']);
        $child = WorkUnit::create(['nama' => 'Child', 'parent_id' => $root->id]);
        $sibling = WorkUnit::create(['nama' => 'Sibling']);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $child->id]);

        $props = $this->actingAs($pic)->get('/risks')
            ->assertOk()
            ->viewData('page')['props'];

        $ids = collect($props['workUnits'])->pluck('id');

        $this->assertSame([$child->id], $ids->all());
        $this->assertFalse($ids->contains($root->id), 'PIC anak tidak boleh melihat unit induk');
        $this->assertFalse($ids->contains($sibling->id), 'PIC anak tidak boleh melihat unit saudara');
    }

    public function test_work_units_picker_prop_is_global_for_non_pic_roles(): void
    {
        ['other' => $other] = $this->tree();
        $admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
            'unit_id' => $other->id,
        ]);

        $props = $this->actingAs($admin)->get('/risks')
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertTrue(collect($props['workUnits'])->pluck('id')->contains($other->id));
    }
}
