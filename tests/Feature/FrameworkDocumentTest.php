<?php

namespace Tests\Feature;

use App\Models\Framework;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * §10 framework document + runtime CRUD in one place. Both Form Requests share
 * one rule set (`file_dokumen => nullable|file|mimes:pdf,docx,csv,xlsx|max:20480`),
 * so type/size limits must hold identically on Web and API, a rejected upload
 * must leave neither row nor object behind, admin_kepatuhan can CRUD frameworks
 * at runtime without SSH/seeding, and denied callers change nothing.
 * Merged from FrameworkDocumentLimitsTest + Qa10ExtrasFrameworkRuntimeCrudTest.
 */
class FrameworkDocumentTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_KB = 20480; // 20 MB, as declared in the Form Requests

    private function superadmin(): User
    {
        return User::factory()->create(['role' => User::ROLE_SUPERADMIN]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
    }

    // ── Accepted types ─────────────────────────────────────────────────────

    public static function acceptedExtensionProvider(): array
    {
        return [
            'pdf' => ['iso.pdf', 'application/pdf'],
            'docx' => ['iso.docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
            'csv' => ['iso.csv', 'text/csv'],
            'xlsx' => ['iso.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        ];
    }

    #[DataProvider('acceptedExtensionProvider')]
    public function test_web_store_accepts_each_declared_extension(string $filename, string $mime): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'ISO '.$filename,
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->createWithContent($filename, 'isi dokumen'),
            ])
            ->assertSessionHasNoErrors();

        $framework = Framework::query()->where('nama', 'ISO '.$filename)->firstOrFail();
        $this->assertNotNull($framework->getRawOriginal('url_file'));
        Storage::disk('frameworks')->assertExists($framework->getRawOriginal('url_file'));
    }

    #[DataProvider('acceptedExtensionProvider')]
    public function test_api_store_accepts_each_declared_extension(string $filename, string $mime): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->postJson('/api/frameworks', [
                'nama' => 'ISO '.$filename,
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->createWithContent($filename, 'isi dokumen'),
            ])
            ->assertCreated();

        $framework = Framework::query()->where('nama', 'ISO '.$filename)->firstOrFail();
        Storage::disk('frameworks')->assertExists($framework->getRawOriginal('url_file'));
    }

    // ── Rejected types ─────────────────────────────────────────────────────

    public static function rejectedExtensionProvider(): array
    {
        return [
            'windows executable' => ['payload.exe', 'application/x-msdownload'],
            'php script' => ['shell.php', 'application/x-php'],
            'plain text' => ['catatan.txt', 'text/plain'],
            'svg (active content)' => ['logo.svg', 'image/svg+xml'],
            'zip archive' => ['bundle.zip', 'application/zip'],
        ];
    }

    #[DataProvider('rejectedExtensionProvider')]
    public function test_web_store_rejects_disallowed_extension(string $filename, string $mime): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->from('/admin/superadmin/frameworks')
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'ISO_test',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->create($filename, 8, $mime),
            ])
            ->assertSessionHasErrors(['file_dokumen']);

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO_test']);
        $this->assertEmpty(Storage::disk('frameworks')->allFiles(), 'file yang ditolak tidak boleh tersimpan');
    }

    #[DataProvider('rejectedExtensionProvider')]
    public function test_api_store_rejects_disallowed_extension(string $filename, string $mime): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->postJson('/api/frameworks', [
                'nama' => 'ISO_test',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->create($filename, 8, $mime),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file_dokumen']);

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO_test']);
        $this->assertEmpty(Storage::disk('frameworks')->allFiles());
    }

    #[DataProvider('rejectedExtensionProvider')]
    public function test_web_update_rejects_disallowed_extension_without_touching_the_existing_document(string $filename, string $mime): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO 27001', 'versi' => '2022']);
        Storage::disk('frameworks')->put('frameworks/iso-27001.pdf', '%PDF-1.4 asli');
        $framework->update(['url_file' => 'frameworks/iso-27001.pdf']);

        $this->actingAs($this->superadmin())
            ->from("/admin/superadmin/frameworks/{$framework->id}")
            ->patch("/admin/superadmin/frameworks/{$framework->id}", [
                'versi' => '2025',
                'file_dokumen' => UploadedFile::fake()->create($filename, 8, $mime),
            ])
            ->assertSessionHasErrors(['file_dokumen']);

        $framework->refresh();
        $this->assertSame('2022', $framework->versi, 'field lain tidak boleh ikut ter-update saat upload ditolak');
        Storage::disk('frameworks')->assertExists('frameworks/iso-27001.pdf');
    }

    // ── Size limit ─────────────────────────────────────────────────────────

    public function test_web_store_rejects_file_over_the_20mb_limit(): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->from('/admin/superadmin/frameworks')
            ->post('/admin/superadmin/frameworks', [
                'nama' => 'ISO 27001',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->create('iso.pdf', self::MAX_KB + 1, 'application/pdf'),
            ])
            ->assertSessionHasErrors(['file_dokumen']);

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO 27001']);
        $this->assertEmpty(Storage::disk('frameworks')->allFiles());
    }

    public function test_api_store_rejects_file_over_the_20mb_limit(): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->postJson('/api/frameworks', [
                'nama' => 'ISO 27001',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->create('iso.pdf', self::MAX_KB + 1, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file_dokumen']);

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO 27001']);
        $this->assertEmpty(Storage::disk('frameworks')->allFiles());
    }

    public function test_file_exactly_at_the_20mb_limit_is_accepted(): void
    {
        Storage::fake('frameworks');

        $this->actingAs($this->superadmin())
            ->postJson('/api/frameworks', [
                'nama' => 'ISO 27001',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->create('iso.pdf', self::MAX_KB, 'application/pdf'),
            ])
            ->assertCreated();

        $framework = Framework::query()->where('nama', 'ISO 27001')->firstOrFail();
        Storage::disk('frameworks')->assertExists($framework->getRawOriginal('url_file'));
    }

    // ── Field is optional ──────────────────────────────────────────────────

    public function test_file_dokumen_is_optional_and_leaves_url_file_null(): void
    {
        $this->actingAs($this->superadmin())
            ->postJson('/api/frameworks', ['nama' => 'ISO 27001', 'versi' => '2022'])
            ->assertCreated()
            ->assertJsonPath('data.url_file', null);

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO 27001', 'url_file' => null]);
    }

    // ── Out of scope callers cannot abuse the upload endpoint ──────────────

    public function test_pic_role_cannot_upload_a_framework_document(): void
    {
        Storage::fake('frameworks');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);

        $this->actingAs($pic)
            ->postJson('/api/frameworks', [
                'nama' => 'ISO 27001',
                'versi' => '2022',
                'file_dokumen' => UploadedFile::fake()->createWithContent('iso.pdf', '%PDF-1.4'),
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO 27001']);
        $this->assertEmpty(Storage::disk('frameworks')->allFiles());
    }

    // ── Runtime CRUD: admin_kepatuhan needs no SSH/seeding ─────────────────

    public function test_qa10_admin_kepatuhan_can_create_framework_at_runtime_on_both_surfaces(): void
    {
        $admin = $this->admin();
        $this->assertTrue($admin->hasPermissionTo('framework.create'));

        $this->actingAs($admin)
            ->post('/admin/superadmin/frameworks', ['nama' => 'ISO/IEC 42001', 'versi' => '2023'])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO/IEC 42001', 'versi' => '2023']);

        $this->actingAs($admin)
            ->postJson('/api/frameworks', ['nama' => 'ISO/IEC 27002', 'versi' => '2022'])
            ->assertCreated();

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO/IEC 27002', 'versi' => '2022']);
    }

    public function test_qa10_admin_kepatuhan_can_update_and_delete_framework_at_runtime(): void
    {
        $admin = $this->admin();
        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);

        $this->actingAs($admin)
            ->patch("/admin/superadmin/frameworks/{$framework->id}", ['versi' => '2025'])
            ->assertSessionHasNoErrors();
        $this->assertSame('2025', $framework->fresh()->versi);

        $this->actingAs($admin)
            ->patchJson("/api/frameworks/{$framework->id}", ['versi' => '2026'])
            ->assertOk();
        $this->assertSame('2026', $framework->fresh()->versi);

        $this->actingAs($admin)->delete("/admin/superadmin/frameworks/{$framework->id}");
        $this->assertSoftDeleted('frameworks', ['id' => $framework->id]);
    }

    public function test_qa10_pic_is_denied_framework_crud_and_nothing_is_written(): void
    {
        Storage::fake('frameworks');
        $pic = User::factory()->create(['role' => User::ROLE_PIC]);
        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);

        $this->actingAs($pic)
            ->post('/admin/superadmin/frameworks', ['nama' => 'ISO/IEC 42001', 'versi' => '2023'])
            ->assertForbidden();
        $this->actingAs($pic)
            ->patchJson("/api/frameworks/{$framework->id}", ['versi' => '2030'])
            ->assertForbidden();
        $this->actingAs($pic)
            ->deleteJson("/api/frameworks/{$framework->id}")
            ->assertForbidden();

        $this->assertDatabaseMissing('frameworks', ['nama' => 'ISO/IEC 42001']);
        $this->assertSame('2022', $framework->fresh()->versi);
        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'deleted_at' => null]);
    }

    /**
     * Privilege boundary: both destroy() implementations authorize BEFORE
     * deleting the stored document (Web\FrameworkController and the API twin
     * call `Gate::authorize('framework.delete')` first). A denied caller gets
     * the 403 and the object survives — no orphaned url_file.
     */
    public function test_qa10_gap_unauthorized_destroy_still_deletes_the_stored_document_before_the_403(): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        Storage::disk('frameworks')->put('frameworks/iso-27001.pdf', '%PDF-1.4 asli');
        $framework->update(['url_file' => 'frameworks/iso-27001.pdf']);

        $koordinator = User::factory()->create(['role' => User::ROLE_KOORDINATOR_SMKI]);
        $this->assertFalse($koordinator->hasPermissionTo('framework.delete'));

        $this->actingAs($koordinator)
            ->delete("/admin/superadmin/frameworks/{$framework->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'deleted_at' => null]);
        $this->assertTrue(
            Storage::disk('frameworks')->exists('frameworks/iso-27001.pdf'),
            'Dokumen harus utuh saat request ditolak 403 — authorize berjalan sebelum penghapusan object'
        );
    }

    /**
     * Same authorize-first order on the API surface: denied destroy leaves
     * both the row and the stored object intact.
     */
    public function test_qa10_gap_unauthorized_api_destroy_still_deletes_the_stored_document(): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);
        Storage::disk('frameworks')->put('frameworks/iso-27701.pdf', '%PDF-1.4 asli');
        $framework->update(['url_file' => 'frameworks/iso-27701.pdf']);

        $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);
        $this->assertFalse($auditor->hasPermissionTo('framework.delete'));

        $this->actingAs($auditor)
            ->deleteJson("/api/frameworks/{$framework->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('frameworks', ['id' => $framework->id, 'deleted_at' => null]);
        $this->assertTrue(Storage::disk('frameworks')->exists('frameworks/iso-27701.pdf'));
    }

    /**
     * §10 "Framework file upload (url_file) — QA file-type/size limits".
     * FrameworkDocumentLimitsTest exercises store() on both surfaces plus the
     * web update(); the API update() branch was untested.
     */
    public function test_qa10_api_update_rejects_disallowed_extension_and_keeps_existing_document(): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        Storage::disk('frameworks')->put('frameworks/asli.pdf', '%PDF-1.4 asli');
        $framework->update(['url_file' => 'frameworks/asli.pdf']);

        $this->actingAs($this->admin())
            ->patchJson("/api/frameworks/{$framework->id}", [
                'versi' => '2025',
                'file_dokumen' => UploadedFile::fake()->create('payload.php', 8, 'application/x-php'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file_dokumen']);

        $this->assertSame('2022', $framework->fresh()->versi, 'field lain tidak boleh ikut berubah');
        Storage::disk('frameworks')->assertExists('frameworks/asli.pdf');
    }

    public function test_qa10_api_update_rejects_document_over_20mb(): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);

        $this->actingAs($this->admin())
            ->patchJson("/api/frameworks/{$framework->id}", [
                'file_dokumen' => UploadedFile::fake()->create('iso.pdf', 20481, 'application/pdf'),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['file_dokumen']);

        $this->assertNull($framework->fresh()->getRawOriginal('url_file'));
        $this->assertEmpty(Storage::disk('frameworks')->allFiles());
    }

    public function test_qa10_api_update_replaces_document_and_removes_the_previous_object(): void
    {
        Storage::fake('frameworks');

        $framework = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        Storage::disk('frameworks')->put('frameworks/lama.pdf', '%PDF-1.4 lama');
        $framework->update(['url_file' => 'frameworks/lama.pdf']);

        $this->actingAs($this->admin())
            ->patchJson("/api/frameworks/{$framework->id}", [
                'file_dokumen' => UploadedFile::fake()->createWithContent('baru.pdf', '%PDF-1.4 baru'),
            ])
            ->assertOk();

        $newPath = $framework->fresh()->getRawOriginal('url_file');
        $this->assertNotSame('frameworks/lama.pdf', $newPath);
        Storage::disk('frameworks')->assertExists($newPath);
        Storage::disk('frameworks')->assertMissing('frameworks/lama.pdf');
    }
}
