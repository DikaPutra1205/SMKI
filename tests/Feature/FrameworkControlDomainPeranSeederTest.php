<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use Database\Seeders\ControlDomainPeranSeeder;
use Database\Seeders\ControlSeeder;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\FrameworkSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * §10 "Seeder ISO 27001:2022 & ISO 27701:2025 — Implemented
 * (FrameworkSeeder, ControlSeeder, ControlDomainPeranSeeder)".
 *
 * Two things are asserted here:
 *   1. the master data those seeders produce is correct and idempotent;
 *   2. ControlDomainPeranSeeder — which the spec lists as part of the seeding
 *      story — is neither wired into DatabaseSeeder nor safe to run on its own,
 *      because it addresses ISO 27701 by a hard-coded primary key (2).
 */
class FrameworkControlDomainPeranSeederTest extends TestCase
{
    use RefreshDatabase;

    // ── FrameworkSeeder ────────────────────────────────────────────────────

    public function test_framework_seeder_creates_both_iso_standards(): void
    {
        $this->seed(FrameworkSeeder::class);

        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO/IEC 27001', 'versi' => '2022', 'url_file' => null]);
        $this->assertDatabaseHas('frameworks', ['nama' => 'ISO/IEC 27701', 'versi' => '2025', 'url_file' => null]);
        $this->assertCount(2, Framework::all());
    }

    public function test_framework_seeder_is_idempotent(): void
    {
        $this->seed(FrameworkSeeder::class);
        $this->seed(FrameworkSeeder::class);

        $this->assertCount(2, Framework::all());
    }

    /**
     * GAP: FrameworkSeeder matches on `nama`+`versi` through `updateOrCreate`,
     * whose lookup is subject to the SoftDeletes global scope. After the Excel
     * import soft-deletes a framework that is absent from the uploaded sheet
     * (SmkiMasterDataImport "soft-deletes DB frameworks absent from Excel"), a
     * later `db:seed` no longer sees the trashed row and INSERTs a second,
     * active framework with the same `nama`. `frameworks.nama` carries no unique
     * index, so nothing blocks it — and ControlSeeder's
     * `Framework::where('nama', 'ISO/IEC 27001')->first()` then re-attaches all
     * 93 controls to the fresh row, orphaning every historical
     * checklist_entry / finding / risk that still points at the trashed one.
     *
     * The assertions below pin the current (duplicating) behaviour.
     */
    public function test_gap_framework_seeder_duplicates_framework_and_controls_after_a_soft_delete(): void
    {
        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);

        $original = Framework::query()->where('nama', 'ISO/IEC 27001')->firstOrFail();
        $controlCountBefore = $original->controls()->count();
        $this->assertGreaterThan(0, $controlCountBefore);

