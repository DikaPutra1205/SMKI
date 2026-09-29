<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * FUNCTIONAL_SPEC §2 — Master data (US-M1..US-M4).
 *
 * Section A/B/C/D close coverage gaps against the spec'd permission matrix.
 * Section E documents live defects: each test PINS the current (insecure)
 * behaviour so the gap is reproducible, and each is named `test_gap_*`.
 * Rename/flip those assertions when the controller is fixed.
 */
class MasterDataScopeTest extends TestCase
{
    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function framework(): Framework
    {
        return Framework::create(['nama' => 'ISO '.fake()->unique()->numerify('###'), 'versi' => '2022']);
    }

    // ── A. Permission matrix (US-G3 deviation note; US-M1/M2/M3/M4) ──────────

    public function test_admin_kepatuhan_can_create_control_via_api(): void
    {
        $framework = $this->framework();

        $this->actingAs($this->user(User::ROLE_ADMIN_KEPATUHAN))
            ->postJson('/api/controls', [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.5.1',
                'judul' => 'Policies',
                'kategori' => 'organisasional',
            ])
            ->assertCreated();

        $this->assertDatabaseHas('controls', [
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.5.1',
        ]);
    }

    public function test_admin_kepatuhan_denied_users_page(): void
    {
        // US-M3: user.* is superadmin only — admin_kepatuhan has just user.profileview.
        $this->actingAs($this->user(User::ROLE_ADMIN_KEPATUHAN))
            ->get('/admin/superadmin/users')
            ->assertForbidden();
    }

