<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OrphanPagesTest extends TestCase
{
    public function test_every_tsx_page_has_backend_reference(): void
    {
        $pages = collect(glob(resource_path('js/pages/**/*.tsx')))
            ->map(fn ($p) => str($p)->after(resource_path('js/pages/'))->before('.tsx')->toString())
            ->filter(fn ($n) => ! str($n)->contains('components'))
            ->values();

        $appText = collect(File::allFiles(app_path()))
            ->map(fn ($f) => file_get_contents($f->getPathname()))
            ->implode("\n").file_get_contents(base_path('routes/web.php'));

        // The only allowed orphan after this plan is none; assert pic/dashboard is now referenced
        $this->assertStringContainsString('pic/dashboard', $appText, 'pic/dashboard.tsx still orphaned — no Inertia::render or dispatcher ref');
        $this->assertStringContainsString('admin-kepatuhan/dashboard', $appText);
        $this->assertStringContainsString('auditor/dashboard', $appText);
        $this->assertStringContainsString('superadmin/dashboard', $appText);
    }
}
