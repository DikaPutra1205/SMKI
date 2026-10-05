<?php

namespace Tests\Feature;

use App\Models\User;
use App\Routing\PageDispatcher;
use App\Services\NavigationService;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanduanPageTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function roleProvider(): array
    {
        return [
            'superadmin' => [User::ROLE_SUPERADMIN],
            'admin_kepatuhan' => [User::ROLE_ADMIN_KEPATUHAN],
            'koordinator' => [User::ROLE_KOORDINATOR_SMKI],
            'auditor' => [User::ROLE_AUDITOR],
            'pic' => [User::ROLE_PIC],
        ];
    }

    #[DataProvider('roleProvider')]
    public function test_panduan_resolves_for_all_roles(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $res = app(PageDispatcher::class)->resolve($user, 'panduan');

        $this->assertTrue($res->allowed, "panduan denied for role [{$role}]");
        $this->assertSame('shared/panduan', $res->component);
    }

    #[DataProvider('roleProvider')]
    public function test_panduan_nav_visible_last_for_all_roles(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);
        $nav = app(NavigationService::class)->getForUser($user);
        $urls = collect($nav)->pluck('url')->filter()->values()->all();

        $this->assertContains('/panduan', $urls, "missing /panduan nav for role [{$role}]");
        $this->assertSame('/panduan', end($urls), "panduan not last for role [{$role}]: [".implode(', ', $urls).']');
    }

    #[DataProvider('roleProvider')]
    public function test_panduan_route_returns_200_for_all_roles(string $role): void
    {
        $user = User::factory()->create(['role' => $role]);

        $this->actingAs($user)->get('/panduan')->assertOk();
    }
}
