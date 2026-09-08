<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use App\Policies\FindingPolicy;
use App\Services\ComplianceOfficerService;
use Illuminate\Auth\Access\AuthorizationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FindingCoverageGapTest extends TestCase
{
    protected User $admin;

    protected User $picA;

    protected User $picB;

    protected WorkUnit $unitA;

    protected WorkUnit $unitB;

    protected Control $control;

    protected function setUp(): void
    {
        parent::setUp();

        $adminRole = Role::where('name', 'admin_kepatuhan')->first();
        $picRole = Role::where('name', 'pic')->first();

        $this->unitA = WorkUnit::factory()->create();
        $this->unitB = WorkUnit::factory()->create();
        $this->admin = User::factory()->create(['role_id' => $adminRole->id, 'unit_id' => null]);
        $this->picA = User::factory()->create(['role_id' => $picRole->id, 'unit_id' => $this->unitA->id]);
        $this->picB = User::factory()->create(['role_id' => $picRole->id, 'unit_id' => $this->unitB->id]);

        $framework = Framework::factory()->create();
        $this->control = Control::factory()->create(['framework_id' => $framework->id]);
    }

    public function test_anonymous_blocked_on_temuan_web_routes(): void
    {
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $this->picA->id,
        ]);

        $this->get('/temuan')->assertRedirect(route('login'));
        $this->post('/temuan', [])->assertRedirect(route('login'));
        $this->put("/temuan/{$finding->id}", [])->assertRedirect(route('login'));
        $this->get('/admin/kepatuhan/temuan')->assertRedirect(route('login'));
    }

    public function test_anonymous_blocked_on_compliance_officer_finding_api(): void
    {
        $this->getJson('/api/v1/compliance-officer/findings')->assertUnauthorized();
        $this->postJson('/api/v1/compliance-officer/findings', [])->assertUnauthorized();
    }

    public function test_flat_temuan_page_renders_inertia_props(): void
    {
        $this->withoutVite();

        Finding::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $this->picA->id,
        ]);

        $this->actingAs($this->admin)->get('/temuan')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('admin-kepatuhan/temuan', false)
                ->has('findings.data')
                ->has('workUnits')
                ->has('controls')
                ->has('filters'));
    }

    public function test_web_create_and_update_assert_flash_message(): void
    {
        $create = $this->actingAs($this->admin)->from('/temuan')->post('/temuan', [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
            'status' => Finding::STATUS_OPEN,
            'catatan' => 'Flash assert create.',
        ]);

        $create->assertRedirect()->assertSessionHas('flash.type', 'success');
        $create->assertSessionHas('flash.message', 'Temuan audit baru berhasil diterbitkan.');

        $finding = Finding::latest('id')->first();

        $update = $this->actingAs($this->picA)->from('/temuan')->put("/temuan/{$finding->id}", [
            'status' => Finding::STATUS_IN_PROGRESS,
            'catatan' => 'Flash assert update.',
        ]);

        $update->assertRedirect()->assertSessionHas('flash.type', 'success');
        $update->assertSessionHas('flash.message', 'Temuan audit berhasil diperbarui.');
    }

    public function test_service_store_finding_rejects_unauthorized(): void
    {
        // Auditor lacks finding.create → service throws. Note: PIC holds
        // finding.create perm so service allows PIC (policy blocks at HTTP layer).
        $auditorRole = Role::where('name', 'auditor')->first();
        $auditor = User::factory()->create(['role_id' => $auditorRole->id, 'unit_id' => null]);
        $service = app(ComplianceOfficerService::class);

        $this->expectException(AuthorizationException::class);

        $service->storeFinding($auditor, [
            'control_id' => $this->control->id,
            'unit_id' => $this->unitA->id,
            'kategori' => Finding::KATEGORI_MINOR,
        ]);
    }

    public function test_service_update_finding_rejects_unauthorized(): void
    {
        $auditorRole = Role::where('name', 'auditor')->first();
        $auditor = User::factory()->create(['role_id' => $auditorRole->id, 'unit_id' => null]);
        $finding = Finding::factory()->create([
            'control_id' => $this->control->id, 'unit_id' => $this->unitA->id, 'pic_id' => $this->picA->id,
        ]);

        $service = app(ComplianceOfficerService::class);

        $this->expectException(AuthorizationException::class);

        $service->updateFinding($auditor, $finding, ['status' => Finding::STATUS_CLOSED]);
    }

    public function test_finding_policy_view_any_target_unit_scoping(): void
    {
        $policy = new FindingPolicy;

        $this->assertFalse($policy->viewAny($this->picA, $this->unitB->id));
        $this->assertTrue($policy->viewAny($this->picA, $this->unitA->id));
        $this->assertTrue($policy->viewAny($this->picA, null));
        $this->assertTrue($policy->viewAny($this->admin, $this->unitB->id));
    }
}
