<?php

/*
Author: ragul-onemodo
Created: 2026-10-09 18:09:15 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Http\Controllers\Api\SalesMobile\AuthController;
use App\Http\Controllers\UserMobileAccessController;
use App\Http\Middleware\EnforceBrowserSession;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\MobilePlantContext;
use App\Http\Middleware\MobileSalesScope;
use App\Http\Middleware\SetEntityContext;
use App\Models\CrmLead;
use App\Models\CustomerPO;
use App\Models\PushNotificationLog;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\SalesOrder;
use App\Models\User;
use App\Notifications\BusinessActivityNotification;
use App\Services\DashboardService;
use App\Services\Mobile\MobileAccess;
use App\Services\PushNotificationService;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Request;
use Illuminate\Routing\RouteCollection;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SalesMobileApiTest extends TestCase
{
    private string $token;

    private string $reviewer;

    public function withHeaders(array $headers)
    {
        $this->app['auth']->forgetGuards();

        return parent::withHeaders($headers);
    }

    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'mobile_test', 'database.connections.mobile_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''], 'cache.default' => 'array', 'session.driver' => 'array']);
        config(['services.tendigit.license_number' => 'test-license', 'services.tendigit.api_key' => 'test-key', 'services.mobile_otp.template' => 'sales_executive_login']);
        Http::fake(['https://app.tendigit.in/*' => Http::response(['status' => 'success'], 200)]);
        $this->app->bind(AuthController::class, fn () => new class extends AuthController
        {
            protected function sendWhatsAppTemplate(string $licenseNumber, string $apiKey, string $contact, string $template, string $paramString): array
            {
                try {
                    $response = Http::get('https://app.tendigit.in/api/sendtemplate.php', ['LicenseNumber' => $licenseNumber, 'APIKey' => $apiKey, 'Contact' => $contact, 'Template' => $template, 'Param' => $paramString]);

                    return ['error' => false, 'response' => $response->body(), 'http_status' => $response->status()];
                } catch (ConnectionException $e) {
                    return ['error' => true, 'error_code' => 'WHATSAPP_TLS_ERROR', 'message' => 'The WhatsApp provider HTTPS certificate does not validate.'];
                }
            }
        });
        Route::put('/testing/mobile-access', [UserMobileAccessController::class, 'update'])->middleware('auth:sanctum');
        foreach ([
            'mm_users' => ['username', 'email', 'mobile', 'password', 'api_key', 'plan', 'email_verified_at', 'is_active', 'is_otp_enabled', 'last_login', 'lockout_until', 'default_plant_id', 'default_entity_id', 'two_factor_confirmed_at', 'mobile_access_revision', 'login_status', 'login_location', 'ip_address', 'failed_attempts'],
            'mm_entities' => ['name', 'is_suspended'], 'mm_plants' => ['name', 'entity_id', 'is_active', 'gstin'],
            'mm_personnels' => ['plant_id', 'user_id', 'first_name', 'last_name', 'entity_id', 'status', 'employee_code', 'designation_id'], 'mm_roles' => ['name', 'code', 'guard_name'],
            'mm_permissions' => ['name', 'guard_name', 'module', 'description', 'is_system'], 'mm_entity_users' => ['user_id', 'entity_id', 'plant_id', 'role_id'],
            'mm_crm_leads' => ['plant_id', 'lead_number', 'enquiry_date', 'contact_name', 'company_name', 'phone', 'email', 'address', 'source', 'status', 'priority', 'assigned_to', 'project_name', 'mix_design_id', 'requirement', 'estimated_quantity', 'delivery_location', 'expected_value', 'expected_purchase_date', 'remarks', 'lost_reason', 'customer_id', 'converted_at', 'created_by', 'updated_by', 'deleted_by'],
            'mm_crm_deals' => ['plant_id', 'lead_id', 'quotation_id', 'stage', 'expected_value', 'created_by', 'updated_by', 'deleted_by'],
            'mm_crm_activities' => ['plant_id', 'lead_id', 'type', 'subject', 'description', 'due_at', 'completed_at', 'assigned_to', 'created_by', 'updated_by', 'deleted_by'],
            'mm_patrons' => ['plant_id', 'sales_executive_id', 'legal_name', 'patron_type', 'status', 'operational_status'], 'mm_sites' => ['plant_id', 'patron_id', 'name'],
            'mm_concrete_grades' => ['plant_id', 'name'],
            'mm_mix_design_items' => ['plant_id', 'mix_design_id', 'product_id', 'uom_id', 'rate', 'actual_quantity', 'cross_quantity', 'variation_quantity'],
            'mm_products' => ['plant_id', 'title', 'code', 'material_code'],
            'mm_mix_designs' => ['plant_id', 'design_name', 'design_code', 'is_active', 'concrete_grade_id', 'unit_id', 'rate_per_qty'], 'mm_product_units' => ['unit_name', 'unit_code', 'unit_type'],
            'mm_taxes' => ['plant_id', 'tax_name', 'tax_type', 'tax_rate', 'parent_id', 'status', 'tax_group'],
            'mm_quotations' => ['plant_id', 'reference', 'patron_id', 'site_id', 'quote_date', 'validity_date', 'notes', 'is_tax_inclusive', 'sales_executive_id', 'adjustment', 'status', 'is_customer_po', 'amount_untaxed', 'amount_tax', 'amount_total', 'created_by', 'updated_by', 'deleted_by', 'mobile_approval_state', 'mobile_submitted_by', 'mobile_submitted_at', 'mobile_reviewed_by', 'mobile_reviewed_at', 'mobile_approval_comment'],
            'mm_quotation_items' => ['discount_type', 'discount', 'discount_amount', 'quotation_id', 'mix_design_id', 'uom_id', 'quantity', 'rate', 'tax_id', 'tax_amount', 'untaxed_amount', 'amount_total', 'concrete_pump', 'pump_rate', 'created_by', 'updated_by', 'deleted_by'],
            'mm_contacts' => ['patron_id', 'plant_id', 'name', 'phone', 'mobile', 'contact_type_id', 'is_primary', 'status', 'email'],
            'mm_personnel_contacts' => ['employee_id', 'contact_id', 'contact_value', 'is_primary'],
            'mm_customer_pos' => ['plant_id', 'quotation_id', 'patron_id', 'site_id', 'created_by', 'updated_by', 'deleted_by', 'sales_executive_id', 'is_tax_inclusive', 'reference', 'customer_po_reference', 'status', 'order_date', 'amount_untaxed', 'amount_tax', 'amount_total'],
            'mm_customer_po_items' => ['deleted_by', 'customer_po_id', 'mix_design_id', 'rate', 'tax_id', 'quantity', 'concrete_pump', 'pump_rate', 'untaxed_amount', 'tax_amount', 'amount_total'],
            'mm_custom_settings' => ['plant_id', 'module_name', 'settings'],
            'mm_batches' => ['plant_id', 'sales_order_id', 'batch_no', 'batch_size', 'status', 'start_time', 'end_time'],
            'mm_sales_orders' => ['plant_id', 'created_by', 'updated_by', 'deleted_by', 'sales_executive_id', 'customer_id', 'site_id', 'mix_design_id', 'total_qty', 'rate', 'tax_id', 'is_tax_inclusive', 'customer_po_id', 'produced_qty', 'status', 'order_no', 'prefix', 'concrete_pump', 'pump_rate', 'scheduled_start', 'scheduled_end'],
            'mm_dispatches' => ['customer_po_id', 'plant_id', 'sales_order_id', 'batch_id', 'truck_id', 'prefix', 'dispatch_no', 'dispatch_status', 'delivered_qty', 'dispatch_time', 'delivery_time'],
            'mm_machine_types' => ['name'], 'mm_designations' => ['name'],
            'mm_machines' => ['plant_id', 'registration', 'vehicle_model', 'vehicle_type', 'is_active'],
            'mm_dispatch_statuses' => ['dispatch_id', 'is_tax_inclusive'],
            'mm_concrete_batching_schedules' => ['plant_id', 'sales_order_id', 'status', 'batch_id', 'dispatch_id', 'schedule_date', 'site_id', 'mix_design_id', 'qty_m3', 'order_volume_m3', 'remaining_volume_m3', 'vehicle_id', 'driver_id', 'pump_vehicle_id', 'pump_type', 'pour_reference', 'batching_time', 'dispatch_time', 'eta_site', 'unloading_start', 'unloading_end', 'notes', 'created_by', 'updated_by', 'deleted_by'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) {
                $t->id();
                foreach ($columns as $c) {
                    $t->text($c)->nullable();
                } $t->timestamps();
                $t->softDeletes();
            });
        }
        foreach (['mm_role_has_permissions' => ['role_id', 'permission_id'], 'mm_model_has_roles' => ['role_id', 'model_id', 'model_type'], 'mm_model_has_permissions' => ['permission_id', 'model_id', 'model_type']] as $name => $columns) {
            Schema::create($name, function (Blueprint $t) use ($columns) {
                foreach ($columns as $c) {
                    $t->text($c);
                }
            });
        }
        Schema::create('personal_access_tokens', function (Blueprint $t) {
            $t->id();
            $t->morphs('tokenable');
            $t->string('name');
            $t->string('token', 64)->unique();
            $t->text('abilities')->nullable();
            $t->timestamp('last_used_at')->nullable();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
        DB::table('mm_entities')->insert([['id' => 1, 'is_suspended' => 0], ['id' => 2, 'is_suspended' => 0]]);
        DB::table('mm_plants')->insert([['id' => 1, 'name' => 'Guindy', 'entity_id' => 1, 'is_active' => 1], ['id' => 2, 'name' => 'Other', 'entity_id' => 2, 'is_active' => 1]]);
        DB::table('mm_roles')->insert([['id' => 1, 'name' => 'Sales Executive', 'code' => 'SALES_EXECUTIVE', 'guard_name' => 'web'], ['id' => 2, 'name' => 'Sales Manager', 'code' => 'SALES_MANAGER', 'guard_name' => 'web']]);
        foreach ([1, 2] as $id) {
            DB::table('mm_users')->insert(['id' => $id, 'username' => 'User '.$id, 'email' => 'user'.$id.'@example.test', 'mobile' => $id === 1 ? '9876543210' : '9876543211', 'password' => Hash::make('Test-password-123'), 'is_active' => 1, 'is_otp_enabled' => 1, 'mobile_access_revision' => 0]);
            DB::table('mm_entity_users')->insert(['user_id' => $id, 'entity_id' => 1, 'plant_id' => 1, 'role_id' => $id]);
            DB::table('mm_personnels')->insert(['id' => $id, 'plant_id' => 1, 'user_id' => $id]);
        }
        foreach (['CRM_LEAD.VIEW', 'CRM_LEAD.CREATE', 'CRM_LEAD.UPDATE', 'CRM_LEAD.DELETE', 'QUOTATION.VIEW', 'QUOTATION.CREATE', 'QUOTATION.UPDATE', 'QUOTATION.DELETE', 'QUOTATION.APPROVE', 'QUOTATION.VIEW_ALL', 'SALES_ORDER.VIEW', 'BATCHING_SCHEDULE.VIEW', 'BATCHING_SCHEDULE.UPDATE', 'PATRON.VIEW', 'SITE.VIEW', 'PUSH_NOTIFICATION.RECEIVE'] as $i => $name) {
            DB::table('mm_permissions')->insert(['id' => $i + 1, 'name' => $name, 'guard_name' => 'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id' => 2, 'permission_id' => $i + 1]);
            if (! in_array($name, ['QUOTATION.APPROVE', 'QUOTATION.VIEW_ALL'])) {
                DB::table('mm_role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $i + 1]);
            }
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->token = User::find(1)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        $this->reviewer = User::find(2)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        DB::table('mm_patrons')->insert(['id' => 1, 'plant_id' => 1, 'sales_executive_id' => 1, 'legal_name' => 'Customer', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active']);
        DB::table('mm_sites')->insert(['id' => 1, 'plant_id' => 1, 'patron_id' => 1, 'name' => 'Site']);
        DB::table('mm_mix_designs')->insert(['id' => 1, 'plant_id' => 1, 'design_name' => 'M25', 'is_active' => 1]);
        DB::table('mm_taxes')->insert([['id' => 1, 'plant_id' => 1, 'tax_name' => 'GST18', 'tax_group' => 'GST', 'tax_type' => 'Sales', 'tax_rate' => 18, 'parent_id' => null, 'status' => 1], ['id' => 2, 'plant_id' => 1, 'tax_name' => 'CGST9', 'tax_group' => 'CGST', 'tax_type' => 'Sales', 'tax_rate' => 9, 'parent_id' => 1, 'status' => 1], ['id' => 3, 'plant_id' => 1, 'tax_name' => 'SGST9', 'tax_group' => 'SGST', 'tax_type' => 'Sales', 'tax_rate' => 9, 'parent_id' => 1, 'status' => 1]]);
        $this->customerProvisioningSchema();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'X-Plant-Id' => '1']);
    }

    public function test_customers_and_sites_are_shared_read_only_within_the_selected_plant(): void
    {
        DB::table('mm_patrons')->insert([
            ['id' => 700, 'plant_id' => 1, 'sales_executive_id' => 2, 'legal_name' => 'Shared Customer', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active'],
            ['id' => 701, 'plant_id' => 2, 'sales_executive_id' => 2, 'legal_name' => 'Foreign Customer', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active'],
        ]);
        DB::table('mm_sites')->insert([
            ['id' => 700, 'plant_id' => 1, 'patron_id' => '[700]', 'name' => 'Shared Site'],
            ['id' => 701, 'plant_id' => 2, 'patron_id' => '[701]', 'name' => 'Foreign Site'],
        ]);
        foreach (['customers', 'sites'] as $resource) {
            $ids = array_column($this->getJson('/api/mobile/v1/'.$resource)->assertOk()->json('data.data'), 'id');
            $this->assertContains(700, $ids);
            $this->assertNotContains(701, $ids);
            $this->getJson('/api/mobile/v1/'.$resource.'/700')->assertOk();
            $this->getJson('/api/mobile/v1/'.$resource.'/701')->assertNotFound();
            $this->postJson('/api/mobile/v1/'.$resource, [])->assertStatus(405);
            $this->putJson('/api/mobile/v1/'.$resource.'/700', [])->assertStatus(405);
            $this->deleteJson('/api/mobile/v1/'.$resource.'/700')->assertStatus(405);
        }
        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        foreach (['customers', 'sites'] as $key) {
            $ids = array_column($lookups->json('data.'.$key), 'id');
            $this->assertContains(700, $ids);
            $this->assertNotContains(701, $ids);
        }
        $body = array_replace($this->quote(), ['patron_id' => 700, 'site_id' => 700]);
        $this->postJson('/api/mobile/v1/quotations', $body)->assertCreated();
        $this->postJson('/api/mobile/v1/quotations', array_replace($body, ['patron_id' => 701, 'site_id' => 701]))
            ->assertUnprocessable()->assertJsonValidationErrors(['patron_id', 'site_id'])
            ->assertJsonPath('errors.patron_id.0', 'The selected customer is unavailable in the selected plant.');
        $this->assertEquals('Shared Customer', DB::table('mm_patrons')->where('id', 700)->value('legal_name'));
    }

    public function test_sales_quotation_submission_admin_approval_and_status_reflection(): void
    {
        $id = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()->json('data.id');
        DB::table('mm_roles')->where('id', 2)->update(['code' => 'ADMINISTRATOR']);
        $adminToken = User::find(2)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken]);
        $this->getJson('/api/mobile/quotations?approval_state=pending')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/quotations/'.$id)->assertNotFound();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token]);
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk()->assertJsonPath('data.mobile_approval_state', 'pending');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review', ['decision' => 'approved'])->assertForbidden();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertNotFound();
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken]);
        $this->getJson('/api/mobile/quotations?approval_state=pending')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/quotations/'.$id)->assertOk()->assertJsonPath('data.patron.id', 1);
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'approved', 'comment' => 'Approved rate'])
            ->assertOk()->assertJsonPath('data.status', fn ($value) => (int) $value === 2)->assertJsonPath('data.mobile_approval_state', 'approved');
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'rejected', 'comment' => 'Changed mind'])->assertUnprocessable();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token]);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk()
            ->assertJsonPath('data.status', fn ($value) => (int) $value === 2)->assertJsonPath('data.mobile_approval_state', 'approved')
            ->assertJsonPath('data.mobile_reviewed_by', fn ($value) => (int) $value === 2)->assertJsonPath('data.mobile_approval_comment', 'Approved rate');
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertUnprocessable();
        $this->assertEquals(1, DB::table('mm_quotations')->where('id', $id)->value('created_by'));
    }

    public function test_admin_quotation_rejection_plant_scope_and_role_checks(): void
    {
        $id = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk();
        $nonAdminToken = User::find(1)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$nonAdminToken]);
        $this->getJson('/api/mobile/quotations')->assertForbidden();
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'approved'])->assertForbidden();
        DB::table('mm_roles')->where('id', 2)->update(['code' => 'ADMINISTRATOR']);
        $adminToken = User::find(2)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$adminToken]);
        DB::table('mm_quotations')->where('id', $id)->update(['plant_id' => 2]);
        $this->getJson('/api/mobile/quotations/'.$id)->assertNotFound();
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'approved'])->assertNotFound();
        DB::table('mm_quotations')->where('id', $id)->update(['plant_id' => 1]);
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'rejected'])->assertUnprocessable();
        $this->postJson('/api/mobile/quotations/'.$id.'/review', ['decision' => 'rejected', 'comment' => 'Revise rate'])->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token]);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk()->assertJsonPath('data.mobile_approval_state', 'rejected')
            ->assertJsonPath('data.status', fn ($value) => (int) $value === 3)->assertJsonPath('data.mobile_approval_comment', 'Revise rate');
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertOk()->assertJsonPath('data.mobile_approval_state', 'draft');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk();
    }

    public function test_admin_app_can_qualify_and_approve_executive_leads_only_in_selected_plant(): void
    {
        DB::table('mm_permissions')->insert(['id' => 9900, 'name' => 'CRM_LEAD.APPROVE', 'guard_name' => 'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id' => 2, 'permission_id' => 9900]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        DB::table('mm_roles')->where('id', 2)->update(['code' => 'ADMINISTRATOR']);
        $token = User::find(2)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token]);
        $this->getJson('/api/mobile/leads')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/leads/'.$id)->assertOk();
        $this->patchJson('/api/mobile/leads/'.$id, ['status' => 'Qualified', 'assigned_to' => 1])->assertOk()->assertJsonPath('data.status', 'Qualified');
        $this->postJson('/api/mobile/leads/'.$id.'/approve')->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->assertEquals(1, DB::table('mm_crm_leads')->where('id', $id)->value('created_by'));
        $this->assertEquals(1, DB::table('mm_crm_leads')->where('id', $id)->value('assigned_to'));
        DB::table('mm_crm_leads')->where('id', $id)->update(['plant_id' => 2]);
        $this->getJson('/api/mobile/leads/'.$id)->assertNotFound();
        $this->postJson('/api/mobile/leads/'.$id.'/approve')->assertNotFound();
    }

    public function test_sales_app_uses_creator_scope_even_when_lead_is_assigned_to_another_user(): void
    {
        DB::table('mm_permissions')->insert(['id' => 9900, 'name' => 'CRM_LEAD.APPROVE', 'guard_name' => 'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id' => 2, 'permission_id' => 9900]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        DB::table('mm_crm_leads')->where('id', $id)->update(['assigned_to' => 2]);
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/leads')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertNotFound();
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertNotFound();
        $token = User::find(1)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token]);
        $this->getJson('/api/mobile/leads')->assertForbidden();
        $this->getJson('/api/mobile/leads/statuses')->assertForbidden();
    }

    public function test_main_mobile_commercial_routes_require_their_own_token_and_plant(): void
    {
        $this->getJson('/api/mobile/orders')->assertForbidden();
        $token = User::find(1)->createToken('web-login')->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Plant-Id' => '1']);
        $this->getJson('/api/mobile/orders')->assertOk();
        $this->getJson('/api/mobile/quotations')->assertOk();
        $this->getJson('/api/mobile/customer-pos')->assertOk();
        $this->getJson('/api/mobile/batch-schedules')->assertOk();
        $this->withHeaders(['X-Plant-Id' => '2']);
        $this->getJson('/api/mobile/orders')->assertForbidden();
    }

    public function test_customer_po_updates_calculate_taxes_and_preserve_ownership(): void
    {
        DB::table('mm_customer_pos')->insert(['id' => 986, 'plant_id' => 1, 'sales_executive_id' => 1, 'patron_id' => 1, 'site_id' => 1, 'status' => 0]);
        $response = $this->putJson('/api/mobile/v1/customer-pos/986', [
            'patron_id' => 1, 'site_id' => 1, 'order_date' => '2026-10-21',
            'is_tax_inclusive' => false, 'sales_executive_id' => 2,
            'items' => [['mix_design_id' => 1, 'quantity' => 2, 'rate' => 100, 'tax_id' => 1]],
        ])->assertOk();
        $this->assertEquals(236, $response->json('data.amount_total'));
        $this->assertEquals(1, $response->json('data.sales_executive_id'));
        $this->getJson('/api/mobile/v1/customer-pos/986')->assertOk();
        $this->deleteJson('/api/mobile/v1/customer-pos/986')->assertOk();
        $this->getJson('/api/mobile/v1/customer-pos/986')->assertNotFound();
    }

    public function test_customer_po_mutations_hide_unowned_records_and_validate_inputs(): void
    {
        DB::table('mm_customer_pos')->insert(['id' => 987, 'plant_id' => 1, 'sales_executive_id' => 2]);
        $this->getJson('/api/mobile/v1/customer-pos/987')->assertNotFound();
        $this->putJson('/api/mobile/v1/customer-pos/987', [])->assertNotFound();
        $this->deleteJson('/api/mobile/v1/customer-pos/987')->assertNotFound();
        $this->postJson('/api/mobile/v1/customer-pos/987/convert-salesorder', [])->assertNotFound();
        $this->postJson('/api/mobile/v1/customer-pos', [])->assertUnprocessable();
    }

    public function test_sales_executive_scope_ignores_view_all_and_creator_access(): void
    {
        request()->setUserResolver(fn () => User::findOrFail(1));
        request()->attributes->set('mobile_plant_id', 1);
        request()->attributes->set('mobile_system_admin', true);
        request()->attributes->set('mobile_role', (object) ['code' => 'SALES_EXECUTIVE']);
        DB::table('mm_sales_orders')->insert([
            ['id' => 101, 'plant_id' => 1, 'created_by' => 2, 'sales_executive_id' => 1],
            ['id' => 102, 'plant_id' => 1, 'created_by' => 1, 'sales_executive_id' => 2],
            ['id' => 103, 'plant_id' => 2, 'created_by' => 1, 'sales_executive_id' => 1],
        ]);
        $this->assertFalse(MobileAccess::all('SALES_ORDER'));
        $this->assertEquals([101], MobileAccess::orders()->pluck('id')->all());
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        request()->attributes->remove('mobile_personnel:1:1');
        $this->assertSame([], MobileAccess::orders()->pluck('id')->all());
    }

    public function test_lead_mix_design_ids_are_saved_returned_and_plant_validated(): void
    {
        DB::table('mm_mix_designs')->insert([
            ['id' => 991, 'plant_id' => 1, 'design_name' => 'Lead Mix', 'is_active' => 1],
            ['id' => 992, 'plant_id' => 2, 'design_name' => 'Other Mix', 'is_active' => 1],
            ['id' => 993, 'plant_id' => 1, 'design_name' => 'Inactive Mix', 'is_active' => 0],
        ]);
        $this->withToken($this->token);
        $response = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['mix_design_id' => 991]))
            ->assertCreated()->assertJsonPath('data.mix_design_id', 991)->assertJsonPath('data.mix_design.design_name', 'Lead Mix');
        $id = $response->json('data.id');
        foreach ([992, 993, 'Lead Mix'] as $value) {
            $this->putJson('/api/mobile/v1/leads/'.$id, array_replace($this->lead(), ['mix_design_id' => $value]))
                ->assertUnprocessable()->assertJsonValidationErrors('mix_design_id');
        }
        $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['mix_design_id' => 992]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.mix_design_id.0', 'The selected mix design is unavailable in this plant. Refresh the mix designs for the selected plant and choose an active design.');
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertOk()->assertJsonPath('data.mix_design_id', 991);
    }

    public function test_quotation_responses_include_priced_items_and_mix_design_details(): void
    {
        DB::table('mm_product_units')->insert(['id' => 901, 'unit_name' => 'Cubic metre']);
        DB::table('mm_concrete_grades')->insert(['id' => 901, 'plant_id' => 1, 'name' => 'M25']);
        DB::table('mm_products')->insert(['id' => 901, 'plant_id' => 1, 'title' => 'Demo Cement', 'code' => 'CEM']);
        DB::table('mm_mix_designs')->where('id', 1)->update(['concrete_grade_id' => 901, 'unit_id' => 901, 'rate_per_qty' => 546]);
        DB::table('mm_mix_design_items')->insert(['plant_id' => 1, 'mix_design_id' => 1, 'product_id' => 901, 'uom_id' => 901, 'rate' => 6, 'actual_quantity' => 300]);
        $body = $this->quote();
        $body['items'][0]['uom_id'] = 901;
        $created = $this->postJson('/api/mobile/v1/quotations', $body)->assertCreated();
        $id = $created->json('data.id');
        $assertDetails = function ($response, string $prefix) {
            $response->assertJsonPath($prefix.'.items.0.mix_design.design_name', 'M25')
                ->assertJsonPath($prefix.'.items.0.mix_design.concrete_grade.name', 'M25')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.product.title', 'Demo Cement')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.actual_quantity', '300.0000')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.rate', '6.0000')
                ->assertJsonPath($prefix.'.items.0.uom.unit_name', 'Cubic metre')
                ->assertJsonStructure(['data']);
            foreach (['quantity', 'rate', 'discount_amount', 'tax_amount', 'untaxed_amount', 'amount_total', 'pump_rate'] as $field) {
                $this->assertNotNull($response->json($prefix.'.items.0.'.$field));
            }
        };
        $assertDetails($created, 'data');
        $assertDetails($this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk(), 'data');
        $assertDetails($this->getJson('/api/mobile/v1/quotations')->assertOk(), 'data.data.0');
        $assertDetails($this->putJson('/api/mobile/v1/quotations/'.$id, $body)->assertOk(), 'data');
        $assertDetails($this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk(), 'data');
    }

    private function convertedQuotationLead(int $id = 501): void
    {
        $permission = DB::table('mm_permissions')->insertGetId(['name' => 'CRM_LEAD.CONVERT', 'guard_name' => 'web']);
        foreach ([1, 2] as $role) {
            DB::table('mm_role_has_permissions')->insert(['role_id' => $role, 'permission_id' => $permission]);
        }
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('mm_mix_designs')->where('id', 1)->update(['rate_per_qty' => 546]);
        DB::table('mm_crm_leads')->insert([
            'id' => $id, 'plant_id' => 1, 'assigned_to' => 1, 'contact_name' => 'Quote Contact',
            'lead_number' => 'LD-DEMO-'.$id, 'customer_id' => 1, 'converted_at' => now(), 'status' => 'Converted',
            'mix_design_id' => 1, 'estimated_quantity' => 3.55, 'project_name' => 'Lead Project', 'delivery_location' => 'Site delivery',
        ]);
        DB::table('mm_crm_deals')->insert(['plant_id' => 1, 'lead_id' => $id, 'stage' => 'Requirement Received']);
    }

    public function test_create_quotation_from_lead_uses_source_details_and_shared_pricing(): void
    {
        $this->convertedQuotationLead();
        $result = $this->postJson('/api/mobile/v1/leads/501/quotation', ['patron_id' => 1, 'tax_id' => 1, 'is_tax_inclusive' => true])
            ->assertCreated()->assertJsonPath('data.patron_id', fn ($value) => (int) $value === 1)->assertJsonPath('data.site_id', fn ($value) => (int) $value === 1)
            ->assertJsonPath('data.sales_executive_id', 1)->assertJsonPath('data.mobile_approval_state', 'draft')
            ->assertJsonPath('data.items.0.mix_design.design_name', 'M25')->assertJsonPath('data.amount_total', '1938.00');
        $this->assertEquals(3.55, $result->json('data.items.0.quantity'));
        $this->assertEquals(546, $result->json('data.items.0.rate'));
        $this->assertStringContainsString('Lead Project', $result->json('data.notes'));
        $this->assertEquals($result->json('data.id'), DB::table('mm_crm_deals')->where('lead_id', 501)->value('quotation_id'));
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable()->assertJsonValidationErrors('lead_id');
        $this->assertSame(1, DB::table('mm_quotations')->count());
    }

    public function test_lead_quotation_rejects_inaccessible_and_incomplete_source_data(): void
    {
        $this->convertedQuotationLead();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertNotFound();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token]);
        DB::table('mm_crm_leads')->where('id', 501)->update(['converted_at' => null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable()->assertJsonValidationErrors('lead_id');
        DB::table('mm_crm_leads')->where('id', 501)->update(['converted_at' => now(), 'estimated_quantity' => null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        DB::table('mm_crm_leads')->where('id', 501)->update(['estimated_quantity' => 3.55]);
        DB::table('mm_mix_designs')->where('id', 1)->update(['is_active' => 0]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable()->assertJsonValidationErrors('mix_design_id');
        $this->assertSame(0, DB::table('mm_quotations')->count());
        $this->assertNull(DB::table('mm_crm_deals')->where('lead_id', 501)->value('quotation_id'));
    }

    public function test_lead_quotation_requires_unambiguous_customer_site_and_allows_price_override(): void
    {
        $this->convertedQuotationLead();
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['patron_id' => 'Customer name'])->assertUnprocessable()->assertJsonValidationErrors('patron_id');
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['patron_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('patron_id');
        DB::table('mm_sites')->insert(['id' => 502, 'plant_id' => 1, 'patron_id' => 1, 'name' => 'Second site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        DB::table('mm_sites')->insert(['id' => 503, 'plant_id' => 2, 'patron_id' => 2, 'name' => 'Other plant site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['site_id' => 503])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $result = $this->postJson('/api/mobile/v1/leads/501/quotation', ['site_id' => 502, 'quantity' => 2, 'rate' => 600])
            ->assertCreated()->assertJsonPath('data.site_id', fn ($value) => (int) $value === 502)->assertJsonPath('data.amount_total', '1200.00');
        $this->assertEquals(600, $result->json('data.items.0.rate'));
    }

    public function test_lead_quotation_uses_logged_in_user_without_personnel_assignment(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        $id = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.sales_executive_id', null)->json('data.id');
        $this->assertEquals(1, DB::table('mm_quotations')->where('id', $id)->value('created_by'));
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/quotations')->assertOk()->assertJsonPath('data.total', 1);
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertNotFound();
    }

    public function test_lead_quotation_preserves_personnel_foreign_key_when_user_id_differs(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_personnels')->where('user_id', 1)->update(['id' => 101]);
        $id = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.sales_executive_id', 101)->json('data.id');
        $this->assertEquals(1, DB::table('mm_quotations')->where('id', $id)->value('created_by'));
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk();
    }

    public function test_lead_quotation_accepts_global_uom_with_full_pricing_payload(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_product_units')->insert(['id' => 1, 'unit_name' => 'Cubic metre', 'unit_code' => 'm3']);
        $payload = [
            'site_id' => 1, 'quantity' => 30, 'rate' => 4200, 'uom_id' => 1, 'tax_id' => 1,
            'is_tax_inclusive' => false, 'concrete_pump' => '36m Boom Pump', 'pump_rate' => 350,
            'patron_id' => 1, 'discount_type' => 'amount', 'discount' => 1500,
            'quote_date' => '2026-10-17', 'validity_date' => '2026-11-01',
            'notes' => 'Quotation includes 36m Boom Pump charges. Pour scheduled for early morning.',
        ];
        $this->postJson('/api/mobile/v1/leads/501/quotation', array_replace($payload, ['uom_id' => 999]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.uom_id');
        DB::table('mm_product_units')->where('id', 1)->update(['deleted_at' => now()]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.uom_id');
        DB::table('mm_product_units')->where('id', 1)->update(['deleted_at' => null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', $payload)->assertCreated()
            ->assertJsonPath('data.items.0.uom_id', fn ($id) => (int) $id === 1)
            ->assertJsonPath('data.amount_total', '147323.00');
        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        $this->assertEquals([1], array_column($lookups->json('data.units'), 'id'));
    }

    private function customerProvisioningSchema(): void
    {
        foreach ([
            'mm_patrons' => ['code', 'displayed'],
            'mm_contacts' => ['displayed'],
            'mm_crm_deals' => ['customer_id', 'name', 'expected_close_date'],
            'mm_sites' => ['site_address_1', 'site_address_2', 'type', 'status', 'is_active', 'is_restricted'],
        ] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->text($column)->nullable();
                }
            });
        }
        foreach ([
            'mm_contact_types' => ['type'], 'mm_address_types' => ['type'],
            'mm_addresses' => ['plant_id', 'contact_id', 'address_type_id', 'line_1', 'line_2', 'is_primary', 'status'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($columns) {
                $t->id();
                foreach ($columns as $column) {
                    $t->text($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        }
        DB::table('mm_contact_types')->insert(['id' => 1, 'type' => 'Primary']);
        DB::table('mm_address_types')->insert(['id' => 1, 'type' => 'Office']);
    }

    private function unconvertedQuotationLead(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_crm_deals')->where('lead_id', 501)->delete();
        DB::table('mm_crm_leads')->where('id', 501)->update([
            'status' => 'New', 'converted_at' => null, 'customer_id' => null,
            'company_name' => 'New Company', 'phone' => '9876500000', 'email' => 'quote@example.test', 'address' => 'Office address',
        ]);
    }

    public function test_lead_quotation_automatically_creates_customer_contact_address_and_site(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['quantity' => 0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(1, DB::table('mm_patrons')->count());
        $this->assertSame(0, DB::table('mm_contacts')->count());
        $this->assertNull(DB::table('mm_crm_leads')->where('id', 501)->value('converted_at'));

        $this->postJson('/api/mobile/v1/leads/501/quotation', ['tax_id' => 999])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
        $this->assertSame(1, DB::table('mm_sites')->count());
        $this->assertSame(1, DB::table('mm_patrons')->count());
        $this->assertSame(0, DB::table('mm_crm_deals')->count());
        $this->assertSame(0, DB::table('mm_contacts')->count());
        $this->assertSame(0, DB::table('mm_addresses')->count());

        $result = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $customerId = $result->json('data.patron_id');
        $customer = DB::table('mm_patrons')->find($customerId);
        $this->assertEquals('New Company', $customer->legal_name);
        $this->assertEquals(['Customer'], json_decode($customer->patron_type, true));
        $this->assertEquals('active', $customer->operational_status);
        $this->assertEquals(1, $customer->status);
        $contact = DB::table('mm_contacts')->where('patron_id', $customerId)->first();
        $this->assertEquals('Quote Contact', $contact->name);
        $this->assertEquals('9876500000', $contact->mobile);
        $this->assertEquals('quote@example.test', $contact->email);
        $this->assertEquals('Office address', DB::table('mm_addresses')->where('contact_id', $contact->id)->value('line_1'));
        $site = DB::table('mm_sites')->find($result->json('data.site_id'));
        $this->assertEquals([$customerId], json_decode($site->patron_id, true));
        $this->assertEquals('Lead Project', $site->name);
        $this->assertEquals('Site delivery', $site->site_address_1);
        $this->assertEquals('Converted', DB::table('mm_crm_leads')->where('id', 501)->value('status'));
        $this->assertEquals($result->json('data.id'), DB::table('mm_crm_deals')->where('lead_id', 501)->value('quotation_id'));
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable();
        $this->assertSame(2, DB::table('mm_patrons')->count());
    }

    public function test_lead_quotation_does_not_reuse_an_unrelated_same_name_site(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_sites')->insert(['id' => 502, 'plant_id' => 1, 'patron_id' => '[999]', 'name' => ' lead PROJECT ']);
        $response = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $this->assertNotEquals(502, $response->json('data.site_id'));
        $this->assertEquals('[999]', DB::table('mm_sites')->where('id', 502)->value('patron_id'));
    }

    public function test_lead_quotation_ignores_deleted_and_foreign_same_name_sites(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_sites')->insert([
            ['id' => 502, 'plant_id' => 1, 'name' => 'Lead Project', 'deleted_at' => now()],
            ['id' => 503, 'plant_id' => 2, 'name' => 'Lead Project', 'deleted_at' => null],
        ]);
        $result = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $this->assertNotContains((int) $result->json('data.site_id'), [502, 503]);
        $this->assertSame(4, DB::table('mm_sites')->count());
    }

    public function test_converted_lead_quotation_rejects_an_unrelated_site_in_its_plant(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_sites')->insert(['id' => 502, 'plant_id' => 1, 'patron_id' => null, 'name' => 'Shared site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['site_id' => 502])
            ->assertUnprocessable()->assertJsonValidationErrors('site_id');
    }

    public function test_converted_lead_uses_related_site_instead_of_unrelated_same_name_site(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_sites')->insert(['id' => 502, 'plant_id' => 1, 'patron_id' => 999, 'name' => 'Lead Project']);
        $response = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $this->assertNotEquals(502, $response->json('data.site_id'));
    }

    public function test_json_customer_sites_and_nested_po_orders_are_user_and_plant_scoped(): void
    {
        DB::table('mm_sites')->insert([
            ['id' => 700, 'plant_id' => 1, 'patron_id' => '[1]', 'name' => 'Related JSON site'],
            ['id' => 701, 'plant_id' => 1, 'patron_id' => '[999]', 'name' => 'Other customer site'],
            ['id' => 702, 'plant_id' => 2, 'patron_id' => '[1]', 'name' => 'Other plant site'],
        ]);
        $this->getJson('/api/mobile/v1/sites/700')->assertOk();
        foreach ([701, 702] as $id) $this->getJson('/api/mobile/v1/sites/'.$id)->assertNotFound();
        $ids = array_column($this->getJson('/api/mobile/v1/lookups')->assertOk()->json('data.sites'), 'id');
        $this->assertContains(700, $ids);
        $this->assertNotContains(701, $ids);
        $this->assertNotContains(702, $ids);
        DB::table('mm_customer_pos')->insert(['id' => 700, 'plant_id' => 1, 'sales_executive_id' => 1]);
        DB::table('mm_sales_orders')->insert([
            ['id' => 700, 'plant_id' => 1, 'sales_executive_id' => 1, 'customer_po_id' => 700],
            ['id' => 701, 'plant_id' => 1, 'sales_executive_id' => 2, 'customer_po_id' => 700],
            ['id' => 702, 'plant_id' => 2, 'sales_executive_id' => 1, 'customer_po_id' => 700],
        ]);
        $this->getJson('/api/mobile/v1/customer-pos/700')->assertOk()
            ->assertJsonCount(1, 'data.sales_orders')->assertJsonPath('data.sales_orders.0.id', 700);
    }

    public function test_shared_commercial_routes_enforce_the_same_visibility_in_both_apps(): void
    {
        $this->seedExecutiveVisibilityRecords();
        $mainToken = User::find(1)->createToken('web-login')->plainTextToken;
        foreach (['v1/' => $this->token, '' => $mainToken] as $prefix => $token) {
            $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Plant-Id' => '1']);
            foreach (['quotations', 'orders', 'customer-pos', 'batch-schedules'] as $resource) {
                $response = $this->getJson('/api/mobile/'.$prefix.$resource)->assertOk();
                $this->assertSame([101], array_column($response->json('data.data'), 'id'));
                $this->getJson('/api/mobile/'.$prefix.$resource.'/102')->assertNotFound();
            }
            $this->getJson('/api/mobile/'.$prefix.'sites/2')->assertNotFound();
            $this->withHeaders(['X-Plant-Id' => '2']);
            foreach (['quotations', 'orders', 'customer-pos', 'batch-schedules', 'lookups'] as $resource) {
                $this->getJson('/api/mobile/'.$prefix.$resource)->assertForbidden();
            }
        }
    }

    public function test_partial_lead_updates_preserve_omitted_fields_and_allow_status_only_qualification(): void
    {
        $id = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), [
            'email' => 'retained@example.test', 'remarks' => 'Original remark', 'company_name' => 'Partial Company', 'project_name' => 'Partial Site',
        ]))->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['remarks' => 'Updated remark'])->assertOk()
            ->assertJsonPath('data.phone', '9876543210')->assertJsonPath('data.email', 'retained@example.test')
            ->assertJsonPath('data.status', 'New')->assertJsonPath('data.remarks', 'Updated remark');
        $this->patchJson('/api/mobile/v1/leads/'.$id, ['status' => 'Qualified'])->assertOk()
            ->assertJsonPath('data.status', 'Qualified')->assertJsonPath('data.phone', '9876543210')
            ->assertJsonPath('data.email', 'retained@example.test');
        $this->assertNotNull(DB::table('mm_crm_leads')->where('id', $id)->value('customer_id'));
        $this->assertSame(2, DB::table('mm_sites')->count());
        $this->putJson('/api/mobile/v1/leads/'.$id, [])->assertOk();
    }

    public function test_partial_contact_updates_normalize_provided_values_and_preserve_one_contact(): void
    {
        $id = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['email' => 'existing@example.test']))->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['phone' => null])->assertOk()
            ->assertJsonPath('data.phone', null)->assertJsonPath('data.email', 'existing@example.test');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['email' => null])->assertUnprocessable()->assertJsonValidationErrors(['phone', 'email']);
        $this->putJson('/api/mobile/v1/leads/'.$id, ['phone' => '+91 98765 00123', 'email' => null])->assertOk()
            ->assertJsonPath('data.phone', '919876500123')->assertJsonPath('data.email', null);
        $this->putJson('/api/mobile/v1/leads/'.$id, ['email' => ' UPDATED@EXAMPLE.TEST '])->assertOk()
            ->assertJsonPath('data.email', 'updated@example.test')->assertJsonPath('data.phone', '919876500123');
    }

    public function test_partial_updates_validate_supplied_fields_contact_uniqueness_and_loss_reason(): void
    {
        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        $other = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['phone' => '9876500099']))->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['phone' => '9876500099'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['phone' => '9876543210'])->assertOk();
        $this->putJson('/api/mobile/v1/leads/'.$id, ['contact_name' => null])->assertUnprocessable()->assertJsonValidationErrors('contact_name');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['status' => 'Unqualified'])->assertUnprocessable()->assertJsonValidationErrors('lost_reason');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['status' => 'Unqualified', 'lost_reason' => 'No requirement'])->assertOk();
        $this->putJson('/api/mobile/v1/leads/'.$id, ['remarks' => 'Follow-up note'])->assertOk()->assertJsonPath('data.lost_reason', 'No requirement');
        $this->putJson('/api/mobile/v1/leads/'.$id, ['lost_reason' => null])->assertUnprocessable()->assertJsonValidationErrors('lost_reason');
        $this->putJson('/api/mobile/v1/leads/'.$other, ['assigned_to' => null])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
    }

    public function test_converted_lead_can_receive_partial_update_without_resending_status(): void
    {
        $this->convertedQuotationLead();
        $this->putJson('/api/mobile/v1/leads/501', ['remarks' => 'Converted lead note'])->assertOk()
            ->assertJsonPath('data.status', 'Converted')->assertJsonPath('data.remarks', 'Converted lead note');
        $this->putJson('/api/mobile/v1/leads/501', ['status' => 'New'])->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_lead_status_dropdown_options_returned_by_api(): void
    {
        $res = $this->getJson('/api/mobile/v1/leads/statuses')->assertOk();
        $this->assertEquals([
            ['label' => 'New', 'value' => 'New'],
            ['label' => 'Contacted', 'value' => 'Contacted'],
            ['label' => 'Qualified', 'value' => 'Qualified'],
            ['label' => 'Unqualified', 'value' => 'Unqualified'],
        ], $res->json('data'));

        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        $detail = $this->getJson('/api/mobile/v1/leads/'.$id)->assertOk();
        $this->assertEquals(['New', 'Contacted', 'Qualified', 'Unqualified'], $detail->json('data.allowed_statuses'));
        $this->assertEquals([
            ['label' => 'New', 'value' => 'New'],
            ['label' => 'Contacted', 'value' => 'Contacted'],
            ['label' => 'Qualified', 'value' => 'Qualified'],
            ['label' => 'Unqualified', 'value' => 'Unqualified'],
        ], $detail->json('data.status_options'));

        DB::table('mm_crm_leads')->where('id', $id)->update(['status' => 'Approved']);
        $leadStatuses = $this->getJson('/api/mobile/v1/leads/'.$id.'/statuses')->assertOk();
        $this->assertEquals([
            ['label' => 'New', 'value' => 'New'],
            ['label' => 'Contacted', 'value' => 'Contacted'],
            ['label' => 'Qualified', 'value' => 'Qualified'],
            ['label' => 'Unqualified', 'value' => 'Unqualified'],
            ['label' => 'Approved', 'value' => 'Approved'],
        ], $leadStatuses->json('data'));

        $this->convertedQuotationLead(501);
        $convertedStatuses = $this->getJson('/api/mobile/v1/leads/501/statuses')->assertOk();
        $this->assertEquals([
            ['label' => 'Converted', 'value' => 'Converted'],
        ], $convertedStatuses->json('data'));

        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        $this->assertNotEmpty($lookups->json('data.lead_status_options'));
    }

    public function test_creating_a_qualified_lead_prepares_customer_and_site(): void
    {
        $result = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), [
            'status' => 'Qualified', 'company_name' => 'Qualified Company', 'project_name' => 'Qualified Site',
        ]))->assertCreated()->assertJsonPath('data.status', 'Qualified');
        $this->assertNotNull($result->json('data.customer_id'));
        $this->assertSame(2, DB::table('mm_patrons')->count());
        $this->assertSame(2, DB::table('mm_sites')->count());
        $this->assertNull($result->json('data.converted_at'));
    }

    public function test_qualification_creates_customer_and_site_and_quotation_reuses_them(): void
    {
        $this->unconvertedQuotationLead();
        $payload = array_replace($this->lead(), ['status' => 'Qualified', 'company_name' => 'New Company',
            'project_name' => 'Lead Project', 'delivery_location' => 'Site delivery', 'address' => 'Office address']);
        $result = $this->putJson('/api/mobile/v1/leads/501', $payload)->assertOk()->assertJsonPath('data.status', 'Qualified');
        $customerId = $result->json('data.customer_id');
        $this->assertNotNull($customerId);
        $this->assertNull($result->json('data.converted_at'));
        $this->assertSame(0, DB::table('mm_crm_deals')->count());
        $this->assertEquals(['Customer'], json_decode(DB::table('mm_patrons')->where('id', $customerId)->value('patron_type'), true));
        $site = DB::table('mm_sites')->where('name', 'Lead Project')->first();
        $this->assertEquals([$customerId], json_decode($site->patron_id, true));
        $this->assertEquals('Site delivery', $site->site_address_1);
        $this->putJson('/api/mobile/v1/leads/501', $payload)->assertOk();
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.site_id', fn ($id) => (int) $id === (int) $site->id);
        $this->assertSame(2, DB::table('mm_patrons')->count());
        $this->assertSame(2, DB::table('mm_sites')->count());
        $this->assertSame(1, DB::table('mm_contacts')->count());
    }

    public function test_qualification_reuses_customer_category_and_existing_site_by_name(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_patrons')->insert(['id' => 2, 'plant_id' => 1, 'legal_name' => ' new COMPANY ', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active']);
        DB::table('mm_sites')->insert(['id' => 502, 'plant_id' => 1, 'name' => ' lead PROJECT ', 'patron_id' => '[999]']);
        $this->putJson('/api/mobile/v1/leads/501', array_replace($this->lead(), [
            'status' => 'Qualified', 'company_name' => 'New Company', 'project_name' => 'Lead Project',
        ]))->assertOk()->assertJsonPath('data.customer_id', fn ($id) => (int) $id === 2);
        $this->assertSame(2, DB::table('mm_patrons')->count());
        $this->assertSame(2, DB::table('mm_sites')->count());
        $this->assertSame(0, DB::table('mm_contacts')->count());
    }

    public function test_qualification_adds_customer_category_and_ignores_foreign_or_deleted_sites(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_patrons')->insert([
            ['id' => 2, 'plant_id' => 1, 'legal_name' => 'New Company', 'patron_type' => '["Vendor"]', 'status' => 1, 'operational_status' => 'active', 'deleted_at' => null],
            ['id' => 3, 'plant_id' => 2, 'legal_name' => 'New Company', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active', 'deleted_at' => null],
            ['id' => 4, 'plant_id' => 1, 'legal_name' => 'New Company', 'patron_type' => '["Customer"]', 'status' => 1, 'operational_status' => 'active', 'deleted_at' => now()],
        ]);
        DB::table('mm_sites')->insert([
            ['plant_id' => 2, 'name' => 'Lead Project', 'deleted_at' => null], ['plant_id' => 1, 'name' => 'Lead Project', 'deleted_at' => now()],
        ]);
        $result = $this->putJson('/api/mobile/v1/leads/501', array_replace($this->lead(), [
            'status' => 'Qualified', 'company_name' => 'New Company', 'project_name' => 'Lead Project',
        ]))->assertOk();
        $this->assertSame(2, (int) $result->json('data.customer_id'));
        $this->assertEquals(['Vendor', 'Customer'], json_decode(DB::table('mm_patrons')->where('id', 2)->value('patron_type'), true));
        $this->assertSame(4, DB::table('mm_patrons')->count());
        $this->assertSame(4, DB::table('mm_sites')->count());
    }

    public function test_customer_provisioning_failure_rolls_back_lead_qualification(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_contact_types')->delete();
        $this->putJson('/api/mobile/v1/leads/501', array_replace($this->lead(), ['status' => 'Qualified']))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertEquals('New', DB::table('mm_crm_leads')->where('id', 501)->value('status'));
        $this->assertNull(DB::table('mm_crm_leads')->where('id', 501)->value('customer_id'));
        $this->assertSame(1, DB::table('mm_patrons')->count());
        $this->assertSame(1, DB::table('mm_sites')->count());
    }

    public function test_qualification_rejects_inactive_existing_customer_without_duplicates(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_patrons')->insert(['plant_id' => 1, 'legal_name' => 'New Company', 'patron_type' => '["Customer"]', 'status' => 0, 'operational_status' => 'inactive']);
        $this->putJson('/api/mobile/v1/leads/501', array_replace($this->lead(), ['status' => 'Qualified', 'company_name' => 'New Company']))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertEquals('New', DB::table('mm_crm_leads')->where('id', 501)->value('status'));
        $this->assertSame(2, DB::table('mm_patrons')->count());
        $this->assertSame(1, DB::table('mm_sites')->count());
    }

    private function enableLaravelNotifications(): void
    {
        $this->enablePushLogs();
        (require database_path('migrations/2026_10_17_150652_create_database_notifications_table.php'))->up();
        $this->app->instance('env', 'local');
        config(['push_notifications.enabled' => true, 'queue.default' => 'sync', 'broadcasting.default' => 'log']);
    }

    public function test_laravel_notification_fake_captures_queued_database_and_broadcast_channels(): void
    {
        $this->enableLaravelNotifications();
        Notification::fake();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        Notification::assertSentTo(User::find(2), BusinessActivityNotification::class,
            fn ($notification, $channels) => in_array('database', $channels, true) && (int) $notification->toArray(User::find(2))['plant_id'] === 1);
        Notification::assertSentTo(User::find(2), BusinessActivityNotification::class,
            fn ($notification, $channels) => in_array('broadcast', $channels, true));
        $this->assertEquals('pending', DB::table('mm_push_notification_logs')->value('status'));
    }

    public function test_laravel_reverb_notification_delivery_is_idempotent_and_recipient_scoped(): void
    {
        $this->enableLaravelNotifications();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $log = PushNotificationLog::where('recipient_user_id', 2)->firstOrFail();
        $this->assertEquals('sent', $log->status);
        $this->assertEquals('reverb', $log->provider);
        $this->assertSame(1, $log->attempts);
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertFalse(app(PushNotificationService::class)->dispatch($log->id));
        foreach ([1, 2] as $userId) {
            $entries = User::findOrFail($userId)->notifications;
            $this->assertCount(1, $entries);
            $this->assertEquals($userId, $entries->first()->notifiable_id);
        }
        $this->assertNull(User::findOrFail(1)->notifications()->whereKey($log->provider_message_id)->first());
    }

    public function test_laravel_notification_delivery_waits_for_commit_and_is_removed_on_rollback(): void
    {
        $this->enableLaravelNotifications();
        DB::beginTransaction();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->assertSame(0, DB::table('notifications')->count());
        DB::rollBack();
        $this->assertSame(0, DB::table('mm_push_notification_logs')->count());
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_disabled_laravel_notifications_are_not_dispatched(): void
    {
        $this->enableLaravelNotifications();
        $this->app->instance('env', 'production');
        config(['push_notifications.enabled' => false]);
        Notification::fake();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $log = PushNotificationLog::firstOrFail();
        $this->assertFalse(app(PushNotificationService::class)->dispatch($log->id));
        $this->assertEquals('pending', $log->fresh()->status);
        Notification::assertNothingSent();
        $this->artisan('push:dispatch-pending')->assertExitCode(1);
    }

    public function test_pending_command_and_standard_private_channels_enforce_user_identity(): void
    {
        $this->enableLaravelNotifications();
        config(['push_notifications.enabled' => false]);
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $log = PushNotificationLog::firstOrFail();
        config(['push_notifications.enabled' => true]);
        $this->artisan('push:dispatch-pending', ['--id' => $log->id])->assertExitCode(0);
        $this->assertSame(1, DB::table('notifications')->count());
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'dev-key',
            'broadcasting.connections.reverb.secret' => 'dev-secret', 'broadcasting.connections.reverb.app_id' => 'dev-app']);
        require base_path('routes/channels.php');
        // This isolated API fixture does not include browser presence/workspace tables.
        $this->withoutMiddleware([EnforceBrowserSession::class, SetEntityContext::class, HandleInertiaRequests::class]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.2', 'socket_id' => '123.456'])->assertOk()->assertJsonStructure(['auth']);
        $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.1', 'socket_id' => '123.456'])->assertForbidden();
        $request = Request::create('/broadcasting/auth', 'POST', [
            'channel_name' => 'private-App.Models.User.2', 'socket_id' => '123.456',
        ]);
        $request->setUserResolver(fn () => User::find(1));
        try {
            Broadcast::auth($request);
            $this->fail('Private channel authentication must reject other users.');
        } catch (AccessDeniedHttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
        }
        $request->setUserResolver(fn () => User::find(2));
        $response = Broadcast::auth($request);
        $this->assertStringContainsString('auth', is_string($response) ? $response : json_encode($response));
    }

    public function test_laravel_notification_jobs_deliver_only_when_the_queue_worker_runs(): void
    {
        $this->enableLaravelNotifications();
        (require database_path('migrations/2026_03_03_154502_create_jobs_table.php'))->up();
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'mobile_test', 'queue.connections.database.table' => 'mm_jobs']);
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->assertSame(4, DB::table('mm_jobs')->count());
        $this->assertSame(0, DB::table('notifications')->count());
        $log = PushNotificationLog::firstOrFail();
        $this->assertEquals('pending', $log->status);
        $this->assertFalse(app(PushNotificationService::class)->dispatch($log->id));
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1])->assertExitCode(0);
        $this->assertSame(0, DB::table('mm_jobs')->count());
        $this->assertSame(2, DB::table('notifications')->count());
        $this->assertEquals('sent', $log->fresh()->status);
        $this->assertSame(1, $log->fresh()->attempts);
        $notification = new BusinessActivityNotification($log->fresh());
        $notification->failed(new \RuntimeException('Test exhausted retry'));
        $this->assertEquals('failed', $log->fresh()->status);
    }

    public function test_mobile_notification_history_is_paginated_and_scoped_to_user_and_plant(): void
    {
        $this->enableLaravelNotifications();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $otherUser = User::findOrFail(2)->notifications()->firstOrFail();
        $otherPlant = User::findOrFail(1)->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => BusinessActivityNotification::class,
            'data' => ['push_log_id' => 999999, 'plant_id' => 2, 'title' => 'Other plant'],
        ]);
        $this->getJson('/api/mobile/v1/notifications?per_page=1')->assertOk()->assertJsonPath('data.total', 1)->assertJsonCount(1, 'data.data');
        $this->getJson('/api/mobile/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 1);
        $this->getJson('/api/mobile/v1/notifications?per_page=101')->assertUnprocessable();
        foreach ([$otherUser->data['push_log_id'], $otherPlant->data['push_log_id']] as $id) {
            $this->postJson('/api/mobile/v1/notifications/'.$id.'/read')->assertNotFound();
            $this->postJson('/api/mobile/v1/notifications/'.$id.'/delivered')->assertNotFound();
        }
        $own = User::findOrFail(1)->notifications()->where('data->plant_id', 1)->firstOrFail();
        $publicId = (int) $own->data['push_log_id'];
        $this->getJson('/api/mobile/v1/notifications')->assertOk()->assertJsonPath('data.data.0.id', $publicId);
        $this->postJson('/api/mobile/v1/notifications/'.$own->id.'/read')->assertNotFound();
        $this->postJson('/api/mobile/v1/notifications/'.$publicId.'/delivered')->assertOk()->assertJsonPath('data.id', $publicId);
        $log = PushNotificationLog::findOrFail($own->data['push_log_id']);
        $event = new \Illuminate\Notifications\Events\BroadcastNotificationCreated(User::findOrFail(1), new BusinessActivityNotification($log), []);
        $this->assertSame($publicId, $event->broadcastWith()['id']);
        $this->assertSame('delivered', $log->status);
        $this->postJson('/api/mobile/v1/notifications/'.$own->data['push_log_id'].'/read')->assertOk()->assertJsonPath('data.id', $publicId);
        $readAt = $log->fresh()->read_at->toISOString();
        $this->postJson('/api/mobile/v1/notifications/'.$own->data['push_log_id'].'/read')->assertOk();
        $this->postJson('/api/mobile/v1/notifications/'.$own->data['push_log_id'].'/delivered')->assertOk();
        $this->assertSame('read', $log->fresh()->status);
        $this->assertSame($readAt, $log->fresh()->read_at->toISOString());
        $this->assertSame(1, $log->auditLogs()->where('action_type', 'READ')->count());
        $this->assertSame(1, $log->auditLogs()->where('action_type', 'DELIVERED')->count());
        $this->getJson('/api/mobile/v1/notifications?unread=1')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/notifications/unread-count')->assertOk()->assertJsonPath('data.unread_count', 0);
        $this->assertNull($otherUser->fresh()->read_at);
        $this->assertNull($otherPlant->fresh()->read_at);
    }

    public function test_mobile_read_all_preserves_other_users_and_plants_and_is_repeatable(): void
    {
        $this->enableLaravelNotifications();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['phone' => '9876543298']))->assertCreated();
        $foreign = User::findOrFail(1)->notifications()->create([
            'id' => (string) Str::uuid(), 'type' => BusinessActivityNotification::class, 'data' => ['plant_id' => 2],
        ]);
        $this->postJson('/api/mobile/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 2);
        $this->postJson('/api/mobile/v1/notifications/read-all')->assertOk()->assertJsonPath('data.updated_count', 0);
        $this->assertSame(2, User::findOrFail(2)->unreadNotifications()->count());
        $this->assertNull($foreign->fresh()->read_at);
        $this->assertSame(2, PushNotificationLog::where('recipient_user_id', 1)->where('status', 'read')->count());
    }

    public function test_mobile_reverb_configuration_and_authorization_use_bearer_auth_without_exposing_secrets(): void
    {
        config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'mobile-key',
            'broadcasting.connections.reverb.secret' => 'mobile-secret', 'broadcasting.connections.reverb.app_id' => 'mobile-app',
            'broadcasting.connections.reverb.public_host' => 'ws.example.test', 'broadcasting.connections.reverb.public_port' => 443,
            'broadcasting.connections.reverb.public_scheme' => 'https']);
        require base_path('routes/channels.php');
        $response = $this->getJson('/api/mobile/v1/notifications/connection')->assertOk()
            ->assertJsonPath('data.host', 'ws.example.test')->assertJsonPath('data.channel', 'App.Models.User.1')
            ->assertJsonPath('data.port', 443)->assertJsonPath('data.configured', true);
        $this->assertStringNotContainsString('mobile-secret', $response->getContent());
        $endpoint = '/api/mobile/v1/notifications/broadcasting/auth';
        $signature = hash_hmac('sha256', '123.456:private-App.Models.User.1', 'mobile-secret');
        $this->postJson($endpoint, ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.1'])
            ->assertOk()->assertJsonPath('auth', 'mobile-key:'.$signature);
        $this->postJson($endpoint, ['socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.2'])->assertForbidden();
        $this->postJson($endpoint, ['socket_id' => 'bad', 'channel_name' => 'private-App.Models.User.1'])->assertUnprocessable();
    }

    public function test_mobile_notification_endpoints_require_sales_mobile_token_and_valid_plant(): void
    {
        $this->withHeaders(['X-Plant-Id' => ''])->getJson('/api/mobile/v1/notifications/connection')->assertUnprocessable();
        $this->withHeaders(['X-Plant-Id' => '2'])->getJson('/api/mobile/v1/notifications/connection')->assertForbidden();
        $token = User::findOrFail(1)->createToken('erp-mcp:test', ['erp-mcp'])->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Plant-Id' => '1'])->getJson('/api/mobile/v1/notifications/connection')->assertForbidden();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/notifications/connection')->assertUnauthorized();
    }

    public function test_receive_permission_controls_recipients_using_the_effective_plant_role(): void
    {
        $service = app(PushNotificationService::class);
        $permission = DB::table('mm_permissions')->where('name', PushNotificationService::RECEIVE_PERMISSION)->value('id');
        DB::table('mm_role_has_permissions')->where('role_id', 2)->where('permission_id', $permission)->delete();
        $this->assertSame([1], $service->recipients(1));
        DB::table('mm_entity_users')->insert(['user_id' => 2, 'entity_id' => 1, 'plant_id' => null, 'role_id' => 1]);
        $this->assertSame([1], $service->recipients(1));
        DB::table('mm_model_has_permissions')->insert(['model_id' => 2, 'model_type' => (new User)->getMorphClass(), 'permission_id' => $permission]);
        $this->assertEqualsCanonicalizing([1, 2], $service->recipients(1));
        DB::table('mm_model_has_permissions')->where('model_id', 2)->delete();
        DB::table('mm_entity_users')->where('user_id', 2)->where('plant_id', 1)->delete();
        $this->assertEqualsCanonicalizing([1, 2], $service->recipients(1));
        DB::table('mm_entity_users')->where('user_id', 2)->update(['deleted_at' => now()]);
        $this->assertSame([1], $service->recipients(1));
    }

    public function test_revoking_receive_permission_blocks_queued_delivery_and_mobile_subscription(): void
    {
        $this->enableLaravelNotifications();
        (require database_path('migrations/2026_03_03_154502_create_jobs_table.php'))->up();
        config(['queue.default' => 'database', 'queue.connections.database.connection' => 'mobile_test', 'queue.connections.database.table' => 'mm_jobs']);
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->assertSame(4, DB::table('mm_jobs')->count());
        $permission = DB::table('mm_permissions')->where('name', PushNotificationService::RECEIVE_PERMISSION)->value('id');
        DB::table('mm_role_has_permissions')->where('role_id', 1)->where('permission_id', $permission)->delete();
        $this->getJson('/api/mobile/v1/notifications/connection')->assertOk()->assertJsonPath('data.can_receive', false);
        $this->postJson('/api/mobile/v1/notifications/broadcasting/auth', [
            'socket_id' => '123.456', 'channel_name' => 'private-App.Models.User.1',
        ])->assertForbidden();
        $this->artisan('queue:work', ['connection' => 'database', '--stop-when-empty' => true, '--sleep' => 0, '--tries' => 1])->assertExitCode(0);
        $this->assertSame(0, User::findOrFail(1)->notifications()->count());
        $this->assertSame(1, User::findOrFail(2)->notifications()->count());
        $this->assertSame('skipped', PushNotificationLog::where('recipient_user_id', 1)->value('status'));
        // Existing history remains available; this permission controls new delivery.
        $this->getJson('/api/mobile/v1/notifications')->assertOk();
    }

    public function test_receive_permission_registration_preserves_delivery_and_can_be_rolled_back(): void
    {
        $permission = DB::table('mm_permissions')->where('name', PushNotificationService::RECEIVE_PERMISSION)->value('id');
        DB::table('mm_role_has_permissions')->where('permission_id', $permission)->delete();
        DB::table('mm_permissions')->where('id', $permission)->delete();
        $migration = require database_path('migrations/2026_10_17_174557_add_push_notification_receive_permission.php');
        $migration->up();
        $this->assertEqualsCanonicalizing([1, 2], app(PushNotificationService::class)->recipients(1));
        $migration->down();
        $this->assertSame([], app(PushNotificationService::class)->recipients(1));
        $this->assertSame(2, DB::table('mm_users')->count());
    }

    public function test_workspace_endpoints_do_not_require_module_permissions_but_keep_plant_access(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['mix-designs', 'taxes', 'sites', 'sites/1', 'customers', 'customers/1', 'dashboard', 'marketing/summary', 'lookups'] as $path) {
            $this->getJson('/api/mobile/v1/'.$path)->assertOk();
        }
        $this->getJson('/api/mobile/v1/dashboard')->assertJsonStructure(['data' => [
            'open_leads', 'followups_today', 'overdue_followups', 'active_orders', 'completed_batches', 'quotations_pending_approval',
        ]]);
        $this->getJson('/api/mobile/v1/lookups')->assertJsonStructure(['data' => [
            'mix_designs', 'sales_taxes', 'sites', 'units', 'customers', 'customer_pos', 'sales_orders', 'vehicles', 'pumps', 'drivers',
        ]]);
        $this->getJson('/api/mobile/v1/customers/1')->assertJsonStructure(['data' => ['progress']]);
        $this->getJson('/api/mobile/v1/leads')->assertForbidden();
        $this->withHeaders(['X-Plant-Id' => '2'])->getJson('/api/mobile/v1/sites')->assertForbidden();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/dashboard')->assertUnauthorized();
    }

    private function enablePushLogs(): void
    {
        (require database_path('migrations/2026_10_17_135601_create_push_notification_logs.php'))->up();
        (require database_path('migrations/2026_10_17_144450_create_push_notification_audit_logs.php'))->up();
        config(['push_notifications.enabled' => false]);
    }

    public function test_lead_approval_requires_ownership_and_repeat_is_idempotent(): void
    {
        $this->enablePushLogs();
        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertForbidden();
        $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['phone' => '9876543298', 'status' => 'Approved']))->assertUnprocessable();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertNotFound();
        DB::table('mm_crm_leads')->where('id', $id)->update(['assigned_to' => 2]);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertOk()->assertJsonPath('data.status', 'Approved');
        $this->assertNotNull(DB::table('mm_crm_leads')->where('id', $id)->value('customer_id'));
        $this->assertSame(2, DB::table('mm_patrons')->count());
        $this->assertSame(2, DB::table('mm_sites')->count());
        $count = DB::table('mm_push_notification_logs')->count();
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertOk();
        $this->assertSame($count, DB::table('mm_push_notification_logs')->count());
        $this->assertSame(1, DB::table('mm_crm_activities')->where('subject', 'Lead approved')->count());
        DB::table('mm_crm_leads')->where('id', $id)->update(['plant_id' => 2]);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertNotFound();
        DB::table('mm_crm_leads')->where('id', $id)->update(['plant_id' => 1, 'status' => 'Unqualified']);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertUnprocessable();
        DB::table('mm_crm_leads')->where('id', $id)->update(['status' => 'Converted', 'converted_at' => now()]);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertUnprocessable();
        $this->withHeaders(['Authorization' => '']);
        $this->postJson('/api/mobile/v1/leads/'.$id.'/approve')->assertUnauthorized();
    }

    public function test_lead_creation_logs_each_active_plant_user_once_without_recipient_input(): void
    {
        $this->enablePushLogs();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $logs = DB::table('mm_push_notification_logs')->get();
        $this->assertEqualsCanonicalizing([1, 2], $logs->pluck('recipient_user_id')->all());
        $this->assertSame(2, $logs->count());
        foreach ($logs as $log) {
            $this->assertEquals(1, $log->actor_id);
            $this->assertEquals('pending', $log->status);
            $this->assertNull($log->sent_at);
        }
    }

    public function test_all_four_models_log_mutations_and_transaction_rollback_removes_logs(): void
    {
        $this->enablePushLogs();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        foreach ([CrmLead::class, Quotation::class, SalesOrder::class, CustomerPO::class] as $class) {
            if ($class === CrmLead::class) {
                $model = $class::firstOrFail();
            } else {
                $model = $class::create(['plant_id' => 1, 'status' => 0, 'amount_untaxed' => 0, 'amount_tax' => 0, 'amount_total' => 0]);
            }
            $type = $model->getMorphClass();
            $model->update(['status' => $class === CrmLead::class ? 'Contacted' : 1]);
            $model->delete();
            $model->restore();
            $operations = DB::table('mm_push_notification_logs')->where('subject_type', $type)->pluck('operation')->all();
            foreach (['created', 'updated', 'deleted', 'restored'] as $operation) {
                $this->assertContains($operation, $operations);
            }
            $count = DB::table('mm_push_notification_logs')->count();
            DB::beginTransaction();
            $model->update(['status' => $class === CrmLead::class ? 'Qualified' : 2]);
            $this->assertGreaterThan($count, DB::table('mm_push_notification_logs')->count());
            DB::rollBack();
            $this->assertSame($count, DB::table('mm_push_notification_logs')->count());
        }
    }

    public function test_logs_are_retained_when_the_plant_has_no_eligible_users(): void
    {
        $this->enablePushLogs();
        $lead = new CrmLead(['plant_id' => 999, 'lead_number' => 'No-members-test']);
        $lead->id = 999;
        app(PushNotificationService::class)->record($lead, 'created');
        $this->assertSame(1, DB::table('mm_push_notification_logs')->count());
        $this->assertEquals('skipped', DB::table('mm_push_notification_logs')->value('status'));
    }

    public function test_business_notifications_reach_all_active_plant_users_without_recipient_configuration(): void
    {
        $this->enablePushLogs();
        foreach ([3, 4, 5] as $id) {
            DB::table('mm_users')->insert(['id' => $id, 'username' => 'Recipient '.$id, 'is_active' => $id === 4 ? 0 : 1]);
            DB::table('mm_entity_users')->insert(['user_id' => $id, 'entity_id' => $id === 5 ? 2 : 1, 'plant_id' => $id === 5 ? 2 : 1, 'role_id' => 1]);
        }
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->assertEqualsCanonicalizing([1, 2, 3], DB::table('mm_push_notification_logs')->pluck('recipient_user_id')->all());
    }

    public function test_notification_logs_track_audit_users_soft_delete_and_audit_history(): void
    {
        $this->enablePushLogs();
        Schema::create('mm_inventory_audit_logs', function (Blueprint $table) {
            $table->id();
            foreach (['plant_id', 'transaction_type', 'action_type', 'reference_type', 'reference_id', 'log_from', 'log_to', 'user_id', 'remarks', 'ip_address'] as $column) {
                $table->text($column)->nullable();
            }
            $table->timestamp('created_at')->nullable();
        });
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $log = PushNotificationLog::firstOrFail();
        $this->assertSame(1, $log->created_by);
        $this->assertSame(1, $log->updated_by);
        $log->update(['status' => 'sent', 'provider' => 'future-provider', 'provider_message_id' => 'message-123',
            'last_attempt_at' => now(), 'sent_at' => now(), 'delivered_at' => now(), 'read_at' => now()]);
        $this->assertInstanceOf(Carbon::class, $log->read_at);
        $this->assertSame(1, DB::table('mm_push_notification_audit_logs')->where('push_notification_log_id', $log->id)->where('action_type', 'SENT')->count());
        $this->assertSame(0, DB::table('mm_inventory_audit_logs')->where('reference_type', 'PushNotificationLog')->count());
        $log->delete();
        $this->assertNull(PushNotificationLog::find($log->id));
        $deleted = PushNotificationLog::withTrashed()->findOrFail($log->id);
        $this->assertSame(1, $deleted->deleted_by);
        $this->assertNotNull($deleted->deleted_at);
        $this->assertSame(1, DB::table('mm_push_notification_audit_logs')->where('push_notification_log_id', $log->id)->where('action_type', 'DELETED')->count());
        $deleted->restore();
        $this->assertNotNull(PushNotificationLog::find($log->id));
        $this->assertEquals(['QUEUED', 'SENT', 'DELETED', 'RESTORED'], $deleted->auditLogs()->pluck('action_type')->all());
    }

    public function test_push_history_tracks_attempts_failure_retry_delivery_and_read_with_rollback(): void
    {
        $this->enablePushLogs();
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $log = PushNotificationLog::firstOrFail();
        $log->update(['status' => 'processing', 'attempts' => 1, 'last_attempt_at' => now()]);
        $log->update(['status' => 'failed', 'error' => 'Temporary failure', 'provider' => 'test', 'provider_response' => ['code' => 503]]);
        $failed = $log->auditLogs()->where('action_type', 'FAILED')->firstOrFail();
        $this->assertSame('processing', $failed->status_from);
        $this->assertSame('failed', $failed->status_to);
        $this->assertSame(['code' => 503], $failed->provider_response);
        $this->assertSame(1, $failed->attempt_number);
        $log->update(['status' => 'pending', 'next_attempt_at' => now()->addMinute()]);
        $retry = $log->auditLogs()->where('action_type', 'QUEUED')->reorder('id', 'desc')->firstOrFail();
        $this->assertSame('failed', $retry->status_from);
        $log->update(['status' => 'sent', 'attempts' => 2, 'error' => null, 'provider_message_id' => 'provider-123']);
        $log->update(['status' => 'delivered', 'delivered_at' => now()]);
        $log->update(['status' => 'read', 'read_at' => now()]);
        $this->assertEquals(['QUEUED', 'ATTEMPTED', 'FAILED', 'QUEUED', 'SENT', 'DELIVERED', 'READ'], $log->auditLogs()->pluck('action_type')->all());
        $count = $log->auditLogs()->count();
        DB::beginTransaction();
        $log->update(['status' => 'failed']);
        $this->assertSame($count + 1, $log->auditLogs()->count());
        DB::rollBack();
        $this->assertSame($count, $log->auditLogs()->count());
        $log->delete();
        $this->assertTrue($log->auditLogs()->firstOrFail()->notification->trashed());
        $historyCount = $log->auditLogs()->count();
        (require database_path('migrations/2026_10_17_144450_create_push_notification_audit_logs.php'))->up();
        $this->assertSame($historyCount, $log->auditLogs()->count());
    }

    public function test_existing_notification_logs_upgrade_without_data_loss(): void
    {
        Schema::create('mm_push_notification_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plant_id');
            $table->string('status');
            $table->timestamps();
        });
        DB::table('mm_push_notification_logs')->insert(['plant_id' => 1, 'status' => 'pending']);
        $migration = require database_path('migrations/2026_10_17_143900_add_audit_columns_to_push_notification_logs.php');
        $migration->up();
        $migration->up();
        foreach (['created_by', 'updated_by', 'deleted_by', 'deleted_at', 'provider', 'provider_message_id', 'last_attempt_at', 'next_attempt_at', 'delivered_at', 'read_at'] as $column) {
            $this->assertTrue(Schema::hasColumn('mm_push_notification_logs', $column));
        }
        $this->assertSame(1, DB::table('mm_push_notification_logs')->count());
        $this->assertEquals('pending', DB::table('mm_push_notification_logs')->value('status'));
    }

    public function test_approved_lead_can_be_converted_and_configuration_resolves_personnel_users(): void
    {
        $this->enablePushLogs();
        $this->unconvertedQuotationLead();
        DB::table('mm_custom_settings')->insert(['plant_id' => 1, 'module_name' => 'push_notifications', 'settings' => json_encode(['personnel_ids' => [1, 2]])]);
        DB::table('mm_crm_leads')->where('id', 501)->update(['status' => 'Approved']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $this->assertEqualsCanonicalizing([1, 2], DB::table('mm_push_notification_logs')->where('subject_type', Quotation::class)->where('operation', 'created')->pluck('recipient_user_id')->all());
    }

    private function lead(): array
    {
        return ['contact_name' => 'Arun', 'phone' => '9876543210', 'email' => null, 'enquiry_date' => '2026-10-09', 'source' => 'Referral', 'priority' => 'High', 'status' => 'New', 'expected_value' => 10000];
    }

    private function seedExecutiveVisibilityRecords(): void
    {
        DB::table('mm_patrons')->insert([
            ['id' => 2, 'plant_id' => 1, 'sales_executive_id' => 2, 'legal_name' => 'Other customer', 'patron_type' => '["Customer"]', 'operational_status' => 'active'],
            ['id' => 3, 'plant_id' => 1, 'sales_executive_id' => 1, 'legal_name' => 'New assigned customer', 'patron_type' => '["Customer"]', 'operational_status' => 'active'],
        ]);
        DB::table('mm_sites')->insert([
            ['id' => 2, 'plant_id' => 1, 'patron_id' => 2, 'name' => 'Other site'],
            ['id' => 3, 'plant_id' => 1, 'patron_id' => 3, 'name' => 'New assigned site'],
        ]);
        foreach ([
            [101, 1, 2, 1, null], // Assigned to me, created by someone else.
            [102, 1, 1, 2, null], // Created by me, assigned to someone else.
            [103, 1, 1, null, null],
            [104, 2, 1, 1, null],
            [105, 1, 1, 1, '2026-10-14 00:00:00'],
        ] as [$id, $plant, $creator, $executive, $deleted]) {
            $base = ['id' => $id, 'plant_id' => $plant, 'created_by' => $creator,
                'sales_executive_id' => $executive, 'site_id' => $id === 102 ? 2 : 1, 'deleted_at' => $deleted];
            $customer = $id === 102 ? 2 : 1;
            DB::table('mm_sales_orders')->insert($base + ['customer_id' => $customer, 'mix_design_id' => 1, 'status' => 1, 'total_qty' => 10]);
            DB::table('mm_quotations')->insert($base + ['patron_id' => $customer, 'reference' => 'QUOTE-'.$id, 'status' => 1, 'mobile_approval_state' => 'pending']);
            DB::table('mm_customer_pos')->insert($base + ['patron_id' => $customer, 'reference' => 'PO-'.$id]);
        }
        foreach ([101 => 1, 102 => 2] as $id => $owner) {
            DB::table('mm_crm_leads')->insert(['id' => $id, 'plant_id' => 1, 'assigned_to' => $owner,
                'customer_id' => $owner, 'contact_name' => 'Lead '.$id, 'source' => 'Referral', 'status' => 'New']);
            DB::table('mm_crm_activities')->insert(['id' => $id, 'plant_id' => 1, 'lead_id' => $id,
                'type' => 'Site Visit', 'due_at' => now()]);
            DB::table('mm_concrete_batching_schedules')->insert(['id' => $id, 'plant_id' => 1,
                'sales_order_id' => $id, 'site_id' => $owner, 'mix_design_id' => 1, 'status' => 'scheduled']);
        }
    }

    public function test_executive_visibility_is_consistent_across_lists_details_lookups_and_summaries(): void
    {
        $this->seedExecutiveVisibilityRecords();
        foreach (['CRM_LEAD', 'QUOTATION', 'SALES_ORDER', 'PATRON', 'SITE'] as $module) {
            $name = $module.'.VIEW_ALL';
            $id = DB::table('mm_permissions')->where('name', $name)->value('id')
                ?? DB::table('mm_permissions')->insertGetId(['name' => $name, 'guard_name' => 'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $id]);
        }
        DB::table('mm_role_has_permissions')->where('role_id', 1)->whereIn('permission_id',
            DB::table('mm_permissions')->where('name', 'BATCHING_SCHEDULE.VIEW')->select('id'))->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['leads', 'quotations', 'orders', 'customer-pos', 'batch-schedules', 'follow-ups'] as $resource) {
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', 1);
            $this->assertEquals([101], array_column($response->json('data.data'), 'id'));
        }
        foreach (['customers', 'sites'] as $resource) {
            $expected = [1, 3];
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', count($expected));
            $this->assertEqualsCanonicalizing($expected, array_column($response->json('data.data'), 'id'));
            $this->getJson('/api/mobile/v1/'.$resource.'/2')->assertNotFound();
        }
        foreach (['leads', 'quotations', 'orders', 'batch-schedules'] as $resource) {
            $this->getJson('/api/mobile/v1/'.$resource.'/102')->assertNotFound();
        }
        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        foreach (['customers', 'sites'] as $key) {
            $this->assertEqualsCanonicalizing([1, 3], array_column($lookups->json('data.'.$key), 'id'));
        }
        foreach (['customer_pos', 'sales_orders'] as $key) {
            $this->assertEquals([101], array_column($lookups->json('data.'.$key), 'id'));
        }
        $this->getJson('/api/mobile/v1/dashboard')->assertOk()
            ->assertJsonPath('data.open_leads', 1)->assertJsonPath('data.active_orders', 1)
            ->assertJsonPath('data.quotations_pending_approval', 1)->assertJsonPath('data.followups_today', 1);
        $this->getJson('/api/mobile/v1/marketing/summary')->assertOk()
            ->assertJsonPath('data.by_source.0.total', 1)->assertJsonPath('data.site_visits', 1);
    }

    public function test_followups_and_nested_lead_details_exclude_other_executives_assignments(): void
    {
        $this->travelTo(now()->setTime(9, 0));
        $this->seedExecutiveVisibilityRecords();
        DB::table('mm_crm_activities')->insert([
            ['id' => 201, 'plant_id' => 1, 'lead_id' => 101, 'assigned_to' => 2, 'type' => 'Site Visit', 'subject' => 'Other executive task', 'due_at' => now()->subDay()],
            ['id' => 202, 'plant_id' => 1, 'lead_id' => 101, 'assigned_to' => 1, 'type' => 'Call', 'subject' => 'My task', 'due_at' => now()->addHour()],
            ['id' => 203, 'plant_id' => 1, 'lead_id' => 102, 'assigned_to' => 1, 'type' => 'Call', 'subject' => 'Foreign lead task', 'due_at' => now()],
        ]);
        $response = $this->getJson('/api/mobile/v1/follow-ups?view=all')->assertOk();
        $this->assertEqualsCanonicalizing([101, 202], array_column($response->json('data.data'), 'id'));
        $lead = $this->getJson('/api/mobile/v1/leads/101')->assertOk();
        $this->assertEqualsCanonicalizing([101, 202], array_column($lead->json('data.activities'), 'id'));
        $this->putJson('/api/mobile/v1/follow-ups/201', [])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/201/complete', [])->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/201')->assertNotFound();
        $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.overdue_followups', 0);
        $this->getJson('/api/mobile/v1/marketing/summary')->assertOk()->assertJsonPath('data.site_visits', 1);
    }

    public function test_managers_and_administrators_are_also_restricted_to_their_own_mobile_records(): void
    {
        $this->seedExecutiveVisibilityRecords();
        foreach (['SALES_MANAGER', 'ADMINISTRATOR', 'SAAS_OWNER'] as $code) {
            DB::table('mm_roles')->where('id', 2)->update(['code' => $code, 'name' => $code === 'SAAS_OWNER' ? 'Saas Owner' : 'Mobile '.$code]);
            if ($code === 'SAAS_OWNER') {
                User::find(2)->assignRole(Role::find(2));
            }
            app(PermissionRegistrar::class)->forgetCachedPermissions();
            $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer, 'X-Plant-Id' => '1']);
            foreach (['leads', 'quotations', 'orders', 'customer-pos', 'batch-schedules', 'follow-ups'] as $resource) {
                $result = $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', 1);
                $this->assertEquals([102], array_column($result->json('data.data'), 'id'), $code.' '.$resource);
            }
            foreach (['leads', 'quotations', 'orders', 'batch-schedules', 'customers'] as $resource) {
                $this->getJson('/api/mobile/v1/'.$resource.'/'.(in_array($resource, ['customers', 'sites']) ? 1 : 101))->assertNotFound();
            }
            $this->postJson('/api/mobile/v1/quotations/101/review', ['decision' => 'approved'])->assertNotFound();
            $this->postJson('/api/mobile/v1/leads', $this->lead() + ['assigned_to' => 1])->assertForbidden();
            $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.active_orders', 1);
            $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
            $this->assertEquals([102], array_column($lookups->json('data.customer_pos'), 'id'));
            $this->assertEquals([102], array_column($lookups->json('data.sales_orders'), 'id'));
        }
    }

    public function test_executive_writes_reject_inaccessible_foreign_keys_and_records(): void
    {
        $this->seedExecutiveVisibilityRecords();
        $order = ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'total_qty' => 1, 'rate' => 100];
        $this->postJson('/api/mobile/v1/orders', array_replace($order, ['customer_id' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->postJson('/api/mobile/v1/orders', array_replace($order, ['site_id' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $this->postJson('/api/mobile/v1/orders', $order + ['customer_po_id' => 102])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_po_id');
        $this->postJson('/api/mobile/v1/quotations', array_replace($this->quote(), ['patron_id' => 2, 'site_id' => 2]))
            ->assertUnprocessable()->assertJsonValidationErrors(['patron_id']);
        $this->postJson('/api/mobile/v1/batch-schedules', ['site_id' => 1, 'mix_design_id' => 1, 'sales_order_id' => 102])
            ->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $this->postJson('/api/mobile/v1/leads', $this->lead() + ['assigned_to' => 2])->assertForbidden();
        foreach (['leads', 'quotations', 'orders', 'batch-schedules'] as $resource) {
            $this->putJson('/api/mobile/v1/'.$resource.'/102', [])->assertNotFound();
            $this->deleteJson('/api/mobile/v1/'.$resource.'/102')->assertNotFound();
        }
        $this->postJson('/api/mobile/v1/quotations/102/submit')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/102/review', ['decision' => 'approved'])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/102/complete')->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/102')->assertNotFound();
    }

    public function test_direct_customer_assignment_allows_first_sale_and_controls_owner(): void
    {
        $this->seedExecutiveVisibilityRecords();
        $this->postJson('/api/mobile/v1/quotations', array_replace($this->quote(), [
            'patron_id' => 3, 'site_id' => 3, 'sales_executive_id' => 2,
        ]))->assertCreated()->assertJsonPath('data.sales_executive_id', 1);
        $this->postJson('/api/mobile/v1/orders', ['customer_id' => 3, 'site_id' => 3, 'mix_design_id' => 1,
            'total_qty' => 1, 'rate' => 100, 'sales_executive_id' => 2])->assertCreated()
            ->assertJsonPath('data.sales_executive_id', 1);
    }

    public function test_customer_and_site_access_inherits_only_visible_business_relationships(): void
    {
        foreach (range(11, 16) as $id) {
            DB::table('mm_patrons')->insert(['id' => $id, 'plant_id' => 1, 'sales_executive_id' => 2,
                'legal_name' => 'Related '.$id, 'patron_type' => '["Customer"]', 'operational_status' => 'active']);
            DB::table('mm_sites')->insert(['id' => $id, 'plant_id' => 1, 'patron_id' => $id, 'name' => 'Related '.$id]);
        }
        DB::table('mm_crm_leads')->insert(['plant_id' => 1, 'assigned_to' => 1, 'customer_id' => 11]);
        DB::table('mm_quotations')->insert(['plant_id' => 1, 'created_by' => 2, 'sales_executive_id' => 1, 'patron_id' => 12, 'site_id' => 12]);
        DB::table('mm_customer_pos')->insert(['plant_id' => 1, 'created_by' => 2, 'sales_executive_id' => 1, 'patron_id' => 13, 'site_id' => 13]);
        DB::table('mm_sales_orders')->insert([
            ['plant_id' => 1, 'sales_executive_id' => 1, 'customer_id' => 14, 'site_id' => 14, 'deleted_at' => null],
            ['plant_id' => 1, 'sales_executive_id' => 1, 'customer_id' => 15, 'site_id' => 15, 'deleted_at' => '2026-10-14 00:00:00'],
            ['plant_id' => 2, 'sales_executive_id' => 1, 'customer_id' => 16, 'site_id' => 16, 'deleted_at' => null],
        ]);
        foreach (['customers', 'sites'] as $resource) {
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk();
            $this->assertEqualsCanonicalizing([1, 11, 12, 13, 14], array_column($response->json('data.data'), 'id'));
            foreach ([15, 16] as $id) {
                $this->getJson('/api/mobile/v1/'.$resource.'/'.$id)->assertNotFound();
            }
        }
    }

    public function test_missing_personnel_cannot_read_unassigned_sales_or_create_invisible_records(): void
    {
        $this->seedExecutiveVisibilityRecords();
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        foreach (['quotations', 'orders', 'customer-pos', 'batch-schedules'] as $resource) {
            $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', 0);
        }
        $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertForbidden();
        $this->postJson('/api/mobile/v1/orders', ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1,
            'total_qty' => 1, 'rate' => 100])->assertForbidden();
        $this->assertSame(5, DB::table('mm_sales_orders')->count());
    }

    public function test_personnel_and_queries_follow_the_selected_plant_across_requests(): void
    {
        $this->seedExecutiveVisibilityRecords();
        DB::table('mm_plants')->insert(['id' => 3, 'name' => 'Second plant', 'entity_id' => 1, 'is_active' => 1]);
        DB::table('mm_entity_users')->insert(['user_id' => 1, 'entity_id' => 1, 'plant_id' => 3, 'role_id' => 1]);
        DB::table('mm_personnels')->insert(['id' => 31, 'plant_id' => 3, 'user_id' => 1]);
        DB::table('mm_sales_orders')->insert([
            ['id' => 301, 'plant_id' => 3, 'created_by' => 2, 'sales_executive_id' => 31],
            ['id' => 302, 'plant_id' => 3, 'created_by' => 1, 'sales_executive_id' => 1],
        ]);
        $this->getJson('/api/mobile/v1/orders')->assertOk()->assertJsonPath('data.data.0.id', 101);
        $this->withHeaders(['X-Plant-Id' => '3'])->getJson('/api/mobile/v1/orders')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 301);
        $this->withHeaders(['X-Plant-Id' => '1'])->getJson('/api/mobile/v1/orders')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 101);
    }

    public function test_entity_wide_sales_membership_only_exposes_assigned_personnel_plant(): void
    {
        DB::table('mm_plants')->insert(['id' => 3, 'name' => 'Unassigned plant', 'entity_id' => 1, 'is_active' => 1]);
        DB::table('mm_entity_users')->where('user_id', 1)->update(['plant_id' => null]);
        $this->getJson('/api/mobile/v1/plants')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', 1);
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])
            ->assertOk()->assertJsonCount(1, 'data.plants')->assertJsonPath('data.plants.0.id', 1);
        $this->withHeaders(['X-Plant-Id' => '3'])->getJson('/api/mobile/v1/quotations')->assertForbidden();
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        $this->getJson('/api/mobile/v1/plants')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_entity_administrator_can_list_entity_plants_without_personnel_assignment(): void
    {
        DB::table('mm_plants')->insert(['id' => 3, 'name' => 'Another entity plant', 'entity_id' => 1, 'is_active' => 1]);
        DB::table('mm_roles')->where('id', 1)->update(['code' => 'ADMINISTRATOR']);
        DB::table('mm_entity_users')->where('user_id', 1)->update(['plant_id' => null]);
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        $this->getJson('/api/mobile/v1/plants')->assertOk()->assertJsonCount(2, 'data');
        $this->withHeaders(['X-Plant-Id' => '2'])->getJson('/api/mobile/v1/quotations')->assertForbidden();
    }

    public function test_route_group_declares_sales_scope_once_after_plant_context(): void
    {
        $plantMiddleware = MobilePlantContext::class;
        $scopeMiddleware = MobileSalesScope::class;
        $scopedRoutes = 0;
        foreach ($this->mobileEndpoints() as [$method, $url, $middleware]) {
            if (! in_array($plantMiddleware, $middleware, true)) {
                $this->assertNotContains($scopeMiddleware, $middleware, $url);

                continue;
            }
            if (str_starts_with($url, '/api/mobile/v1/notifications') || str_starts_with($url, '/api/mobile/v1/attendances')) {
                $this->assertNotContains($scopeMiddleware, $middleware, $url);

                continue;
            }
            $this->assertSame(1, count(array_keys($middleware, $scopeMiddleware, true)), $url);
            $this->assertLessThan(array_search($scopeMiddleware, $middleware, true),
                array_search($plantMiddleware, $middleware, true), $url);
            $scopedRoutes++;
        }
        $this->assertGreaterThan(30, $scopedRoutes);
    }

    public function test_route_middleware_prepares_sales_context_once_per_request(): void
    {
        Route::get('/testing/sales-mobile-scope', function (Request $request) {
            return ['executive' => $request->attributes->get('mobile_sales_executive'),
                'personnel_loaded' => $request->attributes->has('mobile_personnel:1:1'),
                'fresh_queries' => MobileAccess::orders() !== MobileAccess::orders()];
        })->middleware(['auth:sanctum', MobilePlantContext::class,
            MobileSalesScope::class]);
        $this->getJson('/testing/sales-mobile-scope')->assertOk()
            ->assertExactJson(['executive' => true, 'personnel_loaded' => true, 'fresh_queries' => true]);
    }

    private function mobileEndpoints(): array
    {
        $endpoints = [];
        foreach (app('router')->getRoutes() as $route) {
            if (! str_starts_with($route->uri(), 'api/mobile/')) {
                continue;
            }
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $endpoints[] = [$method, '/'.preg_replace('/\{([^}]+)\}/', '999', $route->uri()), $route->gatherMiddleware()];
            }
        }

        return $endpoints;
    }

    public function test_legacy_public_auth_success_validation_inactive_and_otp_replay(): void
    {
        $this->withHeaders(['Authorization' => '']);
        $this->getJson('/api/mobile/status')->assertOk()->assertJsonPath('success', true);
        $this->postJson('/api/mobile/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password']);
        $this->postJson('/api/mobile/login', ['email' => 'user1@example.test', 'password' => 'wrong'])->assertUnauthorized();
        $this->postJson('/api/mobile/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])->assertOk()->assertJsonPath('2fa_required', true);
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0]);
        $this->postJson('/api/mobile/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])->assertOk()->assertJsonStructure(['token', 'user']);
        $this->postJson('/api/mobile/send-otp', [])->assertUnprocessable();
        $this->postJson('/api/mobile/send-otp', ['email' => 'missing@example.test'])->assertUnprocessable();
        $this->postJson('/api/mobile/send-otp', ['email' => 'user1@example.test'])->assertOk()->assertJsonPath('expires_in', 300);
        Cache::put('email_otp:user1@example.test', Hash::make('123456'), 300);
        $this->postJson('/api/mobile/verify-otp', ['email' => 'user1@example.test', 'otp' => '000000'])->assertUnprocessable();
        $this->postJson('/api/mobile/verify-otp', ['email' => 'user1@example.test', 'otp' => '123456'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/mobile/verify-otp', ['email' => 'user1@example.test', 'otp' => '123456'])->assertUnprocessable();
        DB::table('mm_users')->where('id', 1)->update(['is_active' => 0]);
        Cache::flush();
        $this->postJson('/api/mobile/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])->assertForbidden();
        $this->postJson('/api/mobile/send-otp', ['email' => 'user1@example.test'])->assertForbidden();
        Cache::put('email_otp:user1@example.test', Hash::make('123456'), 300);
        $this->postJson('/api/mobile/verify-otp', ['email' => 'user1@example.test', 'otp' => '123456'])->assertForbidden();
    }

    public function test_legacy_reporting_routes_delegate_filters_and_return_empty_results(): void
    {
        $methods = ['sales-summary' => 'getSalesSummary', 'sales-details' => 'getSalesDetails',
            'customer-details' => 'getCustomerDetails', 'top-mix-designs' => 'getTopMixDesignsFromBatches',
            'truck-dispatch-details' => 'getDispatchDetailsByTruck', 'dispatch-details' => 'getDispatchDetails',
            'dispatch-batching-summary' => 'getDispatchBatchingSummary', 'stock-details' => 'getStockDetails'];
        $service = \Mockery::mock(DashboardService::class);
        $filters = ['from_date' => '2026-10-01', 'to_date' => '2026-10-14', 'plant_id' => '1', 'type' => 'Cash'];
        foreach ($methods as $path => $method) {
            $service->shouldReceive($method)->with($filters)->once()->andReturn([]);
        }
        $service->shouldReceive('getSalesSummary')->with([])->once()->andReturn(['total' => 100]);
        $service->shouldReceive('getStockDetails')->with([])->once()->andReturn([]);
        $this->app->instance(DashboardService::class, $service);
        foreach ($methods as $path => $method) {
            $this->getJson('/api/mobile/'.$path.'?'.http_build_query($filters + ['ignored' => 'value']))
                ->assertOk()->assertExactJson(['success' => true, 'data' => []]);
        }
        $this->getJson('/api/mobile/dashboard')->assertOk()->assertJsonPath('data.sales_summary.total', 100);
        $this->getJson('/api/mobile/user')->assertOk()->assertJsonPath('data.id', 1)->assertJsonMissingPath('data.password');
    }

    public function test_public_otp_route_throttles_and_malformed_payloads(): void
    {
        foreach (['/api/mobile/send-otp' => 6, '/api/mobile/verify-otp' => 10,
            '/api/mobile/v1/auth/send-otp' => 5, '/api/mobile/v1/auth/verify-otp' => 10, '/api/mobile/v1/auth/login' => 5] as $url => $limit) {
            Cache::flush();
            for ($attempt = 0; $attempt < $limit; $attempt++) {
                $this->postJson($url, [])->assertUnprocessable();
            }
            $this->postJson($url, [])->assertStatus(429);
        }
    }

    public function test_legacy_reporting_invalid_filters_and_server_errors_are_safe(): void
    {
        $service = \Mockery::mock(DashboardService::class);
        $this->app->instance(DashboardService::class, $service);
        foreach (['dashboard', 'sales-summary', 'sales-details', 'customer-details', 'top-mix-designs',
            'truck-dispatch-details', 'dispatch-details', 'dispatch-batching-summary', 'stock-details'] as $path) {
            $this->getJson('/api/mobile/'.$path.'?from_date=invalid&plant_id=-1')->assertUnprocessable()
                ->assertJsonValidationErrors(['from_date', 'plant_id'])->assertJsonMissingPath('trace');
            $this->getJson('/api/mobile/'.$path.'?from_date=2026-10-14&to_date=2026-10-01')->assertUnprocessable()
                ->assertJsonValidationErrors('to_date');
        }
        config(['app.debug' => true]);
        $service->shouldReceive('getSalesSummary')->once()->andThrow(new \RuntimeException('Private database details'));
        $this->getJson('/api/mobile/sales-summary')->assertStatus(500)->assertExactJson(['message' => 'Server error.']);
    }

    public function test_mobile_user_management_valid_crud_and_invalid_input(): void
    {
        Notification::fake();
        $this->makeReviewerSystemAdmin();
        $this->getJson('/api/mobile/users')->assertOk()->assertJsonPath('success', true);
        $body = ['username' => 'Mobile Test', 'email' => 'mobile-new@example.test', 'password' => 'Strong-password-123', 'is_active' => true, 'is_otp_enabled' => false];
        $id = $this->postJson('/api/mobile/users', $body)->assertOk()->json('user.id');
        $this->getJson('/api/mobile/users/'.$id)->assertOk()->assertJsonPath('email', $body['email']);
        $this->putJson('/api/mobile/users/'.$id, array_replace($body, ['username' => 'Updated']))->assertOk();
        $this->postJson('/api/mobile/users', $body)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/mobile/users/'.$id, [])->assertUnprocessable();
        $this->deleteJson('/api/mobile/users/'.$id)->assertOk();
        $this->deleteJson('/api/mobile/users/'.$id)->assertNotFound();
        $this->getJson('/api/mobile/users/999')->assertNotFound();
    }

    public function test_v1_foreign_and_unowned_resources_cannot_be_read_changed_or_deleted(): void
    {
        foreach ([['leads', 'mm_crm_leads'], ['quotations', 'mm_quotations'], ['orders', 'mm_sales_orders']] as [$resource,$table]) {
            foreach ([[900, 2, 1], [901, 1, 2]] as [$id,$plant,$owner]) {
                $data = ['id' => $id, 'plant_id' => $plant, 'created_by' => $owner];
                if ($resource === 'leads') {
                    $data['assigned_to'] = $owner;
                }
                DB::table($table)->insert($data);
                $url = '/api/mobile/v1/'.$resource.'/'.$id;
                $this->getJson($url)->assertNotFound();
                $this->putJson($url, $resource === 'leads' ? $this->lead() : ($resource === 'quotations' ? $this->quote() : []))->assertNotFound();
                $this->deleteJson($url)->assertNotFound();
            }
        }
    }

    public function test_every_protected_mobile_route_rejects_missing_or_invalid_tokens(): void
    {
        $checked = 0;
        foreach ($this->mobileEndpoints() as [$method,$url,$middleware]) {
            if (! in_array('auth:sanctum', $middleware)) {
                continue;
            }
            foreach (['', 'Bearer invalid-token'] as $authorization) {
                $this->withHeaders(['Authorization' => $authorization])->json($method, $url, [])->assertUnauthorized();
            }
            $checked++;
        }
        $this->assertGreaterThan(50, $checked);
    }

    public function test_every_plant_scoped_route_rejects_missing_and_foreign_plant(): void
    {
        foreach ($this->mobileEndpoints() as [$method,$url,$middleware]) {
            if (! in_array(MobilePlantContext::class, $middleware)) {
                continue;
            }
            $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'X-Plant-Id' => ''])->json($method, $url, [])
                ->assertUnprocessable()->assertJsonValidationErrors('plant_id');
            $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'X-Plant-Id' => '2'])->json($method, $url, [])->assertForbidden();
        }
    }

    public function test_v1_listing_boundaries_and_successful_empty_results(): void
    {
        foreach (['leads', 'follow-ups', 'quotations', 'orders', 'customer-pos', 'batch-schedules', 'customers', 'sites', 'mix-designs', 'taxes'] as $path) {
            $url = '/api/mobile/v1/'.$path;
            $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('data.per_page', 1);
            $this->getJson($url.'?per_page=100')->assertOk()->assertJsonPath('data.per_page', 100);
            foreach (['0', '101', 'abc'] as $value) {
                $this->getJson($url.'?per_page='.$value)->assertUnprocessable()->assertJsonValidationErrors('per_page');
            }
        }
        foreach (['leads', 'quotations', 'orders', 'customer-pos', 'customers', 'sites', 'mix-designs', 'taxes'] as $path) {
            $this->getJson('/api/mobile/v1/'.$path.'?search=nonexistent')->assertOk()->assertJsonPath('data.total', 0);
        }
    }

    public function test_v1_resource_crud_conversion_and_followup_lifecycle(): void
    {
        $permission = DB::table('mm_permissions')->insertGetId(['name' => 'CRM_LEAD.CONVERT', 'guard_name' => 'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $permission]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        if (! Schema::hasTable('mm_crm_deals')) {
            Schema::create('mm_crm_deals', function (Blueprint $t) {
                $t->id();
                foreach (['plant_id', 'lead_id', 'customer_id', 'name', 'stage', 'expected_value', 'expected_close_date', 'created_by', 'updated_by', 'deleted_by'] as $field) {
                    $t->text($field)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        }
        foreach (['customer_id', 'name', 'expected_close_date'] as $field) {
            if (! Schema::hasColumn('mm_crm_deals', $field)) {
                Schema::table('mm_crm_deals', fn (Blueprint $t) => $t->text($field)->nullable());
            }
        }
        $lead = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/leads/'.$lead)->assertOk();
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert', ['customer_id' => 1])->assertUnprocessable();
        $this->putJson('/api/mobile/v1/leads/'.$lead, array_replace($this->lead(), ['status' => 'Qualified']))->assertOk();
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert', ['customer_id' => 1])->assertOk()->assertJsonPath('data.status', 'Converted');
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert', ['customer_id' => 1])->assertUnprocessable();
        $activity = ['lead_id' => $lead, 'type' => 'Call', 'subject' => 'Follow up', 'due_at' => '2026-10-15 10:00:00'];
        $id = $this->postJson('/api/mobile/v1/follow-ups', $activity)->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/follow-ups/'.$id, $activity + ['description' => 'Updated'])->assertOk();
        $this->postJson('/api/mobile/v1/follow-ups/'.$id.'/complete')->assertOk();
        $this->postJson('/api/mobile/v1/follow-ups/'.$id.'/complete')->assertOk();
        $this->putJson('/api/mobile/v1/follow-ups/'.$id, $activity)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/follow-ups/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/customers/1')->assertOk();
        $this->getJson('/api/mobile/v1/sites/1')->assertOk();
        $quote = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/quotations/'.$quote)->assertOk();
        $this->deleteJson('/api/mobile/v1/quotations/'.$quote)->assertOk();
        $body = ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'total_qty' => 1, 'rate' => 100];
        $order = $this->postJson('/api/mobile/v1/orders', $body)->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/orders/'.$order)->assertOk();
        $this->getJson('/api/mobile/v1/orders')->assertOk();
        $this->deleteJson('/api/mobile/v1/orders/'.$order)->assertOk();
        $this->deleteJson('/api/mobile/v1/leads/'.$lead)->assertUnprocessable();
        $draftLead = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['phone' => '9876543299']))->assertCreated()->json('data.id');
        $this->deleteJson('/api/mobile/v1/leads/'.$draftLead)->assertOk();
    }

    public function test_v1_missing_records_and_invalid_create_payloads(): void
    {
        foreach (['leads', 'quotations', 'orders', 'batch-schedules', 'customers', 'sites'] as $resource) {
            $this->getJson('/api/mobile/v1/'.$resource.'/999')->assertNotFound();
            $this->getJson('/api/mobile/v1/'.$resource.'/abc')->assertNotFound();
        }
        foreach (['leads', 'quotations', 'orders', 'batch-schedules'] as $resource) {
            $this->postJson('/api/mobile/v1/'.$resource, [])->assertUnprocessable();
            $this->putJson('/api/mobile/v1/'.$resource.'/999', [])->assertNotFound();
            $this->deleteJson('/api/mobile/v1/'.$resource.'/999')->assertNotFound();
        }
        $this->postJson('/api/mobile/v1/follow-ups', [])->assertUnprocessable();
        $this->putJson('/api/mobile/v1/follow-ups/999', [])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/999/complete')->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/999')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/999/submit')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/999/review', ['decision' => 'approved'])->assertNotFound();
        $this->postJson('/api/mobile/v1/leads/999/convert', [])->assertForbidden();
    }

    public function test_customer_po_listing_scopes_filters_and_permissions(): void
    {
        foreach ([
            [1, 1, 1, null, null], [2, 1, 2, 1, null], [3, 1, 2, 2, null],
            [4, 2, 1, 1, null], [5, 1, 1, 1, '2026-10-14 00:00:00'],
        ] as [$id,$plant,$creator,$executive,$deleted]) {
            DB::table('mm_customer_pos')->insert(['id' => $id, 'plant_id' => $plant, 'created_by' => $creator,
                'sales_executive_id' => $executive, 'patron_id' => 1, 'site_id' => 1, 'reference' => 'CPO-'.$id,
                'customer_po_reference' => 'CUSTOMER-'.$id, 'status' => 0, 'amount_untaxed' => 100, 'amount_tax' => 18,
                'amount_total' => 118, 'deleted_at' => $deleted]);
        }
        DB::table('mm_customer_po_items')->insert(['customer_po_id' => 2, 'mix_design_id' => 1, 'quantity' => 10, 'rate' => 10]);
        $listing = $this->getJson('/api/mobile/v1/customer-pos?per_page=1')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 2)
            ->assertJsonPath('data.data.0.patron.legal_name', 'Customer')->assertJsonPath('data.data.0.site.name', 'Site')
            ->assertJsonCount(1, 'data.data.0.items');
        $this->assertEqualsCanonicalizing(['id', 'reference', 'customer_po_reference', 'patron_id', 'site_id', 'order_date',
            'status', 'is_tax_inclusive', 'amount_untaxed', 'amount_tax', 'amount_total', 'patron', 'site', 'items'],
            array_keys($listing->json('data.data.0')));
        $this->assertEqualsCanonicalizing(['id', 'mix_design_id', 'quantity', 'rate', 'tax_id', 'concrete_pump', 'pump_rate',
            'untaxed_amount', 'tax_amount', 'amount_total'], array_keys($listing->json('data.data.0.items.0')));
        $this->getJson('/api/mobile/v1/customer-pos?search=CUSTOMER-2&customer_id=1&site_id=1&status=0')
            ->assertOk()->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 2);
        $this->getJson('/api/mobile/v1/customer-pos?search=CPO-3')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/customer-pos?status=1')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/customer-pos?customer_id=2')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/customer-pos?per_page=101&status=3')->assertUnprocessable();
        DB::table('mm_permissions')->insert(['id' => 100, 'name' => 'SALES_ORDER.VIEW_ALL', 'guard_name' => 'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id' => 1, 'permission_id' => 100]);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/mobile/v1/customer-pos')->assertOk()->assertJsonPath('data.total', 1);
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/mobile/v1/customer-pos')->assertOk()->assertJsonPath('data.total', 1);
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/customer-pos')->assertUnauthorized();
    }

    public function test_dispatches_without_module_permissions_are_paged_and_scoped(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([[1, 1, 1], [2, 1, 2], [3, 2, 1]] as [$id,$plant,$creator]) {
            DB::table('mm_sales_orders')->insert(['id' => $id, 'plant_id' => $plant, 'created_by' => $creator, 'sales_executive_id' => $creator]);
        }
        DB::table('mm_machines')->insert(['id' => 1, 'plant_id' => 1, 'registration' => 'TN-TEST']);
        DB::table('mm_batches')->insert([
            ['id' => 1, 'plant_id' => 1, 'sales_order_id' => 1, 'batch_no' => 1, 'batch_size' => 5, 'deleted_at' => null],
            ['id' => 2, 'plant_id' => 1, 'sales_order_id' => 1, 'batch_no' => 2, 'batch_size' => 5, 'deleted_at' => '2026-10-14 00:00:00'],
        ]);
        foreach ([[1, 1, 1, 1, null], [2, 1, 1, null, null], [3, 1, 1, 2, null], [4, 2, 1, null, null], [5, 1, 2, null, null], [6, 1, 1, null, '2026-10-14 00:00:00']] as [$id,$plant,$order,$batch,$deleted]) {
            DB::table('mm_dispatches')->insert(['id' => $id, 'plant_id' => $plant, 'sales_order_id' => $order,
                'batch_id' => $batch, 'truck_id' => 1, 'dispatch_time' => '2026-10-14 10:00:00', 'dispatch_no' => $id,
                'delivered_qty' => 5, 'dispatch_status' => 'Delivered', 'deleted_at' => $deleted]);
        }
        $response = $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=1')->assertOk()
            ->assertJsonPath('data.total', 2)->assertJsonPath('data.data.0.id', 1)
            ->assertJsonPath('data.data.0.truck.registration', 'TN-TEST')->assertJsonPath('data.data.0.batch.id', 1);
        $this->assertEqualsCanonicalizing(['id', 'sales_order_id', 'batch_id', 'truck_id', 'prefix', 'dispatch_no',
            'dispatch_time', 'delivery_time', 'delivered_qty', 'dispatch_status', 'truck', 'batch'], array_keys($response->json('data.data.0')));
        $this->assertEqualsCanonicalizing(['id', 'batch_no', 'batch_size'], array_keys($response->json('data.data.0.batch')));
        $this->assertEqualsCanonicalizing(['id', 'registration'], array_keys($response->json('data.data.0.truck')));
        $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.data.0.id', 2)->assertJsonPath('data.data.0.batch', null);
        foreach ([2, 3, 999] as $order) {
            $this->getJson('/api/mobile/v1/orders/'.$order.'/dispatches')->assertNotFound();
        }
        $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=101')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number/dispatches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=abc')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=3&per_page=1')->assertOk()->assertJsonCount(0, 'data.data');
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/orders/1/dispatches')->assertUnauthorized();
    }

    public function test_batches_without_module_permissions_are_paged_and_scoped(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([[1, 1, 1], [2, 1, 2], [3, 2, 1]] as [$id,$plant,$creator]) {
            DB::table('mm_sales_orders')->insert(['id' => $id, 'plant_id' => $plant, 'created_by' => $creator, 'sales_executive_id' => $creator]);
        }
        foreach ([[1, 1, 1, 2, null], [2, 1, 1, 1, null], [3, 1, 1, 3, '2026-10-14 00:00:00'], [4, 2, 1, 4, null], [5, 1, 2, 5, null]] as [$id,$plant,$order,$number,$deleted]) {
            DB::table('mm_batches')->insert(['id' => $id, 'plant_id' => $plant, 'sales_order_id' => $order,
                'batch_no' => $number, 'batch_size' => 5, 'status' => 1, 'deleted_at' => $deleted]);
        }
        $response = $this->getJson('/api/mobile/v1/orders/1/batches?per_page=1')->assertOk()
            ->assertJsonPath('success', true)->assertJsonPath('data.total', 2)->assertJsonPath('data.data.0.id', 2);
        $this->assertEqualsCanonicalizing(['id', 'batch_no', 'sales_order_id', 'batch_size', 'status', 'start_time', 'end_time'],
            array_keys($response->json('data.data.0')));
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=1&page=2')->assertOk()->assertJsonPath('data.data.0.id', 1);
        $this->getJson('/api/mobile/v1/orders/2/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/3/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/999/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=101')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=abc')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=3&per_page=1')->assertOk()->assertJsonCount(0, 'data.data');
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/orders/1/batches')->assertUnauthorized();
    }

    private function quote(): array
    {
        return ['reference' => 'QT-TEST-1', 'patron_id' => 1, 'site_id' => 1, 'quote_date' => '2026-10-09', 'is_tax_inclusive' => true, 'items' => [['mix_design_id' => 1, 'quantity' => 3.55, 'rate' => 546, 'tax_id' => 1]]];
    }

    public function test_order_update_with_customer_po_uses_matching_line_and_validates_missing_lines(): void
    {
        DB::table('mm_customer_pos')->insert(['id' => 58, 'plant_id' => 1, 'patron_id' => 1, 'site_id' => 1, 'created_by' => 1, 'sales_executive_id' => 1, 'is_tax_inclusive' => 1]);
        DB::table('mm_customer_po_items')->insert(['customer_po_id' => 58, 'mix_design_id' => 1, 'rate' => 4500, 'tax_id' => 1, 'concrete_pump' => 'pump', 'pump_rate' => 100]);
        DB::table('mm_mix_designs')->insert(['id' => 23, 'plant_id' => 1, 'design_name' => 'Other mix', 'is_active' => 1]);
        DB::table('mm_sales_orders')->insert(['id' => 1, 'plant_id' => 1, 'created_by' => 1, 'sales_executive_id' => 1, 'customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'total_qty' => 10, 'rate' => 250, 'status' => 1]);
        $body = ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'customer_po_id' => 58, 'total_qty' => 50, 'rate' => 1];
        $this->putJson('/api/mobile/v1/orders/1', $body)->assertOk()->assertJsonPath('data.rate', 4500)
            ->assertJsonPath('data.tax_id', 1)->assertJsonPath('data.is_tax_inclusive', true)->assertJsonPath('data.concrete_pump', 'pump');
        $this->assertEquals(58, DB::table('mm_sales_orders')->where('id', 1)->value('customer_po_id'));
        $this->putJson('/api/mobile/v1/orders/1', array_replace($body, ['mix_design_id' => 23]))
            ->assertUnprocessable()->assertJsonValidationErrors(['mix_design_id']);
        $this->assertEquals(1, DB::table('mm_sales_orders')->where('id', 1)->value('mix_design_id'));
        DB::table('mm_customer_po_items')->where('customer_po_id', 58)->update(['deleted_at' => '2026-10-14 00:00:00']);
        $this->putJson('/api/mobile/v1/orders/1', $body)->assertUnprocessable()->assertJsonValidationErrors(['mix_design_id']);
        $this->putJson('/api/mobile/v1/orders/1', array_replace($body, ['customer_po_id' => null, 'mix_design_id' => 23]))
            ->assertOk()->assertJsonPath('data.customer_po_id', null)->assertJsonPath('data.rate', 1);
    }

    public function test_orders_can_be_created_and_updated_without_customer_po(): void
    {
        $body = ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'total_qty' => 10, 'rate' => 4500];
        $id = $this->postJson('/api/mobile/v1/orders', $body)->assertCreated()
            ->assertJsonPath('data.customer_po_id', null)->assertJsonPath('data.rate', 4500)->json('data.id');
        $this->putJson('/api/mobile/v1/orders/'.$id, array_replace($body, ['rate' => 5000]))
            ->assertOk()->assertJsonPath('data.customer_po_id', null)->assertJsonPath('data.rate', 5000);
        $this->postJson('/api/mobile/v1/orders', $body + ['customer_po_id' => null])->assertCreated();
        $missingRate = $body;
        unset($missingRate['rate']);
        $this->postJson('/api/mobile/v1/orders', $missingRate)->assertUnprocessable()->assertJsonValidationErrors(['rate'])
            ->assertJsonMissingValidationErrors(['customer_po_id']);
        $this->postJson('/api/mobile/v1/orders', $body + ['customer_po_id' => 999])->assertUnprocessable()->assertJsonValidationErrors(['customer_po_id']);
    }

    public function test_quotation_status_and_approval_state_stay_in_sync(): void
    {
        $id = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()
            ->assertJsonPath('data.mobile_approval_state', 'draft')->json('data.id');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk()
            ->assertJsonPath('data.mobile_approval_state', 'pending');
        $this->assertEquals(1, DB::table('mm_quotations')->where('id', $id)->value('status'));
        DB::table('mm_quotations')->where('id', $id)->update(['sales_executive_id' => 2]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review', ['decision' => 'rejected', 'comment' => 'Revise'])->assertOk()
            ->assertJsonPath('data.mobile_approval_state', 'rejected');
        $this->assertEquals(3, DB::table('mm_quotations')->where('id', $id)->value('status'));
        DB::table('mm_quotations')->where('id', $id)->update(['sales_executive_id' => 1]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token]);
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertOk()->assertJsonPath('data.mobile_approval_state', 'draft');
        $this->assertEquals(0, DB::table('mm_quotations')->where('id', $id)->value('status'));
        DB::table('mm_quotations')->where('id', $id)->update(['status' => 2, 'mobile_approval_state' => 'draft']);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk()->assertJsonPath('data.mobile_approval_state', 'approved');
        $this->getJson('/api/mobile/v1/quotations?approval_state=approved')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/v1/quotations?approval_state=draft')->assertOk()->assertJsonPath('data.total', 0);
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/quotations/'.$id)->assertUnprocessable();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertUnprocessable();
    }

    public function test_quotation_actions_without_permissions_and_concise_mobile_errors(): void
    {
        config(['app.debug' => true]);
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $id = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/quotations')->assertOk();
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertOk();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk();
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertUnprocessable()
            ->assertExactJson(['message' => 'Submitted or approved quotations cannot be edited or deleted.']);
        Route::get('/api/mobile/v1/testing-server-error', fn () => throw new \RuntimeException('Private database details'));
        $this->getJson('/api/mobile/v1/testing-server-error')->assertStatus(500)->assertExactJson(['message' => 'Server error.']);
        $this->postJson('/api/mobile/v1/auth/login', [])->assertUnprocessable()->assertJsonValidationErrors(['email', 'password'])
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception')->assertJsonMissingPath('file');
    }

    public function test_login_requires_email_and_password_without_issuing_tokens(): void
    {
        $tokensBefore = DB::table('personal_access_tokens')->count();
        $this->withHeaders(['Authorization' => ''])->postJson('/api/mobile/v1/auth/login', [])
            ->assertUnprocessable()->assertExactJson([
                'message' => 'The email field is required. (and 1 more error)',
                'errors' => [
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.'],
                ],
            ]);
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user1@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password'])->assertJsonMissingValidationErrors(['email']);
        $this->postJson('/api/mobile/v1/auth/login', ['password' => 'Test-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email'])->assertJsonMissingValidationErrors(['password']);
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'invalid-email', 'password' => 'Test-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertSame($tokensBefore, DB::table('personal_access_tokens')->count());
        Http::assertNothingSent();
    }

    public function test_login_profile_logout_and_token_revocation(): void
    {
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user1@example.test', 'password' => 'wrong'])->assertUnauthorized();
        $data = $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])->assertOk()->json('data');
        Http::assertNothingSent();
        $this->assertArrayNotHasKey('password', $data['user']);
        $this->assertArrayNotHasKey('api_key', $data['user']);
        $this->withHeaders(['Authorization' => 'Bearer '.$data['token']])->getJson('/api/mobile/v1/me')->assertOk()->assertJsonPath('data.id', 1);
        $this->postJson('/api/mobile/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/mobile/v1/me')->assertUnauthorized();
    }

    public function test_plant_membership_required_and_missing_header_rejected(): void
    {
        $this->withHeaders(['X-Plant-Id' => '2'])->getJson('/api/mobile/v1/leads')->assertForbidden();
        $this->withHeaders(['X-Plant-Id' => ''])->getJson('/api/mobile/v1/leads')->assertUnprocessable();
        $this->withHeaders(['X-Plant-Id' => '1'])->getJson('/api/mobile/v1/leads')->assertOk();
    }

    public function test_superadmin_can_login_and_access_active_plants_in_suspended_entities(): void
    {
        DB::table('mm_entities')->where('id', 1)->update(['is_suspended' => 1]);
        DB::table('mm_roles')->insert(['id' => 3, 'name' => 'Saas Owner', 'code' => 'SAAS_OWNER', 'guard_name' => 'web']);
        DB::table('mm_model_has_roles')->insert(['role_id' => 3, 'model_id' => 2, 'model_type' => User::class]);
        DB::table('mm_entity_users')->where('user_id', 2)->delete();
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user2@example.test', 'password' => 'Test-password-123'])->assertOk()->assertJsonCount(2, 'data.plants');
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer, 'X-Plant-Id' => '1'])->getJson('/api/mobile/v1/dashboard')->assertOk();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token, 'X-Plant-Id' => '1'])->getJson('/api/mobile/v1/dashboard')->assertForbidden();
        DB::table('mm_plants')->where('id', 1)->update(['is_active' => 0]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer, 'X-Plant-Id' => '1'])->getJson('/api/mobile/v1/dashboard')->assertForbidden();
    }

    public function test_crm_crud_followup_completion_and_ownership(): void
    {
        $id = $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/leads/'.$id, array_replace($this->lead(), ['status' => 'Contacted']))->assertOk()->assertJsonPath('data.status', 'Contacted');
        $activity = $this->postJson('/api/mobile/v1/follow-ups', ['lead_id' => $id, 'type' => 'Site Visit', 'subject' => 'Visit', 'due_at' => '2026-10-10 10:00:00'])->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/follow-ups/'.$activity.'/complete')->assertOk();
        $this->getJson('/api/mobile/v1/follow-ups?view=completed')->assertOk()->assertJsonPath('data.total', 1);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer])->getJson('/api/mobile/v1/leads/'.$id)->assertNotFound();
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->deleteJson('/api/mobile/v1/leads/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertNotFound();
    }

    public function test_context_permission_denial_does_not_use_another_plant_role(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        $this->getJson('/api/mobile/v1/leads')->assertForbidden();
    }

    public function test_tax_components_totals_and_separate_approval_user(): void
    {
        $id = $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertCreated()->assertJsonPath('data.amount_total', '1938.00')->json('data.id');
        $this->assertEquals(295.68, DB::table('mm_quotation_items')->where('quotation_id', $id)->value('tax_amount'));
        $this->assertEquals(-0.31, DB::table('mm_quotations')->where('id', $id)->value('adjustment'));
        $this->putJson('/api/mobile/v1/quotations/'.$id, array_replace($this->quote(), ['notes' => 'Updated']))->assertOk();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk()->assertJsonPath('data.mobile_approval_state', 'pending');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review', ['decision' => 'approved'])->assertForbidden();
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertUnprocessable();
        DB::table('mm_quotations')->where('id', $id)->update(['sales_executive_id' => 2]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer])->postJson('/api/mobile/v1/quotations/'.$id.'/review', ['decision' => 'approved'])->assertOk()->assertJsonPath('data.mobile_approval_state', 'approved');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review', ['decision' => 'approved'])->assertUnprocessable();
    }

    public function test_wrong_plant_and_purchase_taxes_cannot_be_selected(): void
    {
        DB::table('mm_taxes')->where('id', 1)->update(['tax_type' => 'Purchase']);
        $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
        DB::table('mm_taxes')->where('id', 1)->update(['tax_type' => 'Sales', 'plant_id' => 2]);
        $this->postJson('/api/mobile/v1/quotations', $this->quote())->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
    }

    public function test_mobile_whatsapp_otp_is_passwordless_and_single_use(): void
    {
        $tokens = DB::table('personal_access_tokens')->count();
        $challenge = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '+91 9876543210'])->assertStatus(202)->assertJsonPath('data.channel', 'whatsapp')->assertJsonMissingPath('data.token')->json('data.challenge_id');
        $this->assertSame($tokens, DB::table('personal_access_tokens')->count());
        Http::assertSent(fn ($r) => $r['Contact'] === '919876543210' && $r['Template'] === 'sales_executive_login' && preg_match('/^[0-9]{6}$/', $r['Param']));
        $entry = Cache::get('sales-mobile:'.$challenge);
        $entry['otp'] = Hash::make('123456');
        Cache::put('sales-mobile:'.$challenge, $entry, 300);
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '111111'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertOk()->assertJsonPath('data.user.id', 1);
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertUnprocessable();
    }

    public function test_expired_otp_and_attempt_limit_cannot_issue_tokens(): void
    {
        $tokens = DB::table('personal_access_tokens')->count();
        $challenge = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry = Cache::get('sales-mobile:'.$challenge);
        $entry['otp'] = Hash::make('123456');
        Cache::put('sales-mobile:'.$challenge, $entry, 300);
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '111111'])->assertUnprocessable();
        }
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertStatus(429);
        $fresh = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->json('data.challenge_id');
        $this->travel(6)->minutes();
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $fresh, 'otp' => '123456'])->assertUnprocessable();
        $this->travelBack();
        $this->assertSame($tokens, DB::table('personal_access_tokens')->count());
    }

    public function test_whatsapp_failure_and_duplicate_mobile_do_not_authenticate(): void
    {
        $tokens = DB::table('personal_access_tokens')->count();
        Http::swap(new Factory);
        Http::fake(['https://app.tendigit.in/*' => Http::response(['status' => 'failed', 'message' => 'Invalid template'], 200)]);
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(503)->assertJsonMissingPath('data.token');
        DB::table('mm_users')->where('id', 2)->update(['mobile' => '+91 9876543210']);
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertUnprocessable();
        $this->assertSame($tokens, DB::table('personal_access_tokens')->count());
    }

    public function test_successful_whatsapp_response_with_false_error_flag_is_accepted(): void
    {
        Http::swap(new Factory);
        Http::fake(['https://app.tendigit.in/*' => Http::response(['error' => false, 'status' => 'success', 'message' => 'Message submitted'], 200)]);
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->assertJsonPath('data.channel', 'whatsapp');
    }

    public function test_whatsapp_certificate_failure_returns_safe_specific_error(): void
    {
        Http::swap(new Factory);
        Http::fake(fn () => throw new ConnectionException('cURL error 60: certificate subject name does not match'));
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(503)
            ->assertJsonPath('error_code', 'WHATSAPP_TLS_ERROR')->assertJsonMissingPath('exception')->assertJsonMissingPath('trace')->assertJsonMissingPath('data.challenge_id');
    }

    public function test_swagger_page_and_openapi_spec_are_available_without_login(): void
    {
        $this->get('/api/mobile/v1/docs')->assertOk()->assertSee('SwaggerUIBundle', false)->assertSee('X-Plant-Id');
        $spec = $this->getJson('/api/mobile/v1/openapi.json')->assertOk()->json();
        $this->assertSame('3.0.3', $spec['openapi']);
        $this->assertSame([], $spec['paths']['/auth/send-otp']['post']['security']);
        $this->assertArrayHasKey('202', $spec['paths']['/auth/send-otp']['post']['responses']);
        $this->assertArrayHasKey('bearerAuth', $spec['components']['securitySchemes']);
        $this->assertArrayHasKey('/quotations/{quotation}/review', $spec['paths']);
    }

    public function test_changed_or_inactive_mobile_account_cannot_verify(): void
    {
        $challenge = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry = Cache::get('sales-mobile:'.$challenge);
        $entry['otp'] = Hash::make('123456');
        Cache::put('sales-mobile:'.$challenge, $entry, 300);
        DB::table('mm_users')->where('id', 1)->update(['mobile' => '9876543219']);
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertUnprocessable();
        DB::table('mm_users')->where('id', 1)->update(['is_active' => 0]);
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543219'])->assertForbidden();
    }

    public function test_production_progress_excludes_draft_cancelled_and_deleted_trips(): void
    {
        DB::table('mm_sales_orders')->insert(['id' => 1, 'plant_id' => 1, 'created_by' => 1, 'sales_executive_id' => 1, 'total_qty' => 40]);
        foreach ([1 => 1, 2 => 4, 3 => 5] as $id => $status) {
            DB::table('mm_batches')->insert(['id' => $id, 'plant_id' => 1, 'sales_order_id' => 1, 'batch_size' => 6, 'status' => $status]);
        }
        foreach ([1 => 'Draft', 2 => 'Delivered', 3 => 'Cancelled'] as $id => $status) {
            DB::table('mm_dispatches')->insert(['id' => $id, 'plant_id' => 1, 'sales_order_id' => 1, 'batch_id' => $id, 'delivered_qty' => 6, 'dispatch_status' => $status]);
        }
        $this->getJson('/api/mobile/v1/orders/1')->assertOk()->assertJsonPath('data.progress.completed_batches', 1)->assertJsonPath('data.progress.committed_m3', 12)->assertJsonPath('data.progress.delivered_m3', 6)->assertJsonPath('data.progress.remaining_delivery_m3', 34);
        DB::table('mm_batches')->where('id', 2)->update(['deleted_at' => now()]);
        $this->getJson('/api/mobile/v1/orders/1')->assertOk()->assertJsonPath('data.progress.delivered_m3', 0);
    }

    public function test_dashboard_customers_and_order_mutation_guards(): void
    {
        foreach (['SALES_ORDER.CREATE', 'SALES_ORDER.UPDATE', 'SALES_ORDER.DELETE'] as $name) {
            $id = DB::table('mm_permissions')->insertGetId(['name' => $name, 'guard_name' => 'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id' => 1, 'permission_id' => $id]);
        }
        DB::table('mm_customer_pos')->insert(['id' => 1, 'plant_id' => 1, 'patron_id' => 1, 'site_id' => 1, 'created_by' => 1, 'sales_executive_id' => 1, 'is_tax_inclusive' => 1]);
        DB::table('mm_customer_po_items')->insert(['customer_po_id' => 1, 'mix_design_id' => 1, 'quantity' => 40, 'rate' => 546, 'tax_id' => 1, 'pump_rate' => 0]);
        $body = ['customer_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'customer_po_id' => 1, 'total_qty' => 40];
        $id = $this->postJson('/api/mobile/v1/orders', $body + ['rate' => 1])->assertCreated()->json('data.id');
        $this->assertEquals(546, DB::table('mm_sales_orders')->where('id', $id)->value('rate'));
        $this->putJson('/api/mobile/v1/orders/'.$id, $body)->assertOk();
        $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.active_orders', 1);
        $this->getJson('/api/mobile/v1/customers')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/v1/customers/1')->assertOk()->assertJsonPath('data.progress.remaining_delivery_m3', 40);
        DB::table('mm_batches')->insert(['plant_id' => 1, 'sales_order_id' => $id, 'status' => 1, 'batch_size' => 6]);
        $this->putJson('/api/mobile/v1/orders/'.$id, $body)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/orders/'.$id)->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number')->assertNotFound();
    }

    public function test_schedule_rejects_wrong_order_mix_and_linked_edits(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id', 1)->delete();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('mm_sales_orders')->insert(['id' => 1, 'plant_id' => 1, 'created_by' => 1, 'sales_executive_id' => 1, 'site_id' => 1, 'mix_design_id' => 1, 'total_qty' => 40, 'status' => 1]);
        DB::table('mm_mix_designs')->insert(['id' => 2, 'plant_id' => 1, 'design_name' => 'M30', 'is_active' => 1]);
        $body = ['sales_order_id' => 1, 'site_id' => 1, 'mix_design_id' => 2, 'qty_m3' => 6, 'order_volume_m3' => 40, 'schedule_date' => '2026-10-10', 'pump_type' => 'direct_pour'];
        $this->postJson('/api/mobile/v1/batch-schedules', $body)->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $body['mix_design_id'] = 1;
        $schedule = $this->postJson('/api/mobile/v1/batch-schedules', $body)->assertOk()->assertJsonPath('schedule.remaining_volume_m3', '34.000')->json('schedule.id');
        $this->getJson('/api/mobile/v1/batch-schedules')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertOk()->assertJsonPath('data.id', $schedule);
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule, $body)->assertOk();
        $this->postJson('/api/mobile/v1/batch-schedules', array_replace($body, ['qty_m3' => 35]))->assertUnprocessable()->assertJsonValidationErrors('qty_m3');
        DB::table('mm_concrete_batching_schedules')->where('id', $schedule)->update(['batch_id' => 1]);
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule, $body)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertUnprocessable();
        DB::table('mm_concrete_batching_schedules')->where('id', $schedule)->update(['batch_id' => null]);
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertOk();
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertNotFound()
            ->assertExactJson(['message' => 'Batch schedule not found.']);
    }

    public function test_missing_and_inaccessible_schedules_return_concise_errors(): void
    {
        config(['app.debug' => true]);
        DB::table('mm_sales_orders')->insert([
            ['id' => 1, 'plant_id' => 1, 'created_by' => 2], ['id' => 2, 'plant_id' => 2, 'created_by' => 1],
        ]);
        DB::table('mm_concrete_batching_schedules')->insert([
            ['id' => 1, 'plant_id' => 1, 'sales_order_id' => 1, 'status' => 'scheduled'],
            ['id' => 2, 'plant_id' => 2, 'sales_order_id' => 2, 'status' => 'scheduled'],
        ]);
        foreach ([1, 2, 999] as $id) {
            $url = '/api/mobile/v1/batch-schedules/'.$id;
            $this->getJson($url)->assertNotFound()->assertExactJson(['message' => 'Batch schedule not found.']);
            $this->putJson($url, [])->assertNotFound()->assertExactJson(['message' => 'Batch schedule not found.']);
            $this->deleteJson($url)->assertNotFound()->assertExactJson(['message' => 'Batch schedule not found.']);
        }
        $this->assertEquals(2, DB::table('mm_concrete_batching_schedules')->whereNull('deleted_at')->count());
    }

    public function test_approval_migration_and_route_compilation(): void
    {
        Schema::table('mm_quotations', fn (Blueprint $t) => $t->dropColumn(['mobile_approval_state', 'mobile_submitted_by', 'mobile_submitted_at', 'mobile_reviewed_by', 'mobile_reviewed_at', 'mobile_approval_comment']));
        $migration = require base_path('database/migrations/2026_10_09_180500_add_sales_mobile_quotation_approval.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('mm_quotations', 'mobile_approval_state'));
        $this->assertTrue(DB::table('mm_permissions')->where('name', 'CRM_LEAD.VIEW_ALL')->exists());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('mm_quotations', 'mobile_approval_state'));
        $routes = new RouteCollection;
        foreach (app('router')->getRoutes() as $route) {
            if (str_starts_with($route->uri(), 'api/mobile/v1/')) {
                $routes->add($route);
            }
        }
        $this->assertIsArray($routes->compile());
    }

    public function test_dropdowns_marketing_and_revoked_membership(): void
    {
        foreach (['TCS', 'CESS'] as $index => $group) {
            DB::table('mm_taxes')->insert(['id' => 900 + $index, 'plant_id' => 1, 'tax_name' => $group, 'tax_group' => $group, 'tax_type' => 'Sales', 'tax_rate' => 1, 'parent_id' => null, 'status' => 1]);
            DB::table('mm_taxes')->insert(['id' => 910 + $index, 'plant_id' => 1, 'tax_name' => $group.' child', 'tax_group' => $group, 'tax_type' => 'Sales', 'tax_rate' => 1, 'parent_id' => 1, 'status' => 1]);
        }
        $this->getJson('/api/mobile/v1/lookups')->assertOk()->assertJsonCount(1, 'data.sales_taxes')->assertJsonCount(2, 'data.sales_taxes.0.children');
        $this->getJson('/api/mobile/v1/taxes')->assertOk()->assertJsonCount(1, 'data.data')->assertJsonCount(2, 'data.data.0.children');
        DB::table('mm_role_has_permissions')->where('role_id', 1)->whereIn('permission_id', DB::table('mm_permissions')->where('name', 'BATCHING_SCHEDULE.VIEW')->select('id'))->delete();
        $this->getJson('/api/mobile/v1/lookups')->assertOk()->assertJsonPath('data.sales_taxes.0.tax_type', 'Sales')->assertJsonPath('data.mix_designs.0.design_name', 'M25');
        $this->postJson('/api/mobile/v1/leads', $this->lead())->assertCreated();
        $this->getJson('/api/mobile/v1/marketing/summary')->assertOk()->assertJsonPath('data.by_source.0.total', 1);
        DB::table('mm_entity_users')->where('user_id', 1)->update(['deleted_at' => now()]);
        $this->getJson('/api/mobile/v1/dashboard')->assertForbidden();
    }

    private function makeReviewerSystemAdmin(): void
    {
        DB::table('mm_roles')->insert(['id' => 3, 'name' => 'Saas Owner', 'code' => 'SAAS_OWNER', 'guard_name' => 'web']);
        DB::table('mm_model_has_roles')->insert(['role_id' => 3, 'model_id' => 2, 'model_type' => User::class]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
    }

    public function test_admin_can_grant_by_email_and_revoke_mobile_sessions_without_changing_roles(): void
    {
        $webToken = User::find(1)->createToken('web-login', ['*']);
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0]);
        $this->postJson('/api/mobile/v1/auth/login', ['email' => 'user1@example.test', 'password' => 'Test-password-123'])->assertForbidden();
        $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertForbidden();
        $this->makeReviewerSystemAdmin();
        $this->putJson('/testing/mobile-access', ['email' => 'USER1@example.test', 'enabled' => true])->assertOk()->assertJsonPath('data.is_otp_enabled', true);
        $this->assertSame('1', (string) DB::table('mm_entity_users')->where('user_id', 1)->value('role_id'));
        $this->putJson('/testing/mobile-access', ['email' => 'user1@example.test', 'enabled' => false])->assertOk()->assertJsonPath('data.is_otp_enabled', false);
        $this->assertEquals(0, User::find(1)->tokens()->where('name', 'like', 'sales-mobile:%')->count());
        $this->assertTrue(DB::table('personal_access_tokens')->where('id', $webToken->accessToken->id)->exists());
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->getJson('/api/mobile/v1/me')->assertUnauthorized();
    }

    public function test_revoked_mobile_access_invalidates_requests_and_pending_otp_even_after_regrant(): void
    {
        $challenge = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry = Cache::get('sales-mobile:'.$challenge);
        $entry['otp'] = Hash::make('123456');
        Cache::put('sales-mobile:'.$challenge, $entry, 300);
        $this->makeReviewerSystemAdmin();
        $this->putJson('/testing/mobile-access', ['email' => 'user1@example.test', 'enabled' => false])->assertOk();
        $this->putJson('/testing/mobile-access', ['email' => 'user1@example.test', 'enabled' => true])->assertOk();
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertForbidden();
        DB::table('mm_users')->where('id', 2)->update(['is_otp_enabled' => 0]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/plants')->assertForbidden();
    }

    public function test_mobile_access_is_checked_for_the_bearer_token_owner(): void
    {
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->getJson('/api/mobile/v1/me')
            ->assertForbidden()->assertJsonPath('message', 'Mobile app access has not been granted or has been removed. Contact your administrator.');
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer])->getJson('/api/mobile/v1/me')
            ->assertOk()->assertJsonPath('data.id', 2)->assertJsonPath('data.email', 'user2@example.test');
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 1]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->token])->getJson('/api/mobile/v1/me')
            ->assertOk()->assertJsonPath('data.id', 1);
    }

    public function test_regular_users_cannot_grant_themselves_mobile_access(): void
    {
        $this->putJson('/testing/mobile-access', ['email' => 'user1@example.test', 'enabled' => true])->assertForbidden();
    }

    public function test_entity_admin_can_manage_members_but_not_another_entity(): void
    {
        session(['active_entity_id' => 1, 'active_plant_id' => 1]);
        DB::table('mm_roles')->where('id', 2)->update(['code' => 'ADMINISTRATOR']);
        DB::table('mm_users')->insert(['id' => 3, 'username' => 'Other', 'email' => 'other@example.test', 'is_active' => 1, 'is_otp_enabled' => 0]);
        DB::table('mm_entity_users')->insert(['user_id' => 3, 'entity_id' => 2, 'plant_id' => 2, 'role_id' => 1]);
        $this->withHeaders(['Authorization' => 'Bearer '.$this->reviewer]);
        $this->putJson('/testing/mobile-access', ['email' => 'user1@example.test', 'enabled' => false])->assertOk();
        $this->putJson('/testing/mobile-access', ['email' => 'other@example.test', 'enabled' => true])->assertNotFound();
        $this->assertEquals(0, DB::table('mm_users')->where('id', 3)->value('is_otp_enabled'));
    }

    public function test_mobile_access_migration_defaults_to_denied(): void
    {
        Schema::table('mm_users', fn (Blueprint $t) => $t->dropColumn('mobile_access_revision'));
        $migration = require base_path('database/migrations/2026_10_12_131000_add_mobile_access_to_users.php');
        $migration->up();
        $this->assertEquals(0, DB::table('mm_users')->where('id', 1)->value('mobile_access_enabled'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('mm_users', 'mobile_access_enabled'));
    }

    public function test_otp_flag_migration_preserves_grants_and_removes_old_column(): void
    {
        Schema::table('mm_users', fn (Blueprint $t) => $t->boolean('mobile_access_enabled')->default(false));
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0, 'mobile_access_enabled' => 1, 'mobile_access_revision' => 4]);
        DB::table('mm_users')->where('id', 2)->update(['is_otp_enabled' => 1, 'mobile_access_enabled' => 0]);
        DB::table('mm_users')->insert(['id' => 3, 'username' => 'Denied', 'email' => 'denied@example.test', 'is_otp_enabled' => 0, 'mobile_access_enabled' => 0]);
        $migration = require base_path('database/migrations/2026_10_12_153300_use_otp_enabled_for_mobile_access.php');
        $migration->up();
        $this->assertFalse(Schema::hasColumn('mm_users', 'mobile_access_enabled'));
        $this->assertEquals(1, User::find(1)->is_otp_enabled);
        $this->assertEquals(1, User::find(2)->is_otp_enabled);
        $this->assertEquals(0, User::find(3)->is_otp_enabled);
        $this->assertEquals(4, User::find(1)->mobile_access_revision);
        $migration->down();
        $this->assertEquals(1, DB::table('mm_users')->where('id', 1)->value('mobile_access_enabled'));
        $this->assertEquals(0, DB::table('mm_users')->where('id', 3)->value('mobile_access_enabled'));
    }

    public function test_user_edit_otp_flag_revokes_mobile_tokens_and_invalidates_pending_challenge(): void
    {
        $challenge = $this->postJson('/api/mobile/v1/auth/send-otp', ['mobile' => '9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry = Cache::get('sales-mobile:'.$challenge);
        $entry['otp'] = Hash::make('123456');
        Cache::put('sales-mobile:'.$challenge, $entry, 300);
        $webToken = User::find(1)->createToken('web-login', ['*']);
        $user = User::find(1);
        $user->update(['is_otp_enabled' => false]);
        $this->assertEquals(1, $user->mobile_access_revision);
        $this->assertEquals(0, $user->tokens()->where('name', 'like', 'sales-mobile:%')->count());
        $this->assertTrue(DB::table('personal_access_tokens')->where('id', $webToken->accessToken->id)->exists());
        $user->update(['is_otp_enabled' => true]);
        $this->assertEquals(2, $user->mobile_access_revision);
        $this->postJson('/api/mobile/v1/auth/verify-otp', ['challenge_id' => $challenge, 'otp' => '123456'])->assertForbidden();
    }
}