    public function test_admin_kepatuhan_denied_unit_mutations(): void
    {
        // US-M4: admin_kepatuhan holds work-unit.read but not create/update/delete.
        $unit = WorkUnit::factory()->create(['nama' => 'Unit Kost']);
        $admin = $this->user(User::ROLE_ADMIN_KEPATUHAN);

        $this->actingAs($admin)
            ->post('/admin/superadmin/units', ['nama' => 'Unit Baru'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->patch("/admin/superadmin/units/{$unit->id}", ['nama' => 'Diretas'])
            ->assertForbidden();

        $this->actingAs($admin)
            ->delete("/admin/superadmin/units/{$unit->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'nama' => 'Unit Kost']);
        $this->assertDatabaseMissing('work_units', ['nama' => 'Unit Baru']);
    }

    public function test_pic_denied_users_and_units_pages(): void
    {
        $actor = $this->user(User::ROLE_PIC);

        $this->actingAs($actor)->get('/admin/superadmin/users')->assertForbidden();
        $this->actingAs($actor)->get('/admin/superadmin/units')->assertForbidden();
    }

    /**
     * US-G3 deviation note: framework CRUD belongs to superadmin AND
     * admin_kepatuhan. Only control *create* was asserted before this; the
     * framework surface and every delete were untested, so a regression that
     * stripped admin_kepatuhan's framework grants — or handed framework delete
     * to a role that should not have it — would have gone unnoticed.
     */
    public function test_admin_kepatuhan_can_crud_frameworks_on_both_surfaces(): void
    {
        $admin = $this->user(User::ROLE_ADMIN_KEPATUHAN);

        // Web: create
        $this->actingAs($admin)
            ->post('/admin/superadmin/frameworks', ['nama' => 'ISO 27701:2025', 'versi' => '2025'])
            ->assertRedirect();
        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27701:2025']);

        $framework = Framework::where('nama', 'ISO 27701:2025')->firstOrFail();

        // Web: update
        $this->actingAs($admin)
            ->patch("/admin/superadmin/frameworks/{$framework->id}", ['versi' => '2025.1'])
            ->assertRedirect();
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'versi' => '2025.1']);

        // Web: delete
        $this->actingAs($admin)
            ->delete("/admin/superadmin/frameworks/{$framework->id}")
            ->assertRedirect();
        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);

        // API: full lifecycle for the same role
        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'NIST CSF 2.0', 'versi' => '2.0'])
            ->assertCreated();
        $apiFramework = Framework::where('nama', 'NIST CSF 2.0')->firstOrFail();

        $this->actingAs($admin)
            ->patchJson("/api/frameworks/{$apiFramework->id}", ['versi' => '2.1'])
            ->assertOk();
        $this->assertDatabaseHas('frameworks', ['id' => $apiFramework->id, 'versi' => '2.1']);

        $this->actingAs($admin)
            ->deleteJson("/api/frameworks/{$apiFramework->id}")
            ->assertOk();
        $this->assertSoftDeleted('frameworks', ['id' => $apiFramework->id]);
    }

    public function test_admin_kepatuhan_can_update_and_delete_controls(): void
    {
        $admin = $this->user(User::ROLE_ADMIN_KEPATUHAN);
        $framework = $this->framework();
        $control = $framework->controls()->create([
            'kode_klausul' => 'A.7.1', 'judul' => 'Original', 'kategori' => 'organisasional',
        ]);

        $this->actingAs($admin)
            ->put("/admin/kepatuhan/controls/{$control->id}", [
                'framework_id' => $framework->id,
                'kode_klausul' => 'A.7.1',
                'judul' => 'Web Title',
                'kategori' => 'organisasional',
            ])
            ->assertRedirect();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Web Title']);

        $this->actingAs($admin)
            ->putJson("/api/controls/{$control->id}", ['judul' => 'Diretas Oleh Admin'])
            ->assertOk();
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Diretas Oleh Admin']);

        $this->actingAs($admin)
            ->deleteJson("/api/controls/{$control->id}")
            ->assertOk();
        $this->assertSoftDeleted('controls', ['id' => $control->id]);
    }

    /**
     * The negative half of the same deviation: the two roles that hold
     * framework.read/control.read must be refused on every master-data write.
     * None of these verbs was asserted for them anywhere.
     */
    #[DataProvider('rolesOutsideTheMasterDataMatrix')]
    public function test_roles_outside_the_master_data_matrix_cannot_write_controls_or_frameworks(string $role): void
    {
        $actor = $this->user($role);
        $framework = $this->framework();
        $control = $framework->controls()->create([
            'kode_klausul' => 'A.8.1', 'judul' => 'Asli', 'kategori' => 'teknologi',
        ]);

        $this->actingAs($actor)
            ->post('/admin/superadmin/frameworks', ['nama' => 'Buatan '.$role, 'versi' => '1'])
            ->assertForbidden();
        $this->actingAs($actor)
            ->patch("/admin/superadmin/frameworks/{$framework->id}", ['versi' => '9'])
            ->assertForbidden();
        $this->actingAs($actor)
            ->delete("/admin/superadmin/frameworks/{$framework->id}")
            ->assertForbidden();

        $this->actingAs($actor)
            ->postJson('/api/controls', [
                'framework_id' => $framework->id, 'kode_klausul' => 'X.1', 'judul' => 'X',
                'kategori' => 'teknologi',
            ])
            ->assertForbidden();
        $this->actingAs($actor)
            ->patchJson("/api/controls/{$control->id}", ['judul' => 'Dibajak'])
            ->assertForbidden();
        $this->actingAs($actor)
            ->deleteJson("/api/controls/{$control->id}")
            ->assertForbidden();
        $this->actingAs($actor)
            ->deleteJson("/api/frameworks/{$framework->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('frameworks', ['nama' => 'Buatan '.$role]);
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id]);
        $this->assertDatabaseHas('controls', ['id' => $control->id, 'judul' => 'Asli']);
    }

    public static function rolesOutsideTheMasterDataMatrix(): array
    {
        return [
            'koordinator_smki' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    /**
     * GAP: authorization is not the first gate on the FormRequest-backed write
     * actions. Laravel validates an injected FormRequest (StoreControlRequest)
     * before the controller body runs, so `Gate::authorize('control.create')`
     * at Api\ControlController.php:61 executes only after the payload has been
     * validated. A role that must be refused outright instead receives 422 plus
     * the full field contract of the endpoint. No mutation occurs, so this is
     * an information-disclosure / defence-ordering issue, not a write bypass.
     * The same shape holds for every `Gate::authorize`-after-FormRequest
     * controller (Api\FrameworkController, Web\ControlController, Web\FrameworkController).
     *
     * Fix: move the grant check into the FormRequest's `authorize()` method
     * (Laravel runs it before validation) or into route middleware.
     */
    public function test_gap_unauthorized_caller_gets_validation_feedback_before_the_403(): void
    {
        $pic = $this->user(User::ROLE_PIC);
        $framework = $this->framework();

        $response = $this->actingAs($pic)->postJson('/api/controls', [
            'framework_id' => $framework->id,
            'kode_klausul' => 'A.1.1',
            // `kategori` deliberately omitted
        ]);

        $this->assertSame(422, $response->status());
        $response->assertJsonValidationErrors('kategori');
        $this->assertDatabaseMissing('controls', ['kode_klausul' => 'A.1.1']);
    }

    /**
     * GAP: config/permissions.php:260,282 grant `user.managementview` + `user.read`
     * to koordinator_smki and auditor, so US-G3's "superadmin exclusively manages
     * users" does not hold — both roles render the full user directory page.
     * Mutations stay denied (no user.create/update/delete).
     */
    public function test_gap_koordinator_and_auditor_can_open_the_user_directory(): void
    {
        $target = User::factory()->create(['role' => User::ROLE_PIC]);

        foreach ([User::ROLE_KOORDINATOR_SMKI, User::ROLE_AUDITOR] as $role) {
            $actor = $this->user($role);

            $this->actingAs($actor)
                ->get('/admin/superadmin/users')
                ->assertOk()
                ->assertInertia(fn ($p) => $p->component('superadmin/users')
                    ->where('users', fn ($rows) => collect($rows)->contains('email', $target->email)));

            // Read-only: the write grants are absent.
            $this->actingAs($actor)
                ->patch("/admin/superadmin/users/{$target->id}", [
                    'name' => 'Diretas', 'email' => $target->email, 'role_id' => $target->role_id,
                ])
                ->assertForbidden();

            $this->actingAs($actor)->delete("/admin/superadmin/users/{$target->id}")->assertForbidden();
        }

        $this->assertDatabaseHas('users', ['id' => $target->id, 'name' => $target->name]);
    }

    public function test_anonymous_blocked_from_every_master_data_web_route(): void
    {
        $this->get('/admin/superadmin/frameworks')->assertRedirect('/login');
        $this->get('/admin/superadmin/users')->assertRedirect('/login');
        $this->get('/admin/superadmin/units')->assertRedirect('/login');
        $this->post('/admin/superadmin/frameworks', ['nama' => 'X', 'versi' => '1'])->assertRedirect('/login');
        $this->post('/admin/superadmin/users', ['name' => 'X', 'email' => 'x@e.test'])->assertRedirect('/login');
        $this->post('/admin/superadmin/units', ['nama' => 'X'])->assertRedirect('/login');
        $this->post('/admin/kepatuhan/controls', ['kode_klausul' => 'X'])->assertRedirect('/login');
    }

    public function test_anonymous_blocked_from_every_master_data_api_route(): void
    {
        $unit = WorkUnit::factory()->create();

        $this->getJson('/api/users')->assertUnauthorized();
        $this->getJson('/api/controls')->assertUnauthorized();
        $this->getJson('/api/frameworks')->assertUnauthorized();
        $this->getJson('/api/work-units')->assertUnauthorized();
        $this->getJson('/api/work-units-tree')->assertUnauthorized();
        $this->postJson('/api/work-units', ['nama' => 'X'])->assertUnauthorized();
        $this->patchJson("/api/work-units/{$unit->id}", ['nama' => 'X'])->assertUnauthorized();
        $this->deleteJson("/api/work-units/{$unit->id}")->assertUnauthorized();
        $this->postJson('/api/frameworks', ['nama' => 'X', 'versi' => '1'])->assertUnauthorized();
    }

    // ── B. Not-found + empty state ────────────────────────────────────────────

    public function test_master_data_web_mutations_404_on_unknown_record(): void
    {
        $admin = $this->user(User::ROLE_SUPERADMIN);

        $this->actingAs($admin)->patch('/admin/superadmin/frameworks/999999', ['versi' => 'x'])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/superadmin/frameworks/999999')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/superadmin/users/999999', [
            'name' => 'X', 'email' => 'x@e.test', 'role_id' => 1,
        ])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/superadmin/users/999999')->assertNotFound();
        $this->actingAs($admin)->patch('/admin/superadmin/units/999999', ['nama' => 'X'])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/superadmin/units/999999')->assertNotFound();
        $this->actingAs($admin)->put('/admin/kepatuhan/controls/999999', ['judul' => 'X'])->assertNotFound();
        $this->actingAs($admin)->delete('/admin/kepatuhan/controls/999999')->assertNotFound();
    }

    public function test_master_data_pages_render_with_empty_tables(): void
    {
        $admin = $this->user(User::ROLE_SUPERADMIN);

        $this->actingAs($admin)->get('/admin/superadmin/frameworks')
            ->assertOk()->assertInertia(fn ($p) => $p->component('superadmin/frameworks')->where('frameworks', []));

        $this->actingAs($admin)->get('/admin/superadmin/units')
            ->assertOk()->assertInertia(fn ($p) => $p->component('superadmin/units')->where('units', []));

        // The only row is the acting superadmin — no seeded org data leaks in.
        $this->actingAs($admin)->get('/admin/superadmin/users')
            ->assertOk()->assertInertia(fn ($p) => $p->component('superadmin/users')
            ->where('users', fn ($rows) => count($rows) === 1 && $rows[0]['id'] === $admin->id));
    }

    // ── C. Inertia server props / no sensitive leak ───────────────────────────

    public function test_users_page_props_never_expose_password_or_token(): void
    {
        User::factory()->create(['role' => User::ROLE_PIC]);

        $response = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get('/admin/superadmin/users')
            ->assertOk();

        $props = $response->viewData('page')['props'];

        $this->assertArrayHasKey('users', $props);
        $this->assertArrayHasKey('roles', $props);
        $this->assertArrayHasKey('units', $props);

        foreach ($props['users'] as $row) {
            $this->assertArrayNotHasKey('password', $row);
            $this->assertArrayNotHasKey('remember_token', $row);
            $this->assertSame(
                ['id', 'name', 'email', 'role_id', 'unit_id', 'role', 'unit'],
                array_keys($row)
            );
        }
    }

    public function test_units_page_props_expose_parent_relation_for_the_tree(): void
    {
        $parent = WorkUnit::factory()->create(['nama' => 'Induk']);
        WorkUnit::factory()->create(['nama' => 'Anak', 'parent_id' => $parent->id]);

        $props = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get('/admin/superadmin/units')
            ->assertOk()
            ->viewData('page')['props'];

        $byName = collect($props['units'])->keyBy('nama');

        $this->assertSame($parent->id, $byName['Anak']['parent']['id']);
        $this->assertSame('Induk', $byName['Anak']['parent']['nama']);
        $this->assertNull($byName['Induk']['parent']);
    }

    public function test_frameworks_page_props_include_control_counts_and_search_filter(): void
    {
        $framework = $this->framework();
        $framework->controls()->create([
            'kode_klausul' => 'A.1.1', 'judul' => 'Policies', 'kategori' => 'organisasional',
        ]);

        $props = $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->get("/admin/superadmin/frameworks?search={$framework->nama}")
            ->assertOk()
            ->viewData('page')['props'];

        $this->assertCount(1, $props['frameworks']);
        $this->assertSame(1, $props['frameworks'][0]['controls_count']);
        $this->assertSame($framework->nama, $props['frameworks'][0]['nama']);
        $this->assertArrayNotHasKey('password', $props['frameworks'][0]);
    }

    // ── D. US-M3 observer through the real write path ─────────────────────────

    public function test_pic_created_via_web_endpoint_claims_unowned_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->count(2)->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);
        $picRole = Role::where('name', 'pic')->firstOrFail();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post('/admin/superadmin/users', [
                'name' => 'PIC Baru',
                'email' => 'pic.baru@example.test',
                'role_id' => $picRole->id,
                'unit_id' => $unit->id,
            ])
            ->assertRedirect();

        $pic = User::where('email', 'pic.baru@example.test')->firstOrFail();

        $this->assertSame(User::ROLE_PIC, $pic->role);
        $this->assertSame($unit->id, $pic->unit_id);
        // SmkiObserver::saved (app/Observers/SmkiObserver.php:74)
        $this->assertSame(2, ChecklistEntry::where('unit_id', $unit->id)->where('pic_id', $pic->id)->count());
    }

    public function test_creating_non_pic_with_unit_does_not_claim_entries(): void
    {
        $unit = WorkUnit::factory()->create();
        $session = ChecklistSession::factory()->create(['unit_id' => $unit->id]);
        ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'unit_id' => $unit->id,
            'pic_id' => null,
        ]);
        $role = Role::where('name', 'auditor')->firstOrFail();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post('/admin/superadmin/users', [
                'name' => 'Auditor Baru',
                'email' => 'auditor.baru@example.test',
                'role_id' => $role->id,
                'unit_id' => $unit->id,
            ])
            ->assertRedirect();

        $this->assertSame(0, ChecklistEntry::whereNotNull('pic_id')->count());
    }

    public function test_superadmin_can_reassign_pic_unit_and_role(): void
    {
        $from = WorkUnit::factory()->create();
        $to = WorkUnit::factory()->create();
        $picRole = Role::where('name', 'pic')->firstOrFail();
        $auditorRole = Role::where('name', 'auditor')->firstOrFail();
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $from->id]);

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->patch("/admin/superadmin/users/{$pic->id}", [
                'name' => $pic->name,
                'email' => $pic->email,
                'role_id' => $auditorRole->id,
                'unit_id' => $to->id,
            ])
            ->assertRedirect();

        $pic->refresh();
        $this->assertSame(User::ROLE_AUDITOR, $pic->role);
        $this->assertSame($to->id, $pic->unit_id);
    }

    // ── E. Live defects — pinned current behaviour ───────────────────────────

    /** GAP: Api\WorkUnitController::store (app/Http/Controllers/Api/WorkUnitController.php:34) has no Gate::authorize. */
    /** FIXED: Api\WorkUnitController gates create on work-unit.create (US-G3). */
    public function test_gap_pic_can_create_work_unit_via_api(): void
    {
        $this->actingAs($this->user(User::ROLE_PIC))
            ->postJson('/api/work-units', ['nama' => 'Unit Buatan Pic'])
            ->assertForbidden();

        $this->assertDatabaseMissing('work_units', ['nama' => 'Unit Buatan Pic']);
    }

    /** FIXED: Api\WorkUnitController::update gates on work-unit.update + model cycle guard. */
    public function test_gap_pic_can_update_work_unit_via_api(): void
    {
        $unit = WorkUnit::factory()->create(['nama' => 'Asli']);

        $this->actingAs($this->user(User::ROLE_PIC))
            ->patchJson("/api/work-units/{$unit->id}", ['nama' => 'Diubah Pic'])
            ->assertForbidden();

        $this->assertDatabaseHas('work_units', ['id' => $unit->id, 'nama' => 'Asli']);
    }

    /**
     * FIXED: Api\WorkUnitController::destroy gates on work-unit.delete + enforces
     * child/user guard, so children/users never orphan.
     */
    public function test_gap_pic_can_delete_work_unit_via_api_orphaning_children_and_users(): void
    {
        $parent = WorkUnit::factory()->create(['nama' => 'Induk']);
        WorkUnit::factory()->create(['nama' => 'Anak', 'parent_id' => $parent->id]);
        User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $parent->id]);

        $this->actingAs($this->user(User::ROLE_PIC))
            ->deleteJson("/api/work-units/{$parent->id}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('work_units', ['id' => $parent->id]);
    }

    /** FIXED: Api\UserController::index gates on user.read — pic gets 403. */
    public function test_gap_pic_can_read_full_user_directory_via_api(): void
    {
        User::factory()->create(['role' => User::ROLE_SUPERADMIN, 'name' => 'Super Rahasia']);

        $this->actingAs($this->user(User::ROLE_PIC))
            ->getJson('/api/users')
            ->assertForbidden();
    }

    /**
     * FIXED: Web\FrameworkController::destroy authorizes before deleting storage,
     * so a 403'd caller leaves the document intact.
     */
    public function test_gap_unauthorized_framework_delete_still_removes_document_from_storage(): void
    {
        Storage::fake('frameworks');
        Storage::disk('frameworks')->put('frameworks/iso.pdf', '%PDF-1.4');
        $framework = $this->framework();
        $framework->update(['url_file' => 'frameworks/iso.pdf']);

        $this->actingAs($this->user(User::ROLE_KOORDINATOR_SMKI))
            ->delete("/admin/superadmin/frameworks/{$framework->id}")
            ->assertForbidden();

        // 403 returned, file preserved.
        Storage::disk('frameworks')->assertExists('frameworks/iso.pdf');
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id]);
    }

    /** FIXED: same ordering on API surface — authorize before deleteExisting. */
    public function test_gap_unauthorized_framework_api_delete_still_removes_document_from_storage(): void
    {
        Storage::fake('frameworks');
        Storage::disk('frameworks')->put('frameworks/iso.pdf', '%PDF-1.4');
        $framework = $this->framework();
        $framework->update(['url_file' => 'frameworks/iso.pdf']);

        $this->actingAs($this->user(User::ROLE_KOORDINATOR_SMKI))
            ->deleteJson("/api/frameworks/{$framework->id}")
            ->assertForbidden();

        Storage::disk('frameworks')->assertExists('frameworks/iso.pdf');
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id]);
    }

    /**
     * GAP: StoreUserRequest has no `password` rule (app/Http/Requests/StoreUserRequest.php:21)
     * but Web\UserController::store falls back to a hardcoded default
     * (app/Http/Controllers/Web/UserController.php:50) and the UI form sends none —
     * so every UI-created account shares one publicly-known credential, and
     * forgotPassword is a stub that mails nothing.
     */
    public function test_gap_new_user_gets_known_default_password(): void
    {
        $role = Role::where('name', 'pic')->firstOrFail();

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->post('/admin/superadmin/users', [
                'name' => 'Tanpa Password',
                'email' => 'tanpa.password@example.test',
                'role_id' => $role->id,
            ])
            ->assertRedirect();

        $created = User::where('email', 'tanpa.password@example.test')->firstOrFail();

        $this->assertTrue(Hash::check('password', $created->password));
    }

    /**
     * FIXED: API self-parent cycle rejected 422 via WorkUnit saving guard;
     * unit stays a tree root.
     */
    public function test_gap_api_self_parent_cycle_removes_unit_from_tree(): void
    {
        $admin = $this->user(User::ROLE_SUPERADMIN);
        $root = WorkUnit::factory()->create(['nama' => 'Akar']);

        $this->actingAs($admin)
            ->patchJson("/api/work-units/{$root->id}", ['parent_id' => $root->id])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['parent_id']);

        $this->assertDatabaseHas('work_units', ['id' => $root->id, 'parent_id' => null]);

        $tree = $this->actingAs($admin)->getJson('/api/work-units-tree')->assertOk()->json('data');

        $this->assertNotSame([], $tree, 'Unit remains reachable as tree root after rejected cycle.');
    }

    /** GAP: Api\WorkUnitController::store (:36) and the web request share no uniqueness rule — duplicate unit names are accepted. */
    public function test_gap_duplicate_unit_name_is_accepted(): void
    {
        $admin = $this->user(User::ROLE_SUPERADMIN);
        WorkUnit::factory()->create(['nama' => 'Biropatibility']);

        $this->actingAs($admin)
            ->postJson('/api/work-units', ['nama' => 'Biropatibility'])
            ->assertCreated();

        $this->assertSame(2, WorkUnit::where('nama', 'Biropatibility')->count());
    }

    /** GAP: same missing uniqueness on StoreWorkUnitRequest (app/Http/Requests/StoreWorkUnitRequest.php:21). */
    public function test_gap_duplicate_unit_name_is_accepted_on_the_web_surface(): void
    {
        WorkUnit::factory()->create(['nama' => 'Biro Kembar']);

        $this->actingAs($this->user(User::ROLE_SUPERADMIN))
            ->from('/admin/superadmin/units')
            ->post('/admin/superadmin/units', ['nama' => 'Biro Kembar'])
            ->assertRedirect('/admin/superadmin/units');

        $this->assertSame(2, WorkUnit::where('nama', 'Biro Kembar')->count());
    }

    /**
     * GAP: Web\FrameworkController::index (app/Http/Controllers/Web/FrameworkController.php:47)
     * has no `$this->authorize()` / `Gate::authorize('framework.view')`, unlike its
     * siblings Web\UserController::index (:23) and Web\WorkUnitController::index (:20).
     * The route therefore inherits only the `auth` middleware and renders the whole
     * framework directory to any signed-in role — including `pic`, which holds
     * neither `framework.view` nor `framework.create` (config/permissions.php:285).
     * The flat route `/frameworks` IS gated (PageDispatcher::MAP['frameworks']), so
     * the same page is 403 there and 200 here.
     */
    #[DataProvider('rolesWithoutFrameworkView')]
    public function test_gap_frameworks_index_route_is_reachable_without_the_framework_view_grant(string $role): void
    {
        $framework = $this->framework();

        $response = $this->actingAs($this->user($role))
            ->get('/admin/superadmin/frameworks')
            ->assertOk();

        $this->assertSame(
            $framework->nama,
            $response->viewData('page')['props']['frameworks'][0]['nama'],
            "{$role} rendered the framework directory without framework.view.",
        );

        // Same page, same role, gated alias → contrast that proves the gate is missing here.
        $this->actingAs($this->user($role))
            ->get('/frameworks')
            ->assertForbidden();
    }

    public static function rolesWithoutFrameworkView(): array
    {
        return [
            'koordinator_smki' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    /**
     * GAP: Web\UserController::destroy (:75) refuses self-deletion, but update has
     * no equivalent guard, so the last superadmin can demote themselves to `pic`
     * and immediately lose every master-data surface (US-M3 has no recovery path;
     * `forgotPassword` is a stub per §6). One click, silently.
     */
    public function test_gap_superadmin_can_demote_own_account(): void
    {
        $superadmin = $this->user(User::ROLE_SUPERADMIN);
        $picRole = Role::where('name', 'pic')->firstOrFail();

        $this->actingAs($superadmin)
            ->from('/admin/superadmin/users')
            ->patch("/admin/superadmin/users/{$superadmin->id}", [
                'name' => $superadmin->name,
                'email' => $superadmin->email,
                'role_id' => $picRole->id,
            ])
            ->assertRedirect('/admin/superadmin/users');

        $this->assertSame(User::ROLE_PIC, $superadmin->fresh()->role);

        // ...and the demoted account is now locked out of everything it just owned.
        $this->actingAs($superadmin->fresh())
            ->get('/admin/superadmin/users')
            ->assertForbidden();
        $this->actingAs($superadmin->fresh())
            ->get('/admin/superadmin/units')
            ->assertForbidden();
    }

    /** Documents the soft-delete cascade: controls survive with deleted_at set and drop out of the default scope. */
    public function test_soft_deleting_framework_soft_deletes_its_controls(): void
    {
        $framework = $this->framework();
        $control = $framework->controls()->create([
            'kode_klausul' => 'A.9.9', 'judul' => 'Orphan Clause', 'kategori' => 'teknologi',
        ]);

        $framework->delete();

        $this->assertSoftDeleted('controls', ['id' => $control->id]);
        $this->assertNull(Control::find($control->id), 'Soft-deleted control is hidden by the default scope.');
        $this->assertNotNull(Control::withTrashed()->find($control->id), 'Row is retained, not hard-deleted.');
    }
}
