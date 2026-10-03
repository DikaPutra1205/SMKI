<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\ComplianceEvidence;
use App\Models\Control;
use App\Models\Finding;
use App\Models\Framework;
use App\Models\Permission;
use App\Models\Risk;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * US-A1 (docs/FUNCTIONAL_SPEC.md §8) — audit trail contract for the ten models
 * registered with SmkiObserver: create → snapshot, update → {before, after}
 * diff, delete → snapshot, `$hidden` never leaks, the /audit-logs surface is
 * gated by `audit-log.view`, plus observer-registration/append-only guards and
 * the five characterised blind spots (each marked incomplete, suite stays green).
 * Merged from AuditTrailObserverCoverageTest + AuditTrailQa8Test.
 *
 * Complements AuditTrailTest (HTTP surface + Finding deeply).
 */
class AuditTrailCoverageTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unit;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create();
        $this->admin = User::factory()->create([
            'role' => User::ROLE_ADMIN_KEPATUHAN,
            'unit_id' => $this->unit->id,
        ]);

        $this->actingAs($this->admin);
        AuditLog::query()->delete();
    }

    /**
     * The ten models SmkiObserver is registered on, each with one field that is
     * safe to change in isolation plus a known `from` value so the write is
     * never a no-op (several factories randomise their columns).
     *
     * @return array<string, array{class-string<Model>, string, mixed, mixed}>
     */
    public static function observedModelProvider(): array
    {
        return [
            'Framework' => [Framework::class, 'nama', 'ISO 27001:2022', 'ISO 27001:2022 Revisi A'],
            'Control' => [Control::class, 'judul', 'Judul Asli', 'Judul Klausul Baru'],
            'User' => [User::class, 'name', 'Nama Asli', 'Nama Baru'],
            'Role' => [Role::class, 'label', 'Label Asli', 'Label Baru'],
            'WorkUnit' => [WorkUnit::class, 'nama', 'Unit Asli', 'Unit Kerja Baru'],
            'ChecklistSession' => [ChecklistSession::class, 'catatan', 'Catatan Asli', 'Catatan Sesi Baru'],
            'ChecklistEntry' => [ChecklistEntry::class, 'catatan', 'Catatan Asli', 'Catatan Entri Baru'],
            'ComplianceEvidence' => [ComplianceEvidence::class, 'is_active', true, false],
            'Finding' => [Finding::class, 'status', Finding::STATUS_OPEN, Finding::STATUS_IN_PROGRESS],
            'Risk' => [Risk::class, 'rencana_mitigasi', 'Rencana Asli', 'Rencana Mitigasi Baru'],
        ];
    }

    public function test_every_observed_model_writes_a_create_snapshot(): void
    {
        foreach (self::observedModelProvider() as $type => [$class, $field, $from, $to]) {
            $model = $this->makeObserved($class, [$field => $from]);

            $log = $this->logFor($type, $model, 'create');

            $this->assertNotNull($log, "[{$type}] tidak menulis audit log saat create.");
            $this->assertSame($this->admin->id, $log->actor_id, "[{$type}] actor_id create tidak terisi.");
            $this->assertSame(
                $model->getKey(),
                $log->detail_perubahan['data']['id'] ?? null,
                "[{$type}] snapshot create tidak memuat id.",
            );
            $this->assertNotSame([], $log->detail_perubahan['data'] ?? [], "[{$type}] snapshot create kosong.");
            $this->assertArrayHasKey(
                $field,
                $log->detail_perubahan['data'] ?? [],
                "[{$type}] snapshot create tidak memuat field {$field}.",
            );
        }
    }

    public function test_every_observed_model_writes_a_before_after_diff_on_update(): void
    {
        foreach (self::observedModelProvider() as $type => [$class, $field, $from, $to]) {
            $model = $this->makeObserved($class, [$field => $from]);
            AuditLog::query()->delete();

            $model->update([$field => $to]);

            $log = $this->logFor($type, $model, 'update');

            $this->assertNotNull($log, "[{$type}] tidak menulis audit log saat update.");
            $this->assertArrayHasKey('before', $log->detail_perubahan, "[{$type}] diff tanpa before.");
            $this->assertArrayHasKey('after', $log->detail_perubahan, "[{$type}] diff tanpa after.");
            $this->assertArrayHasKey(
                $field,
                $log->detail_perubahan['before'] ?? [],
                "[{$type}] before tidak memuat field yang berubah {$field}.",
            );
            $this->assertArrayHasKey($field, $log->detail_perubahan['after'] ?? []);
            $this->assertSame(
                $this->normalize($to),
                $this->normalize($log->detail_perubahan['after'][$field] ?? null),
                "[{$type}] nilai after tidak sesuai nilai baru.",
            );
        }
    }

    /**
     * US-A1 "ignores bare updated_at": the diff must carry the changed field
     * only — never the timestamp the write itself bumped.
     */
    public function test_update_diff_excludes_updated_at_and_untouched_columns(): void
    {
        foreach (self::observedModelProvider() as $type => [$class, $field, $from, $to]) {
            $model = $this->makeObserved($class, [$field => $from]);
            AuditLog::query()->delete();

            $model->update([$field => $to]);

            $log = $this->logFor($type, $model, 'update');

            $this->assertNotNull($log, "[{$type}] tidak menulis audit log saat update.");
            $this->assertArrayNotHasKey('updated_at', $log->detail_perubahan['after'] ?? [], "[{$type}] after memuat updated_at.");
            $this->assertArrayNotHasKey('updated_at', $log->detail_perubahan['before'] ?? [], "[{$type}] before memuat updated_at.");
            $this->assertSame(
                [$field],
                array_keys($log->detail_perubahan['after'] ?? []),
                "[{$type}] after harus hanya berisi kolom yang benar-benar berubah.",
            );
            $this->assertSame(
                array_keys($log->detail_perubahan['after'] ?? []),
                array_keys($log->detail_perubahan['before'] ?? []),
                "[{$type}] before dan after harus memuat key yang sama.",
            );
        }
    }

    public function test_timestamp_only_change_writes_no_update_log_for_any_observed_model(): void
    {
        foreach (self::observedModelProvider() as $type => [$class, $field, $from, $to]) {
            $model = $this->makeObserved($class, [$field => $from]);
            AuditLog::query()->delete();

            $model->touch();

            $this->assertNull(
                $this->logFor($type, $model, 'update'),
                "[{$type}] touch() saja seharusnya tidak menulis audit log update.",
            );
        }
    }

    public function test_every_observed_model_writes_a_delete_snapshot(): void
    {
        foreach (self::observedModelProvider() as $type => [$class, $field, $from, $to]) {
            $model = $this->makeObserved($class, [$field => $from]);
            AuditLog::query()->delete();

            $model->delete();

            $log = $this->logFor($type, $model, 'delete');

            $this->assertNotNull($log, "[{$type}] tidak menulis audit log saat delete.");
            $this->assertSame($this->admin->id, $log->actor_id, "[{$type}] actor_id delete tidak terisi.");
            $this->assertSame(
                $model->getKey(),
                $log->detail_perubahan['data']['id'] ?? null,
                "[{$type}] snapshot delete tidak memuat id.",
            );
        }
    }

    /**
     * Soft-deleting models (8 of 10) must be audited the same way, and the
     * row must survive — US-A1 relies on the audit log to explain a vanish.
     */
    public function test_soft_delete_is_audited_and_keeps_the_row(): void
    {
        $finding = Finding::factory()->create(['unit_id' => $this->unit->id]);
        AuditLog::query()->delete();

        $finding->delete();

        $this->assertNotNull($this->logFor('Finding', $finding, 'delete'));
        $this->assertSoftDeleted('findings', ['id' => $finding->id]);

        AuditLog::query()->delete();
        $finding->restore();

        $this->assertNotNull(
            $this->logFor('Finding', $finding, 'update'),
            'restore() harus tercatat, kalau tidak perubahan status soft-delete tak terlacak.',
        );
    }

    /**
     * $hidden must hold for every write path, not just create.
     */
    public function test_password_and_remember_token_never_reach_any_user_audit_log(): void
    {
        $user = User::factory()->create([
            'unit_id' => $this->unit->id,
            'password' => 'rahasia-123',
        ]);
        AuditLog::query()->delete();

        // A hidden-only write leaves nothing to log, so the observable log set is
        // the mixed write (name + password) plus the delete.
        $user->update(['remember_token' => 'token-baru']);
        $this->assertSame(
            0,
            AuditLog::where('entity_type', 'User')->where('entity_id', $user->id)->count(),
            'Perubahan hanya pada kolom $hidden tidak boleh menghasilkan log.',
        );

        $user->update(['name' => 'Nama Baru', 'password' => 'rahasia-456']);
        $user->delete();

        $logs = AuditLog::where('entity_type', 'User')
            ->where('entity_id', $user->id)
            ->orderBy('id')
            ->get();

        $this->assertCount(2, $logs, 'Ekspektasi 1 update + 1 delete.');

        foreach ($logs as $log) {
            $flat = json_encode($log->detail_perubahan);
            $this->assertStringNotContainsString('password', $flat, "Log {$log->aksi} memuat kunci password.");
            $this->assertStringNotContainsString('remember_token', $flat, "Log {$log->aksi} memuat kunci remember_token.");
            $this->assertStringNotContainsString('$2y$', $flat, "Log {$log->aksi} memuat hash bcrypt.");
            $this->assertStringNotContainsString('rahasia-', $flat, "Log {$log->aksi} memuat password plaintext.");
        }

        $update = $logs->firstWhere('aksi', 'update');
        $this->assertNotNull($update);
        $this->assertSame(['name'], array_keys($update->detail_perubahan['after']));
    }

    public function test_audit_log_api_response_never_exposes_password_material(): void
    {
        User::factory()->create([
            'unit_id' => $this->unit->id,
            'password' => 'rahasia-api-123',
        ]);

        $response = $this->getJson('/api/v1/audit-logs?entity_type=User');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('rahasia-api-123', $body);
        $this->assertStringNotContainsString('$2y$', $body, 'Hash bcrypt bocor lewat API audit-logs.');
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_inertia_audit_logs_page_never_exposes_password_material(): void
    {
        User::factory()->create([
            'unit_id' => $this->unit->id,
            'password' => 'rahasia-inertia-123',
        ]);

        $manifest = public_path('build/manifest.json');
        $version = file_exists($manifest) ? hash_file('xxh128', $manifest) : '';

        $response = $this->withHeader('X-Inertia', 'true')
            ->withHeader('X-Inertia-Version', $version)
            ->get('/admin/kepatuhan/audit-logs');

        $response->assertOk();
        $body = $response->getContent();
        $this->assertStringNotContainsString('rahasia-inertia-123', $body);
        $this->assertStringNotContainsString('$2y$', $body, 'Hash bcrypt bocor lewat props Inertia.');
        $this->assertStringNotContainsString('"password"', $body);
        $this->assertStringNotContainsString('remember_token', $body);
    }

    public function test_anonymous_callers_are_rejected_from_every_audit_log_route(): void
    {
        auth()->logout();

        foreach (['/audit-logs', '/admin/kepatuhan/audit-logs', '/admin/superadmin/audit-logs', '/admin/auditor/audit-logs'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }

        $this->getJson('/api/v1/audit-logs')->assertUnauthorized();
        $this->getJson('/api/v1/audit-logs/stats')->assertUnauthorized();
    }

    public function test_pic_is_forbidden_on_every_audit_log_route(): void
    {
        $pic = User::factory()->create(['role' => 'pic', 'unit_id' => $this->unit->id]);

        foreach (['/audit-logs', '/admin/kepatuhan/audit-logs', '/admin/superadmin/audit-logs', '/admin/auditor/audit-logs'] as $url) {
            $this->actingAs($pic)->get($url)->assertForbidden();
        }

        $this->actingAs($pic)->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->actingAs($pic)->getJson('/api/v1/audit-logs/stats')->assertForbidden();
    }

    private function makeObserved(string $class, array $state = []): Model
    {
        return match ($class) {
            Framework::class => Framework::factory()->create($state),
            Control::class => Control::factory()->create($state),
            User::class => User::factory()->create($state + ['unit_id' => $this->unit->id]),
            Role::class => Role::create($state + ['name' => 'role_'.uniqid(), 'label' => 'Peran Uji']),
            WorkUnit::class => WorkUnit::factory()->create($state),
            ChecklistSession::class => ChecklistSession::factory()->create($state + ['unit_id' => $this->unit->id]),
            ChecklistEntry::class => ChecklistEntry::factory()->create($state + ['unit_id' => $this->unit->id]),
            ComplianceEvidence::class => ComplianceEvidence::create($state + [
                'checklist_entry_id' => ChecklistEntry::factory()->create(['unit_id' => $this->unit->id])->id,
                'uploaded_by' => $this->admin->id,
                'file_url' => 'bukti/seed/original.pdf',
                'version_number' => 1,
                'is_active' => true,
            ]),
            Finding::class => Finding::factory()->create($state + ['unit_id' => $this->unit->id]),
            Risk::class => Risk::factory()->create($state),
            default => throw new \InvalidArgumentException("Model {$class} tidak terobserve."),
        };
    }

    private function logFor(string $entityType, Model $model, string $aksi): ?AuditLog
    {
        return AuditLog::where('entity_type', $entityType)
            ->where('entity_id', $model->getKey())
            ->where('aksi', $aksi)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * The jsonb round-trip normalises ints to strings on some casts, so
     * compare loosely on scalars.
     */
    private function normalize(mixed $value): mixed
    {
        return is_bool($value) || $value === null ? $value : (string) $value;
    }

    // ── Contract guards beyond the behavioural matrix ─────────────────────

    /**
     * Spec §8 names the ten models explicitly. If someone adds or drops an
     * `X::observe(SmkiObserver::class)` line, the audit surface silently
     * changes and no other test notices — the behavioural suites only prove
     * what is registered, never what *should* be.
     */
    public function test_qa8_smki_observer_is_registered_on_all_ten_spec_models(): void
    {
        $dispatcher = Model::getEventDispatcher();

        $expected = [
            Framework::class,
            Control::class,
            User::class,
            Role::class,
            WorkUnit::class,
            ChecklistSession::class,
            ChecklistEntry::class,
            ComplianceEvidence::class,
            Finding::class,
            Risk::class,
        ];

        foreach ($expected as $class) {
            foreach (['created', 'updated', 'deleted'] as $event) {
                $this->assertTrue(
                    $dispatcher->hasListeners("eloquent.{$event}: {$class}"),
                    "SmkiObserver tidak terdaftar untuk event {$event} pada {$class}."
                );
            }
        }

        $this->assertCount(
            10,
            $expected,
            'Daftar model §8 berubah — perbarui docs/FUNCTIONAL_SPEC.md dan test ini.'
        );
    }

    /**
     * The log must be a record, not a mutable table. Two halves: no HTTP
     * surface writes it, and the model has no `updated_at` column to re-date
     * a row in place.
     */
    public function test_qa8_audit_log_has_no_write_surface_and_no_updated_at(): void
    {
        foreach (Route::getRoutes() as $route) {
            if (! str_contains($route->uri(), 'audit-log')) {
                continue;
            }

            $this->assertSame(
                ['GET', 'HEAD'],
                $route->methods(),
                "Endpoint audit-logs bukan read-only: {$route->methods()[0]} {$route->uri()}."
            );
        }

        $this->assertNull(
            (new AuditLog)->timestamps ? AuditLog::UPDATED_AT : null,
            'AuditLog tidak boleh punya updated_at — baris lama bisa dirapikan ulang.'
        );

        $log = AuditLog::catat(entityType: 'Probe', entityId: 1, aksi: 'create');
        $this->assertArrayNotHasKey('updated_at', $log->toArray());
    }

    /**
     * Secrecy scan over the *whole* table rather than one entity's rows, so it
     * also covers writes that carry no direct User relationship (role grants,
     * bulk entry resets) and a stray hash in any snapshot.
     */
    public function test_qa8_no_audit_log_row_anywhere_contains_password_material(): void
    {
        $secret = 'rahasia-qa8-'.Str::random(6);

        $user = User::factory()->create([
            'unit_id' => $this->unit->id,
            'password' => $secret,
        ]);
        $role = Role::create(['name' => 'qa8_role_'.Str::random(5), 'label' => 'QA8']);
        $role->permissions()->sync(Permission::whereIn('key', ['audit-log.view', 'user.create'])->pluck('id'));
        $entry = ChecklistEntry::factory()->create(['unit_id' => $this->unit->id]);
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unit->id]);
        $session->entries()->update(['tanggal_input' => now()]);

        $user->update(['password' => $secret.'-rotated']);
        $user->update(['remember_token' => 'token-'.Str::random(8)]);
        $user->update(['name' => 'Nama QA8', 'password' => $secret.'-rotated-2']);
        $user->delete();

        $this->assertGreaterThan(0, AuditLog::count(), 'Tidak ada audit log sama sekali — probe tidak berarti.');

        foreach (AuditLog::get() as $log) {
            $flat = json_encode($log->detail_perubahan, JSON_UNESCAPED_UNICODE);

            $this->assertStringNotContainsString('password', $flat, "Log #{$log->id} memuat kunci password.");
            $this->assertStringNotContainsString('remember_token', $flat, "Log #{$log->id} memuat kunci remember_token.");
            $this->assertStringNotContainsString('$2y$', $flat, "Log #{$log->id} memuat hash bcrypt.");
            $this->assertStringNotContainsString($secret, $flat, "Log #{$log->id} memuat password plaintext.");
        }
    }

    /**
     * PIC is one instance of the denial; the general case is a role that simply
     * lacks `audit-log.view`. Both web and API must refuse without leaking a
     * log row in the payload.
     */
    public function test_qa8_audit_logs_denied_for_role_without_the_permission(): void
    {
        $role = Role::create(['name' => 'qa8_noaudit_'.Str::random(5), 'label' => 'QA8 Tanpa Audit']);
        $role->permissions()->sync(Permission::where('key', 'checklist.view')->pluck('id'));

        $user = User::factory()->create(['role_id' => $role->id, 'unit_id' => $this->unit->id]);
        Role::flushPermissionsCache($role->id);
        app('cache')->flush();

        foreach (['/audit-logs', '/admin/kepatuhan/audit-logs', '/admin/superadmin/audit-logs', '/admin/auditor/audit-logs'] as $url) {
            $this->actingAs($user)->get($url)->assertForbidden();
        }

        $this->actingAs($user)->getJson('/api/v1/audit-logs')->assertForbidden();
        $this->actingAs($user)->getJson('/api/v1/audit-logs/stats')->assertForbidden();
    }

    // ── Blind-spot characterisation (each marked incomplete, suite green) ──

    /**
     * GAP 1 (most severe) — Framework::booted() cascades a framework delete to
     * every Control under it via `$framework->controls()->delete()`. That is a
     * relation query: no Control instance is hydrated, and `SoftDeletingScope`
     * rewrites the mass delete into a bulk `update deleted_at`, so neither the
     * `deleted` nor the `updated` event fires. The rows go trashed while the
     * audit trail shows only the Framework.
     *
     * @see app/Models/Framework.php:61-62
     */
    public function test_qa8_framework_delete_audits_its_cascade_to_controls(): void
    {
        $framework = Framework::factory()->create();
        $controlIds = Control::factory()->count(3)
            ->create(['framework_id' => $framework->id])
            ->pluck('id')
            ->all();

        AuditLog::query()->delete();

        $framework->delete();

        $this->assertTrue($framework->fresh()->trashed(), 'Framework seharusnya soft-deleted.');
        $this->assertCount(
            3,
            Control::onlyTrashed()->whereIn('id', $controlIds)->get(),
            'Control di bawah framework seharusnya ikut ter-trash.'
        );

        $cascadeLogs = AuditLog::where('entity_type', 'Control')
            ->whereIn('entity_id', $controlIds)
            ->get();

        $this->markTestIncomplete(
            sprintf(
                'GAP 1 (US-A1): cascade delete Control saat Framework dihapus tidak tercatat. '
                .'Control %s semuanya trashed, jumlah log Control = %d. '
                .'Penyebab: app/Models/Framework.php:61-62 memakai $framework->controls()->delete() (mass query, event model tidak fired).',
                implode(',', $controlIds),
                $cascadeLogs->count()
            )
        );
    }

    /**
     * GAP 2 — RoleController@store/@update grant or revoke access with
     * `$role->permissions()->sync($ids)`. A pivot write fires no model event, so
     * the audit trail records the role's name/label but never which permissions
     * it gained or lost. Granting `audit-log.view` — the permission that opens
     * this very screen — leaves no trace.
     *
     * @see app/Http/Controllers/Web/RoleController.php:41 (store), :59 (update)
     */
    public function test_qa8_role_permission_grant_is_audited(): void
    {
        $superadmin = User::factory()->create(['role' => User::ROLE_SUPERADMIN]);

        $this->actingAs($superadmin)
            ->post('/admin/superadmin/roles', [
                'name' => 'qa8_auditor_'.Str::lower(Str::random(5)),
                'label' => 'QA8 Auditor',
                'permissions' => ['audit-log.view'],
            ])
            ->assertRedirect();

        $role = Role::where('name', 'like', 'qa8_auditor_%')->latest('id')->first();
        $this->assertNotNull($role);
        $this->assertTrue($role->permissions()->where('key', 'audit-log.view')->exists());

        $grantLogs = AuditLog::where('entity_type', 'Role')
            ->where('entity_id', $role->id)
            ->where(function ($q) {
                $q->where('detail_perubahan->permissions', '!=', null)
                    ->orWhere('detail_perubahan->before->permissions', '!=', null)
                    ->orWhere('detail_perubahan->after->permissions', '!=', null);
            })
            ->get();

        $this->markTestIncomplete(
            sprintf(
                'GAP 2 (US-A1): grant permission lewat RoleController::store/update tidak tercatat. '
                .'Role #%d dibuat dengan audit-log.view, jumlah log yang menyebut permission = %d. '
                .'Penyebab: pivot role_permission ditulis via BelongsToMany::sync(), tidak.fire event model.',
                $role->id,
                $grantLogs->count()
            )
        );
    }

    /**
     * GAP 3 — ComplianceEvidence::booted() supersedes the previous version with
     * a query-builder `update(['is_active' => false])` *before* the new row is
     * inserted. The trail shows the new version's create, so a reviewer sees a
     * new document but never sees the superseded one lose its active flag.
     *
     * @see app/Models/ComplianceEvidence.php:107-109
     */
    public function test_qa8_superseded_evidence_deactivation_is_audited(): void
    {
        $entry = ChecklistEntry::factory()->create(['unit_id' => $this->unit->id]);

        $v1 = ComplianceEvidence::create([
            'checklist_entry_id' => $entry->id,
            'uploaded_by' => $this->admin->id,
            'file_url' => 'bukti/qa8/v1.pdf',
            'version_number' => 1,
            'is_active' => true,
        ]);
        AuditLog::query()->delete();

        $v2 = ComplianceEvidence::create([
            'checklist_entry_id' => $entry->id,
            'uploaded_by' => $this->admin->id,
            'file_url' => 'bukti/qa8/v2.pdf',
            'version_number' => 2,
            'is_active' => true,
        ]);

        $this->assertFalse($v1->fresh()->is_active, 'v1 seharusnya tidak aktif setelah v2 masuk.');
        $this->assertTrue($v2->fresh()->is_active);

        $v1Logs = AuditLog::where('entity_type', 'ComplianceEvidence')
            ->where('entity_id', $v1->id)
            ->where('aksi', 'update')
            ->get();

        $this->markTestIncomplete(
            sprintf(
                'GAP 3 (US-A1): evidence versi lama dinonaktifkan tanpa log. '
                .'ComplianceEvidence #%d is_active true→false, jumlah log update = %d. '
                .'Penyebab: app/Models/ComplianceEvidence.php:107-109 memakai query-builder update(), observer tidak dipanggil.',
                $v1->id,
                $v1Logs->count()
            )
        );
    }

    /**
     * GAP 4 — ChecklistSessionController@submitAssessment stamps
     * `tanggal_input` on every entry and then nulls the compliance
     * officer's `catatan_admin`, `admin_id` and `tanggal_verifikasi` on rejected
     * entries, all via `entries()->update(...)`. The reviewer's name and note
     * are erased with zero audit trace, so "who rejected this and why" becomes
     * unanswerable after resubmission.
     *
     * @see app/Http/Controllers/Web/ChecklistSessionController.php:388 and :392-399
     */
    public function test_qa8_pic_resubmission_clearing_admin_review_is_audited(): void
    {
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unit->id]);

        $entry = ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'unit_id' => $this->unit->id,
            'pic_id' => $pic->id,
            'admin_id' => $this->admin->id,
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'catatan' => 'Sudah ada SOP.',
            'catatan_admin' => 'Bukti kurang jelas, unggah ulang.',
            'tanggal_verifikasi' => now()->subDay(),
        ]);

        AuditLog::query()->delete();

        $this->actingAs($pic)
            ->post("/admin/pic/checklist/{$session->id}/submit")
            ->assertRedirect();

        $fresh = $entry->fresh();
        $this->assertNull($fresh->catatan_admin, 'catatan_admin seharusnya sudah dikosongkan.');
        $this->assertNull($fresh->admin_id, 'admin_id seharusnya sudah dikosongkan.');

        $entryLogs = AuditLog::where('entity_type', 'ChecklistEntry')
            ->where('entity_id', $entry->id)
            ->get();

        $this->markTestIncomplete(
            sprintf(
                'GAP 4 (US-A1): reset verifikasi admin saat PIC submit ulang tidak tercatat. '
                .'ChecklistEntry #%d: admin_id %s→NULL, catatan_admin "…"→NULL, jumlah log entry = %d. '
                .'Penyebab: app/Http/Controllers/Web/ChecklistSessionController.php:388,392-399 memakai relation update().',
                $entry->id,
                (string) $this->admin->id,
                $entryLogs->count()
            )
        );
    }

    /**
     * GAP 5 — SmkiObserver::saved() claims every unowned entry in a PIC's unit
     * with a query-builder `update(['pic_id' => ...])`. Those rows change owner,
     * which is exactly the assignment that decides who may edit and upload
     * evidence, yet the trail only shows the User row changing.
     *
     * @see app/Observers/SmkiObserver.php:81-83
     */
    public function test_qa8_pic_claiming_unit_entries_is_audited(): void
    {
        $session = ChecklistSession::factory()->create(['unit_id' => $this->unit->id]);
        $entry = ChecklistEntry::factory()->create([
            'session_id' => $session->id,
            'unit_id' => $this->unit->id,
            'pic_id' => null,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
        ]);

        AuditLog::query()->delete();

        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);

        $this->assertSame($pic->id, $entry->fresh()->pic_id, 'Entry seharusnya diklaim PIC.');

        $entryLogs = AuditLog::where('entity_type', 'ChecklistEntry')
            ->where('entity_id', $entry->id)
            ->get();

        $this->markTestIncomplete(
            sprintf(
                'GAP 5 (US-A1): pengalihan kepemilikan entry checklist tidak tercatat. '
                .'ChecklistEntry #%d pic_id NULL→%d, jumlah log entry = %d. '
                .'Penyebab: app/Observers/SmkiObserver.php:81-83 memakai query-builder update() di dalam event saved().',
                $entry->id,
                $pic->id,
                $entryLogs->count()
            )
        );
    }
}