        // The import soft-deletes any framework missing from the uploaded sheet.
        // Framework::booted() also soft-deletes the framework's controls.
        $original->delete();
        $this->assertSame(0, $original->controls()->count());

        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);

        $this->assertSame(
            2,
            Framework::withTrashed()->where('nama', 'ISO/IEC 27001')->count(),
            'GAP: seeder membuat baris kedua alih-alih memulihkan baris soft-deleted'
        );

        $duplicate = Framework::query()->where('nama', 'ISO/IEC 27001')->firstOrFail();
        $this->assertNotSame($original->id, $duplicate->id);
        $this->assertSame(
            $controlCountBefore,
            $duplicate->controls()->count(),
            'GAP: 93 kontrol diduplikasi ke framework baru'
        );

        // The trashed framework keeps its 93 controls — but only as trashed rows,
        // so every historical checklist_entry / finding / risk that points at
        // them resolves to no control in the UI.
        $this->assertTrue($original->fresh()->trashed());
        $this->assertSame(
            $controlCountBefore,
            $original->controls()->withTrashed()->count(),
            'kontrol historis harus tetap ada sebagai baris soft-deleted'
        );
        $this->assertSame(
            0,
            $original->controls()->count(),
            'GAP: kontrol historis hilang dari relasi aktif framework aslinya'
        );
    }

    // ── ControlSeeder: domain_peran assignment ─────────────────────────────

    public function test_control_seeder_maps_iso27701_clause_prefixes_to_role_domains(): void
    {
        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);

        $iso27701 = Framework::query()->where('nama', 'ISO/IEC 27701')->firstOrFail();

        // 7.x → controller, 8.x → processor, 5.x/6.x → shared (NULL).
        $this->assertSame('controller', $iso27701->controls()->where('kode_klausul', '7.2.1')->firstOrFail()->domain_peran);
        $this->assertSame('controller', $iso27701->controls()->where('kode_klausul', '7.4.6')->firstOrFail()->domain_peran);
        $this->assertSame('processor', $iso27701->controls()->where('kode_klausul', '8.2.1')->firstOrFail()->domain_peran);
        $this->assertSame('processor', $iso27701->controls()->where('kode_klausul', '8.4.2')->firstOrFail()->domain_peran);

        // Every ISO 27701 control is inside 7.x or 8.x, so none may be NULL.
        $this->assertSame(0, $iso27701->controls()->whereNull('domain_peran')->count());

        $controllers = $iso27701->controls()->where('domain_peran', 'controller')->count();
        $processors = $iso27701->controls()->where('domain_peran', 'processor')->count();
        $this->assertSame(21, $controllers);
        $this->assertSame(9, $processors);
    }

    public function test_control_seeder_leaves_every_iso27001_control_role_agnostic(): void
    {
        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);

        $iso27001 = Framework::query()->where('nama', 'ISO/IEC 27001')->firstOrFail();

        $this->assertGreaterThan(0, $iso27001->controls()->count());
        $this->assertSame(
            0,
            $iso27001->controls()->whereNotNull('domain_peran')->count(),
            'ISO 27001 tidak punya pemisahan controller/processor'
        );
    }

    public function test_control_seeder_is_idempotent_and_keeps_clause_codes_unique_per_framework(): void
    {
        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);
        $before = Control::count();

        $this->seed([FrameworkSeeder::class, ControlSeeder::class]);

        $this->assertSame($before, Control::count());

        foreach (Framework::all() as $fw) {
            $codes = $fw->controls()->pluck('kode_klausul');
            $this->assertSame(
                $codes->count(),
                $codes->unique()->count(),
                "kode klausul duplikat pada framework {$fw->nama}"
            );
        }
    }

    // ── ControlDomainPeranSeeder ───────────────────────────────────────────

    public function test_control_domain_peran_seeder_is_not_registered_in_database_seeder(): void
    {
        $this->seed(DatabaseSeeder::class);

        // domain_peran is populated, but by ControlSeeder's inline column, not
        // by ControlDomainPeranSeeder — that seeder is unreachable from `db:seed`.
        $source = file_get_contents(database_path('seeders/DatabaseSeeder.php'));
        $this->assertStringNotContainsString(
            ControlDomainPeranSeeder::class,
            (string) $source,
            'ControlDomainPeranSigner tidak diregistrasikan di DatabaseSeeder — jalurnya mati'
        );

        $iso27701 = Framework::query()->where('nama', 'ISO/IEC 27701')->firstOrFail();
        $this->assertSame(0, $iso27701->controls()->whereNull('domain_peran')->count());
    }

    /**
     * GAP: ControlDomainPeranSeeder (ControlDomainPeranSeeder.php:20) addresses
     * ISO 27701 as `framework_id = 2` — a hard-coded primary key instead of a
     * lookup by `nama`. On any database where ISO 27701 is not row #2 — a fresh
     * migrate that seeded something else first, a restored backup, a truncated
     * sequence — the seeder silently updates a different framework's controls
     * (or none at all) and still exits 0 with no warning. The assertions below
     * pin the current (id-addressed) behaviour.
     */
    public function test_gap_control_domain_peran_seeder_targets_iso27701_by_hardcoded_id_2(): void
    {
        // ISO 27701 deliberately does not sit at any fixed key we control.
        Framework::create(['nama' => 'Filler A', 'versi' => 'v1']);
        Framework::create(['nama' => 'Filler B', 'versi' => 'v1']);
        Framework::create(['nama' => 'Filler C', 'versi' => 'v1']);
        $iso27701 = Framework::create(['nama' => 'ISO/IEC 27701', 'versi' => '2025']);
        $this->assertGreaterThan(4, $iso27701->id);

        $processor = $iso27701->controls()->create([
            'kode_klausul' => '8.2.1', 'judul' => 'Memproses PII', 'kategori' => 'teknologi',
            'domain_peran' => null,
        ]);
        $controller = $iso27701->controls()->create([
            'kode_klausul' => '7.2.1', 'judul' => 'Tujuan pengumpulan', 'kategori' => 'organisasional',
            'domain_peran' => null,
        ]);

        // Whatever row happens to hold primary key 2 gets the 7.x/8.x rewrite.
        $byId2 = Framework::withTrashed()->find(2);
        $id2Control = $byId2?->controls()->create([
            'kode_klausul' => '8.9.9', 'judul' => 'Kontrol di id 2', 'kategori' => 'teknologi',
            'domain_peran' => null,
        ]);

        $this->seed(ControlDomainPeranSeeder::class);

        // GAP: ISO 27701 rows are never reached — the seeder never resolved the standard.
        $this->assertNull($processor->refresh()->domain_peran);
        $this->assertNull($controller->refresh()->domain_peran);
        $this->assertSame(
            0,
            DB::table('controls')->where('framework_id', $iso27701->id)->whereNotNull('domain_peran')->count(),
            'GAP: seeder seharusnya mengisi domain_peran ISO 27701 apa pun primary key-nya'
        );

        if ($id2Control !== null) {
            $this->assertSame(
                'processor',
                $id2Control->refresh()->domain_peran,
                'GAP: seeder menulis ke framework ber-key 2, bukan ke ISO 27701'
            );
        }
    }
}
