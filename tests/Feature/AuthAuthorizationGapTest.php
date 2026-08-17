<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class AuthAuthorizationGapTest extends TestCase
{
    use RefreshDatabase;

    private function seedEntry(): array
    {
        $unit = WorkUnit::create(['nama' => 'Unit QA']);
        $fw = Framework::create(['nama' => 'ISO 27001:2022', 'versi' => '2022']);
        $control = $fw->controls()->create([
            'kode_klausul' => 'A.5.1', 'judul' => 'Policies', 'kategori' => 'annex_a',
        ]);
        $pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $unit->id]);
        $entry = ChecklistEntry::create([
            'control_id' => $control->id, 'unit_id' => $unit->id, 'pic_id' => $pic->id,
            'status' => ChecklistEntry::STATUS_NON_COMPLIANT,
        ]);

        return ['pic' => $pic, 'entry' => $entry];
    }

    // Session gate: anonymous API is blocked.
    public function test_anonymous_api_blocked(): void
    {
        $this->getJson('/api/checklist-entries')->assertStatus(401);
    }

    // D6 — pic reaches the admin-only verify endpoint (no policy/gate/middleware).
    public function test_pic_reaches_verify_endpoint(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)
            ->patchJson("/api/checklist-entries/{$entry->id}/verify",
                ['admin_id' => $pic->id, 'status' => ChecklistEntry::STATUS_COMPLIANT])
            ->assertOk();

        $this->assertNotNull(ChecklistEntry::find($entry->id)->tanggal_verifikasi);
    }

    // D6 — pic reaches evidence store (writes any role's data).
    public function test_pic_reaches_evidence_store(): void
    {
        ['pic' => $pic, 'entry' => $entry] = $this->seedEntry();

        $this->actingAs($pic)
            ->postJson("/api/checklist-entries/{$entry->id}/evidences",
                ['bukti_file' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'), 'uploaded_by' => $pic->id])
            ->assertCreated();

        $this->assertDatabaseHas('compliance_evidences', ['checklist_entry_id' => $entry->id]);
    }

    // D5 — forgot-password stub returns 200 but sends no mail/token.
    public function test_forgot_password_stub_returns_200_no_email(): void
    {
        User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12')]);

        Mail::fake();

        $this->postJson('/forgot-password', ['email' => 'pic@smki.test'])->assertOk();
        Mail::assertNothingSent();
        $this->assertDatabaseMissing('password_reset_tokens', ['email' => 'pic@smki.test']);
    }

    public function test_login_accepts_valid_credentials(): void
    {
        User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12')]);

        $this->post('/login', ['email' => 'pic@smki.test', 'password' => 'secret12'])
            ->assertRedirect('/');
        $this->assertAuthenticated();
    }

    public function test_login_rejects_invalid_credentials(): void
    {
        User::factory()->create(['email' => 'pic@smki.test', 'password' => bcrypt('secret12')]);

        $this->post('/login', ['email' => 'pic@smki.test', 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
