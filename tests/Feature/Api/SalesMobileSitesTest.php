<?php
/*
Author: Cipher
Created: 2026-10-12 12:07:12 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SalesMobileSitesTest extends TestCase
{
    private function reviewContext(array $permissions = [], bool $owner = false): void
    {
        DB::table('mm_quotations')->insert(['id' => 1, 'plant_id' => 11, 'created_by' => 99, 'mobile_submitted_by' => 99, 'mobile_approval_state' => 'pending', 'status' => 1]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->id = 10;
        $user->setRelation('personnel', null);
        $user->shouldReceive('hasAnyRole')->andReturn($owner);
        $user->shouldReceive('getDirectPermissions')->andReturn(collect());
        $this->actingAs($user);
        Route::matched(function ($event) use ($permissions) {
            $event->request->attributes->set('mobile_system_admin', false);
            $event->request->attributes->set('mobile_role', (object) ['permissions' => collect($permissions)->map(fn ($name) => (object) ['name' => $name])]);
        });
    }

    public function test_saas_owner_can_review_own_draft_without_permission(): void
    {
        $this->reviewContext([], true);
        DB::table('mm_quotations')->where('id', 1)->update(['sales_executive_id'=>110, 'status' => 0, 'mobile_approval_state' => 'draft', 'mobile_submitted_by' => 10]);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertOk()
            ->assertJsonPath('data.mobile_approval_state', 'approved')->assertJsonPath('data.mobile_reviewed_by', 10);
    }

    public function test_reviewer_requires_visibility_without_module_permission(): void
    {
        $this->reviewContext();
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertNotFound()
            ->assertExactJson(['message' => 'Quotation not found or you do not have access to it.']);
        Route::matched(fn ($event) => $event->request->attributes->set('mobile_role', (object) ['permissions' => collect([(object) ['name' => 'QUOTATION.APPROVE']])]));
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertNotFound()
            ->assertExactJson(['message' => 'Quotation not found or you do not have access to it.']);
    }

    public function test_permitted_reviewer_can_approve_and_cannot_review_again(): void
    {
        $this->reviewContext(['QUOTATION.APPROVE', 'QUOTATION.VIEW_ALL']);
        DB::table('mm_quotations')->where('id',1)->update(['sales_executive_id'=>110]);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertOk()->assertJsonPath('data.status', 2);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertUnprocessable()
            ->assertExactJson(['message' => 'Only submitted quotations can be reviewed.']);
    }

    public function test_self_review_foreign_plant_and_rejection_validation(): void
    {
        $this->reviewContext(['QUOTATION.APPROVE', 'QUOTATION.VIEW_ALL']);
        DB::table('mm_quotations')->where('id', 1)->update(['sales_executive_id'=>110, 'mobile_submitted_by' => 10]);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertForbidden()
            ->assertExactJson(['message' => 'You cannot review your own quotation submission.']);
        DB::table('mm_quotations')->where('id', 1)->update(['mobile_submitted_by' => 99, 'plant_id' => 12]);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'approved'])->assertNotFound();
        DB::table('mm_quotations')->where('id', 1)->update(['plant_id' => 11]);
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'rejected'])->assertUnprocessable()->assertJsonValidationErrors('comment');
        $this->postJson('/api/mobile/v1/quotations/1/review', ['decision' => 'rejected', 'comment' => 'Revise rates'])->assertOk()
            ->assertJsonPath('data.status', 3)->assertJsonPath('data.mobile_approval_comment', 'Revise rates');
    }

    public function test_mobile_errors_only_return_message_even_with_debug_enabled(): void
    {
        config(['app.debug' => true]);
        Route::get('api/mobile/v1/test-error', fn () => abort(422, 'Quotation already converted into a customer PO.'));
        $this->getJson('/api/mobile/v1/test-error')->assertUnprocessable()
            ->assertExactJson(['message' => 'Quotation already converted into a customer PO.']);
        $this->getJson('/api/mobile/v1/sites/999')->assertNotFound()
            ->assertJsonStructure(['message'])->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
    }

    public function test_validation_errors_keep_field_details_without_trace(): void
    {
        config(['app.debug' => true]);
        $this->getJson('/api/mobile/v1/sites?per_page=0')->assertUnprocessable()
            ->assertJsonValidationErrors('per_page')->assertJsonMissingPath('trace')->assertJsonMissingPath('exception');
    }

    protected function setUp(): void
    {
        parent::setUp();
        // Isolate endpoint behavior from the shared device and plant middleware.
        $this->withoutMiddleware();
        Route::matched(function ($event) {
            $event->request->attributes->set('mobile_plant_id', 11);
            $event->request->attributes->set('mobile_system_admin', true);
        });
        Schema::create('mm_sites', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->string('name');
            $table->string('site_address_1')->nullable();
            $table->unsignedBigInteger('patron_id')->nullable();
            $table->softDeletes();
        });
        DB::table('mm_sites')->insert([
            ['id' => 1, 'plant_id' => 11, 'name' => 'Alpha', 'site_address_1' => 'First Street', 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 11, 'name' => 'Beta', 'site_address_1' => null, 'deleted_at' => null],
            ['id' => 3, 'plant_id' => 12, 'name' => 'Foreign', 'site_address_1' => null, 'deleted_at' => null],
            ['id' => 4, 'plant_id' => 11, 'name' => 'Deleted', 'site_address_1' => null, 'deleted_at' => now()],
        ]);
        foreach ([
            'mm_personnels' => ['plant_id','user_id'],
            'mm_patrons' => ['plant_id','sales_executive_id','patron_type','operational_status'],
            'mm_crm_leads' => ['plant_id','assigned_to','customer_id'],
            'mm_crm_deals' => ['plant_id','lead_id','quotation_id'],
            'mm_sales_orders' => ['plant_id','sales_executive_id','customer_id','site_id'],
            'mm_customer_pos' => ['plant_id','sales_executive_id','patron_id','site_id','quotation_id'],
            'mm_quotations' => ['plant_id','created_by','updated_by','sales_executive_id','patron_id','site_id','mobile_approval_state','mobile_submitted_by','mobile_approval_comment','mobile_reviewed_by','mobile_reviewed_at','status'],
            'mm_quotation_items' => ['quotation_id','mix_design_id','tax_id','uom_id'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) $table->text($column)->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
        DB::table('mm_personnels')->insert(['id'=>110,'user_id'=>10,'plant_id'=>11]);
        DB::table('mm_patrons')->insert(['id'=>1,'plant_id'=>11,'sales_executive_id'=>110,'patron_type'=>'["Customer"]','operational_status'=>'active']);
        DB::table('mm_sites')->whereIn('id',[1,2])->update(['patron_id'=>1]);
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->shouldReceive('isSystemAdmin')->andReturn(true);
        $user->shouldReceive('getDirectPermissions')->andReturn(collect());
        $this->actingAs($user);

    }

    public function test_list_is_paginated_and_excludes_foreign_and_deleted_sites(): void
    {
        $this->getJson('/api/mobile/v1/sites?per_page=1')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.data.0.id', 1)->assertJsonCount(1, 'data.data');
        $this->getJson('/api/mobile/v1/sites?per_page=1&page=2')->assertOk()->assertJsonPath('data.data.0.id', 2);
    }

    public function test_search_and_empty_results(): void
    {
        $this->getJson('/api/mobile/v1/sites?search=Beta')->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 2);
        $this->getJson('/api/mobile/v1/sites?search=Absent')->assertOk()->assertJsonCount(0, 'data.data');
    }

    public function test_detail_returns_site_fields(): void
    {
        $this->getJson('/api/mobile/v1/sites/1')->assertOk()->assertJsonPath('success', true)
            ->assertJsonPath('data.id', 1)->assertJsonPath('data.site_address_1', 'First Street');
    }

    public function test_sites_are_visible_without_customer_or_user_relationships(): void
    {
        DB::table('mm_sites')->where('id',1)->update(['patron_id'=>999]);
        DB::table('mm_sites')->where('id',2)->update(['patron_id'=>null]);
        DB::table('mm_personnels')->delete();
        Route::matched(function ($event) {
            $event->request->attributes->set('mobile_system_admin', false);
            $event->request->attributes->set('mobile_role', (object) [
                'code'=>'SALES_EXECUTIVE', 'permissions'=>collect([(object) ['name'=>'SITE.VIEW']]),
            ]);
        });
        $this->getJson('/api/mobile/v1/sites')->assertOk()->assertJsonPath('data.total',2);
        $this->getJson('/api/mobile/v1/sites/1')->assertOk()->assertJsonPath('data.id',1);
        $this->getJson('/api/mobile/v1/sites/2')->assertOk()->assertJsonPath('data.id',2);
        $this->getJson('/api/mobile/v1/sites/3')->assertNotFound();
        $this->getJson('/api/mobile/v1/sites/4')->assertNotFound();
    }

    public function test_missing_foreign_deleted_and_non_numeric_ids_return_404(): void
    {
        foreach ([3, 4, 999, 'invalid'] as $id) {
            $this->getJson('/api/mobile/v1/sites/'.$id)->assertNotFound();
        }
    }

    public function test_invalid_list_parameters_return_validation_errors(): void
    {
        foreach (['per_page=0', 'per_page=101', 'per_page=abc', 'search[]=Alpha'] as $query) {
            $this->getJson('/api/mobile/v1/sites?'.$query)->assertUnprocessable();
        }
        $this->getJson('/api/mobile/v1/sites?search='.str_repeat('a', 101))->assertUnprocessable();
    }

    public function test_site_view_permission_is_required(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->shouldReceive('getDirectPermissions')->andReturn(collect());
        $this->actingAs($user);
        Route::matched(fn ($event) => $event->request->attributes->set('mobile_system_admin', false));
        $this->getJson('/api/mobile/v1/sites')->assertForbidden();
        $this->getJson('/api/mobile/v1/sites/1')->assertForbidden();
    }

    public function test_role_checks_support_strings_arrays_pipes_and_guards(): void
    {
        $user = new User;
        $role = new \Spatie\Permission\Models\Role;
        $role->forceFill(['id' => 1, 'name' => 'Platform Admin', 'guard_name' => 'web']);
        $user->setRelation('roles', new \Illuminate\Database\Eloquent\Collection([$role]));

        $this->assertTrue($user->hasRole('Platform Admin'));
        $this->assertTrue($user->hasAnyRole(['Sales Executive', 'Platform Admin']));
        $this->assertTrue($user->hasRole('Sales Executive|Platform Admin'));
        $this->assertTrue($user->hasRole('Platform Admin', 'web'));
        $this->assertFalse($user->hasRole('Platform Admin', 'api'));
        $this->assertFalse($user->hasRole('Sales Executive'));
        $this->assertTrue($user->isSystemAdmin());
        $user->setRelation('roles', new \Illuminate\Database\Eloquent\Collection);
        $this->assertFalse($user->isSystemAdmin());
    }

    public function test_super_admin_has_all_plants_and_other_users_only_access_assigned_active_plants(): void
    {
        Schema::create('mm_entities', function (Blueprint $table) {
            $table->id();
            $table->boolean('is_suspended');
            $table->softDeletes();
        });
        Schema::create('mm_plants', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('entity_id');
            $table->boolean('is_active');
            $table->softDeletes();
        });
        Schema::create('mm_entity_users', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('entity_id');
            $table->unsignedBigInteger('plant_id')->nullable();
            $table->softDeletes();
        });
        DB::table('mm_entities')->insert([
            ['id' => 1, 'is_suspended' => false], ['id' => 2, 'is_suspended' => true],
        ]);
        DB::table('mm_plants')->insert([
            ['id' => 1, 'entity_id' => 1, 'is_active' => true, 'deleted_at' => null],
            ['id' => 2, 'entity_id' => 1, 'is_active' => true, 'deleted_at' => null],
            ['id' => 3, 'entity_id' => 1, 'is_active' => false, 'deleted_at' => null],
            ['id' => 4, 'entity_id' => 2, 'is_active' => true, 'deleted_at' => null],
            ['id' => 5, 'entity_id' => 1, 'is_active' => true, 'deleted_at' => now()],
        ]);
        DB::table('mm_entity_users')->insert([
            ['user_id' => 10, 'entity_id' => 1, 'plant_id' => 1, 'deleted_at' => null],
            ['user_id' => 10, 'entity_id' => 1, 'plant_id' => 2, 'deleted_at' => now()],
            ['user_id' => 10, 'entity_id' => 1, 'plant_id' => 3, 'deleted_at' => null],
            ['user_id' => 10, 'entity_id' => 2, 'plant_id' => 4, 'deleted_at' => null],
            ['user_id' => 20, 'entity_id' => 1, 'plant_id' => null, 'deleted_at' => null],
        ]);
        $admin = new User;
        $role = new \Spatie\Permission\Models\Role;
        $role->forceFill(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin->setRelation('roles', new \Illuminate\Database\Eloquent\Collection([$role]));
        $this->assertSame([1, 2, 4], \App\Services\Mobile\MobileAccess::plants($admin)->orderBy('id')->pluck('id')->all());

        $user = new User;
        $user->id = 10;
        $user->setRelation('roles', new \Illuminate\Database\Eloquent\Collection);
        $this->assertSame([1], \App\Services\Mobile\MobileAccess::plants($user)->pluck('id')->all());
        $user->id = 20;
        $this->assertSame([1, 2], \App\Services\Mobile\MobileAccess::plants($user)->orderBy('id')->pluck('id')->all());
        $user->id = 30;
        $this->assertSame([], \App\Services\Mobile\MobileAccess::plants($user)->pluck('id')->all());
    }

    public function test_other_users_can_view_sites_with_role_or_direct_permission(): void
    {
        $user = \Mockery::mock(User::class)->makePartial();
        $user->id = 10;
        $user->shouldReceive('getDirectPermissions')->andReturn(collect(), collect([(object) ['name' => 'SITE.VIEW']]));
        $this->actingAs($user);
        $role = (object) ['permissions' => collect([(object) ['name' => 'SITE.VIEW']])];
        Route::matched(function ($event) use ($role) {
            $event->request->attributes->set('mobile_system_admin', false);
            $event->request->attributes->set('mobile_role', $event->request->is('*/sites/1') ? null : $role);
        });
        $this->getJson('/api/mobile/v1/sites')->assertOk();
        $this->getJson('/api/mobile/v1/sites/1')->assertOk();
    }
}
