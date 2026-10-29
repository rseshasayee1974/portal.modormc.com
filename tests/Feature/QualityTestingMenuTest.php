<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:46:40 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Plant, User};
use App\Services\InstallQualityTestingMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\{Permission, Role};

class QualityTestingMenuTest extends TestCase
{
    use RefreshDatabase;

    public function test_quality_menu_links_existing_testing_and_reporting_screens_without_duplicates(): void
    {
        InstallQualityTestingMenu::run();
        InstallQualityTestingMenu::run();
        $root = DB::table('mm_menus')->where('alias', 'quality')->first();
        $this->assertSame('Quality Testing & Report', $root->title);
        foreach (['quality-tests' => 'quality/tests', 'quality-concrete-tests' => 'inventory/concrete-quality-tests', 'quality-reports' => 'quality/reports'] as $alias => $link) {
            $menus = DB::table('mm_menus')->where('alias', $alias)->get();
            $this->assertCount(1, $menus);
            $this->assertEquals($root->id, $menus[0]->parent_id);
            $this->assertSame($link, $menus[0]->link);
            $this->assertEquals(1, $menus[0]->published);
        }
    }

    public function test_existing_qc_permissions_allow_testing_and_reporting_screens(): void
    {
        $plant = Plant::factory()->create();
        $user = User::factory()->create();
        $role = Role::firstOrCreate(['name' => 'Quality Viewer', 'guard_name' => 'web'], ['code' => 'QUALITY_VIEWER']);
        DB::table('mm_entity_users')->insert([
            'user_id' => $user->id, 'entity_id' => $plant->entity_id, 'plant_id' => $plant->id, 'role_id' => $role->id,
        ]);
        foreach (['QC.VIEW', 'QC_TEST.VIEW', 'QC_SAMPLE.VIEW', 'QC_REPORT.VIEW', 'CONCRETE_QUALITY_TEST.VIEW'] as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
            $user->givePermissionTo($name);
        }
        $this->actingAs($user)->withSession(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id]);
        foreach (['quality.dashboard', 'quality.tests.index', 'quality.samples.index', 'quality.reports.index', 'concrete-quality-tests.index'] as $route) {
            $this->get(route($route))->assertOk();
        }
    }

    public function test_quality_report_remains_forbidden_without_permission(): void
    {
        $plant = Plant::factory()->create();
        $this->actingAs(User::factory()->create())->withSession(['active_plant_id' => $plant->id, 'active_entity_id' => $plant->entity_id]);
        $this->get(route('quality.reports.index'))->assertForbidden();
    }
}
