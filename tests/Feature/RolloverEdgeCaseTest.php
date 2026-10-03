<?php

namespace Tests\Feature;

use App\Models\ChecklistEntry;
use App\Models\ChecklistSession;
use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use App\Models\WorkUnit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * §11 "Rollover edge cases" for US-C5 (100% verified rollover).
 *
 * Invariant under test, from CarryForwardVerifiedMap + the two provisioning
 * paths (smki:generate-monthly-checklist and Web ChecklistSessionController@generate):
 *   - previous session for the SAME (unit, framework, immediately-previous periode)
 *     absent            -> every new row is belum_dimulai, level_maturity NULL
 *   - previous row not `selesai_diterapkan` -> reset to belum_dimulai
 *   - previous row `selesai_diterapkan`     -> status + level_maturity carried,
 *     but PIC-authored fields (catatan) are cleared and admin verdict columns
 *     (tanggal_verifikasi, catatan_admin) are NOT copied
 *   - a gap in periode (prev-2) does not carry
 *   - a verified row in framework A never leaks into framework B's session
 *   - re-running the generator inside the same periode inserts nothing and
 *     never overwrites rows the PIC has since edited
 */
class RolloverEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    private WorkUnit $unit;

    private Framework $fwA;

    private Framework $fwB;

    private User $pic;

    private string $thisMonth;

    private string $prevMonth;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unit = WorkUnit::factory()->create(['nama' => 'Unit Rollover']);
        $this->fwA = Framework::factory()->create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        $this->fwB = Framework::factory()->create(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);
        $this->pic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $this->unit->id]);
        $this->thisMonth = now()->format('Y-m');
        $this->prevMonth = now()->subMonthNoOverflow()->format('Y-m');
    }

    private function control(Framework $fw, string $kode): Control
    {
        return $fw->controls()->create([
            'kode_klausul' => $kode,
            'judul' => 'Klausul '.$kode,
            'kategori' => 'teknologi',
        ]);
    }

    private function makeSession(string $periode, ?Framework $fw = null, ?int $unitId = null): ChecklistSession
    {
        return ChecklistSession::create([
            'konteks_penilaian' => 'Sesi '.$periode,
            'unit_id' => $unitId ?? $this->unit->id,
            'framework_id' => ($fw ?? $this->fwA)->id,
            'periode' => $periode,
        ]);
    }

    private function entry(ChecklistSession $s, Control $c, array $attrs = []): ChecklistEntry
    {
        return ChecklistEntry::create(array_merge([
            'session_id' => $s->id,
            'control_id' => $c->id,
            'unit_id' => $s->unit_id,
            'pic_id' => $this->pic->id,
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            'level_maturity' => null,
            'catatan' => '',
            'tanggal_input' => now()->subMonth(),
        ], $attrs));
    }

    private function generate(): void
    {
        $this->artisan('smki:generate-monthly-checklist --periode='.$this->thisMonth)->assertSuccessful();
    }

    private function newEntry(string $controlCode, ?Framework $fw = null): ChecklistEntry
    {
        return ChecklistEntry::query()
            ->whereHas('session', fn ($q) => $q
                ->where('unit_id', $this->unit->id)
                ->where('framework_id', ($fw ?? $this->fwA)->id)
                ->where('periode', $this->thisMonth))
            ->whereHas('control', fn ($q) => $q->where('kode_klausul', $controlCode))
            ->firstOrFail();
    }

    // ── Edge 1: no previous session at all ──────────────────────────────────
    public function test_no_previous_session_seeds_every_row_as_belum_dimulai(): void
    {
        $a = $this->control($this->fwA, 'A.5.1');
        $b = $this->control($this->fwA, 'A.5.2');

        $this->generate();

        foreach ([$a, $b] as $control) {
            $entry = $this->newEntry($control->kode_klausul);
            $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->status);
            $this->assertNull($entry->level_maturity);
            $this->assertSame('', $entry->catatan);
            $this->assertNull($entry->tanggal_verifikasi);
            $this->assertNull($entry->catatan_admin);
        }
    }

    // ── Edge 2: previous session exists, rows not verified -> reset ─────────
    public function test_previous_session_rows_without_verification_reset_to_belum_dimulai(): void
    {
        $controls = [];
        foreach (['A.5.1', 'A.5.2', 'A.5.3'] as $kode) {
            $controls[$kode] = $this->control($this->fwA, $kode);
        }

        $prev = $this->makeSession($this->prevMonth);

        // Never started.
        $this->entry($prev, $controls['A.5.1'], [
            'status' => ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            'level_maturity' => 3,
            'catatan' => 'Catatan PIC lama',
        ]);
        // In progress — notes present, no verification.
        $this->entry($prev, $controls['A.5.2'], [
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            'level_maturity' => 2,
            'catatan' => 'Separuh jalan',
        ]);
        // In review — awaiting admin verdict.
        $this->entry($prev, $controls['A.5.3'], [
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 5,
            'catatan' => 'Menunggu verifikasi',
        ]);

        $this->generate();

        foreach (['A.5.1', 'A.5.2', 'A.5.3'] as $kode) {
            $entry = $this->newEntry($kode);
            $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->status, "klausul {$kode} harus reset");
            $this->assertNull($entry->level_maturity, "klausul {$kode} level_maturity harus NULL");
            $this->assertSame('', $entry->catatan, "klausul {$kode} catatan harus dikosongkan");
        }
    }

    // ── Edge 3: not-applicable must NOT carry forward either ────────────────
    public function test_previous_not_applicable_row_does_not_carry_forward(): void
    {
        $control = $this->control($this->fwA, 'A.5.9');
        $prev = $this->makeSession($this->prevMonth);
        $this->entry($prev, $control, [
            'status' => ChecklistEntry::WORKFLOW_TIDAK_BERLAKU,
            'level_maturity' => null,
        ]);

        $this->generate();

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $this->newEntry('A.5.9')->status);
    }

    // ── Edge 4: verified row carries status + maturity, clears PIC fields ───
    public function test_verified_row_carries_status_and_maturity_but_clears_pic_and_admin_fields(): void
    {
        $control = $this->control($this->fwA, 'A.5.1');
        $prev = $this->makeSession($this->prevMonth);
        $this->entry($prev, $control, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
            'catatan' => 'SOP bulan lalu',
            'catatan_admin' => 'Sudah sesuai',
            'tanggal_verifikasi' => now()->subMonthNoOverflow()->startOfMonth(),
        ]);

        $this->generate();

        $entry = $this->newEntry('A.5.1');

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $entry->status);
        $this->assertSame(4, $entry->level_maturity);
        // QA note US-C5: "month N+1 row has same status/level, empty catatan,
        // fresh tanggal_input" — the admin verdict belongs to the old month.
        $this->assertSame('', $entry->catatan);
        $this->assertNull($entry->catatan_admin);
        $this->assertNull($entry->tanggal_verifikasi);
        $this->assertTrue(
            $entry->tanggal_input->isSameDay(now()),
            'tanggal_input harus di-refresh ke hari generator berjalan, bukan diwarisi dari bulan lalu'
        );
    }

    // ── Edge 5: a periode gap blocks the carry ──────────────────────────────
    public function test_verified_row_two_months_ago_does_not_carry_forward(): void
    {
        $control = $this->control($this->fwA, 'A.5.1');
        $twoMonthsAgo = $this->makeSession(now()->subMonthsNoOverflow(2)->format('Y-m'));
        $this->entry($twoMonthsAgo, $control, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 5,
        ]);

        $this->generate();

        $entry = $this->newEntry('A.5.1');
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $entry->status);
        $this->assertNull($entry->level_maturity);
    }

    // ── Edge 6: carry is per (unit, framework), never leaks across ─────────
    public function test_verified_row_does_not_leak_into_another_framework_session(): void
    {
        $controlA = $this->control($this->fwA, 'A.5.1');
        $this->control($this->fwB, '7.2.1');

        $prevA = $this->makeSession($this->prevMonth, $this->fwA);
        $this->entry($prevA, $controlA, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 5,
        ]);

        $this->generate();

        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $this->newEntry('A.5.1', $this->fwA)->status);
        $this->assertSame(
            ChecklistEntry::WORKFLOW_BELUM_DIMULAI,
            $this->newEntry('7.2.1', $this->fwB)->status,
            'klausul framework lain tidak boleh mewarisi status terverifikasi'
        );
    }

    // ── Edge 7: another unit's verified row never leaks in ─────────────────
    public function test_verified_row_from_another_unit_does_not_carry_forward(): void
    {
        $other = WorkUnit::factory()->create(['nama' => 'Unit Tetangga']);
        $otherPic = User::factory()->create(['role' => User::ROLE_PIC, 'unit_id' => $other->id]);
        $control = $this->control($this->fwA, 'A.5.1');

        $prevOther = $this->makeSession($this->prevMonth, $this->fwA, $other->id);
        $this->entry($prevOther, $control, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 5,
        ]);
        $this->assertNotNull($otherPic);

        $this->generate();

        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $this->newEntry('A.5.1')->status);
    }

    // ── Edge 8: re-running the generator mid-month is idempotent ────────────
    public function test_rerunning_generator_in_same_period_inserts_nothing_and_preserves_pic_edits(): void
    {
        $control = $this->control($this->fwA, 'A.5.1');
        $prev = $this->makeSession($this->prevMonth);
        $this->entry($prev, $control, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
        ]);

        $this->generate();

        $sessionCount = ChecklistSession::query()
            ->where('unit_id', $this->unit->id)
            ->where('periode', $this->thisMonth)
            ->count();
        $entryCount = ChecklistEntry::query()
            ->whereHas('session', fn ($q) => $q->where('unit_id', $this->unit->id)->where('periode', $this->thisMonth))
            ->count();
        $this->assertSame(1, $sessionCount);
        $this->assertSame(1, $entryCount);

        // PIC works on the carried row between the two generator runs.
        $carried = $this->newEntry('A.5.1');
        $carried->update([
            'status' => ChecklistEntry::WORKFLOW_DALAM_PROSES,
            'level_maturity' => 1,
            'catatan' => 'PIC sedang memperbarui',
        ]);

        $this->generate();

        $this->assertSame(1, ChecklistSession::query()
            ->where('unit_id', $this->unit->id)
            ->where('periode', $this->thisMonth)
            ->count(), 'sesi tidak boleh terduplikasi');
        $this->assertSame(1, ChecklistEntry::query()
            ->whereHas('session', fn ($q) => $q->where('unit_id', $this->unit->id)->where('periode', $this->thisMonth))
            ->count(), 'lembar checklist tidak boleh terduplikasi');

        $carried->refresh();
        $this->assertSame(ChecklistEntry::WORKFLOW_DALAM_PROSES, $carried->status, 'jalur provisioning ulang menimpa pekerjaan PIC');
        $this->assertSame(1, $carried->level_maturity);
        $this->assertSame('PIC sedang memperbarui', $carried->catatan);
    }

    // ── Edge 9: same edge set through the Web manual-generate path ─────────
    public function test_web_manual_generate_applies_the_same_rollover_rules(): void
    {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);

        $verified = $this->control($this->fwA, 'A.5.1');
        $unverified = $this->control($this->fwA, 'A.5.2');

        $prev = $this->makeSession($this->prevMonth);
        $this->entry($prev, $verified, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
            'catatan' => 'SOP bulan lalu',
        ]);
        $this->entry($prev, $unverified, [
            'status' => ChecklistEntry::WORKFLOW_DALAM_TINJAUAN,
            'level_maturity' => 3,
            'catatan' => 'Menunggu verifikasi',
        ]);

        $this->actingAs($admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Sesi bulan ini',
                'unit_id' => $this->unit->id,
                'framework_id' => $this->fwA->id,
                'periode' => $this->thisMonth,
            ])
            ->assertRedirect();

        $carried = $this->newEntry('A.5.1');
        $this->assertSame(ChecklistEntry::WORKFLOW_SELESAI, $carried->status);
        $this->assertSame(4, $carried->level_maturity);
        $this->assertSame('', $carried->catatan);

        $reset = $this->newEntry('A.5.2');
        $this->assertSame(ChecklistEntry::WORKFLOW_BELUM_DIMULAI, $reset->status);
        $this->assertNull($reset->level_maturity);
        $this->assertSame('', $reset->catatan);
    }

    /**
     * Documents a divergence between the two provisioning paths.
     *
     * smki:generate-monthly-checklist stamps `tanggal_input = now()` on every
     * seeded row (GenerateMonthlyChecklistCommand.php:107), but the Web manual
     * path stamps `tanggal_input = null`
     * (Web/ChecklistSessionController.php:186). US-C5's QA note asks for a
     * "fresh tanggal_input" on the month-N+1 row, which only the command
     * satisfies — so a session created by hand shows blank timestamps in the
     * admin review queue while a scheduled one does not.
     */
    public function test_web_manual_generate_leaves_tanggal_input_null_while_command_stamps_it(): void
    {
        $control = $this->control($this->fwA, 'A.5.1');
        $prev = $this->makeSession($this->prevMonth);
        $this->entry($prev, $control, [
            'status' => ChecklistEntry::WORKFLOW_SELESAI,
            'level_maturity' => 4,
        ]);

        $admin = User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]);
        $this->actingAs($admin)
            ->postJson('/admin/kepatuhan/checklist-sessions', [
                'konteks_penilaian' => 'Sesi manual',
                'unit_id' => $this->unit->id,
                'framework_id' => $this->fwA->id,
                'periode' => $this->thisMonth,
            ])
            ->assertRedirect();

        $this->assertNull(
            $this->newEntry('A.5.1')->tanggal_input,
            'jalur Web tidak menyetel tanggal_input — berbeda dari command'
        );

        // Same shape, different generator: the command stamps a fresh timestamp.
        ChecklistSession::query()->where('periode', $this->thisMonth)->delete();
        $this->generate();
        $this->assertTrue($this->newEntry('A.5.1')->tanggal_input->isSameDay(now()));
    }
}
