<?php

namespace Tests\Feature;

use App\Models\Control;
use App\Models\Framework;
use App\Models\User;
use Tests\TestCase;

class MasterDataPageTest extends TestCase
{
    public function test_anonymous_master_data_page_redirects_to_login(): void
    {
        $this->get('/admin/kepatuhan/master-data')->assertRedirect('/login');
    }

    public function test_pic_without_control_view_gets_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_PIC]))
            ->get('/admin/kepatuhan/master-data')
            ->assertForbidden();
    }

    public function test_superadmin_sees_master_data_page_with_frameworks(): void
    {
        $fw = Framework::create(['nama' => 'ISO/IEC 27001', 'versi' => '2022']);
        Control::create([
            'framework_id' => $fw->id,
            'kode_klausul' => 'A.5.1',
            'judul' => 'Kebijakan',
            'kategori' => 'organisasional',
        ]);

        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]))
            ->get('/admin/kepatuhan/master-data')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin-kepatuhan/master-data')
                ->has('frameworks', 1)
                ->where('frameworks.0.nama', 'ISO/IEC 27001')
                ->where('frameworks.0.versi', '2022')
                ->where('frameworks.0.controls_count', 1)
            );
    }

    public function test_admin_kepatuhan_sees_master_data_page(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_ADMIN_KEPATUHAN]))
            ->get('/admin/kepatuhan/master-data')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('admin-kepatuhan/master-data'));
    }

    public function test_master_data_page_renders_empty_frameworks(): void
    {
        $this->actingAs(User::factory()->create(['role' => User::ROLE_SUPERADMIN]))
            ->get('/admin/kepatuhan/master-data')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin-kepatuhan/master-data')
                ->where('frameworks', [])
            );
    }
}
