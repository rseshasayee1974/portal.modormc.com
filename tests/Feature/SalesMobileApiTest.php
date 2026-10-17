<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 18:09:15 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\{DB, Schema, Hash, Cache, Http};
use Illuminate\Database\Schema\Blueprint;
use App\Models\User;

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
        config(['services.tendigit.license_number'=>'test-license','services.tendigit.api_key'=>'test-key','services.mobile_otp.template'=>'sales_executive_login']);
        Http::fake(['https://app.tendigit.in/*'=>Http::response(['status'=>'success'],200)]);
        $this->app->bind(\App\Http\Controllers\Api\SalesMobile\AuthController::class, fn()=>new class extends \App\Http\Controllers\Api\SalesMobile\AuthController {
            protected function sendWhatsAppTemplate(string $licenseNumber,string $apiKey,string $contact,string $template,string $paramString): array
            {
                try {
                    $response=Http::get('https://app.tendigit.in/api/sendtemplate.php',['LicenseNumber'=>$licenseNumber,'APIKey'=>$apiKey,'Contact'=>$contact,'Template'=>$template,'Param'=>$paramString]);
                    return ['error'=>false,'response'=>$response->body(),'http_status'=>$response->status()];
                } catch (\Illuminate\Http\Client\ConnectionException $e) {
                    return ['error'=>true,'error_code'=>'WHATSAPP_TLS_ERROR','message'=>'The WhatsApp provider HTTPS certificate does not validate.'];
                }
            }
        });
        \Illuminate\Support\Facades\Route::put('/testing/mobile-access',[\App\Http\Controllers\UserMobileAccessController::class,'update'])->middleware('auth:sanctum');
        foreach ([
            'mm_users' => ['username','email','mobile','password','api_key','plan','email_verified_at','is_active','is_otp_enabled','last_login','lockout_until','default_plant_id','default_entity_id','two_factor_confirmed_at','mobile_access_revision','login_status','login_location','ip_address','failed_attempts'],
            'mm_entities' => ['name','is_suspended'], 'mm_plants' => ['name','entity_id','is_active','gstin'],
            'mm_personnels' => ['plant_id','user_id','first_name','last_name','entity_id','status','employee_code','designation_id'], 'mm_roles' => ['name','code','guard_name'],
            'mm_permissions' => ['name','guard_name','module','description','is_system'], 'mm_entity_users' => ['user_id','entity_id','plant_id','role_id'],
            'mm_crm_leads' => ['plant_id','lead_number','enquiry_date','contact_name','company_name','phone','email','address','source','status','priority','assigned_to','project_name','mix_design_id','requirement','estimated_quantity','delivery_location','expected_value','expected_purchase_date','remarks','lost_reason','customer_id','converted_at','created_by','updated_by','deleted_by'],
            'mm_crm_deals' => ['plant_id','lead_id','quotation_id','stage','expected_value','created_by','updated_by','deleted_by'],
            'mm_crm_activities' => ['plant_id','lead_id','type','subject','description','due_at','completed_at','assigned_to','created_by','updated_by','deleted_by'],
            'mm_patrons' => ['plant_id','sales_executive_id','legal_name','patron_type','status','operational_status'], 'mm_sites' => ['plant_id','patron_id','name'],
            'mm_concrete_grades' => ['plant_id','name'],
            'mm_mix_design_items' => ['plant_id','mix_design_id','product_id','uom_id','rate','actual_quantity','cross_quantity','variation_quantity'],
            'mm_products' => ['plant_id','title','code','material_code'],
            'mm_mix_designs' => ['plant_id','design_name','design_code','is_active','concrete_grade_id','unit_id','rate_per_qty'], 'mm_product_units' => ['unit_name','unit_code','unit_type'],
            'mm_taxes' => ['plant_id','tax_name','tax_type','tax_rate','parent_id','status','tax_group'],
            'mm_quotations' => ['plant_id','reference','patron_id','site_id','quote_date','validity_date','notes','is_tax_inclusive','sales_executive_id','adjustment','status','is_customer_po','amount_untaxed','amount_tax','amount_total','created_by','updated_by','deleted_by','mobile_approval_state','mobile_submitted_by','mobile_submitted_at','mobile_reviewed_by','mobile_reviewed_at','mobile_approval_comment'],
            'mm_quotation_items' => ['discount_type','discount','discount_amount','quotation_id','mix_design_id','uom_id','quantity','rate','tax_id','tax_amount','untaxed_amount','amount_total','concrete_pump','pump_rate','created_by','updated_by','deleted_by'],
            'mm_contacts' => ['patron_id','plant_id','name','phone','mobile','contact_type_id','is_primary','status','email'],
            'mm_personnel_contacts' => ['employee_id','contact_id','contact_value','is_primary'],
            'mm_customer_pos' => ['plant_id','quotation_id','patron_id','site_id','created_by','sales_executive_id','is_tax_inclusive','reference','customer_po_reference','status','order_date','amount_untaxed','amount_tax','amount_total'],
            'mm_customer_po_items' => ['customer_po_id','mix_design_id','rate','tax_id','quantity','concrete_pump','pump_rate','untaxed_amount','tax_amount','amount_total'],
            'mm_custom_settings' => ['plant_id','module_name','settings'],
            'mm_batches' => ['plant_id','sales_order_id','batch_no','batch_size','status','start_time','end_time'],
            'mm_sales_orders' => ['plant_id','created_by','updated_by','deleted_by','sales_executive_id','customer_id','site_id','mix_design_id','total_qty','rate','tax_id','is_tax_inclusive','customer_po_id','produced_qty','status','order_no','prefix','concrete_pump','pump_rate','scheduled_start','scheduled_end'],
            'mm_dispatches' => ['plant_id','sales_order_id','batch_id','truck_id','prefix','dispatch_no','dispatch_status','delivered_qty','dispatch_time','delivery_time'],
            'mm_machine_types' => ['name'], 'mm_designations' => ['name'],
            'mm_machines' => ['plant_id','registration','vehicle_model','vehicle_type','is_active'],
            'mm_dispatch_statuses' => ['dispatch_id','is_tax_inclusive'],
            'mm_concrete_batching_schedules' => ['plant_id','sales_order_id','status','batch_id','dispatch_id','schedule_date','site_id','mix_design_id','qty_m3','order_volume_m3','remaining_volume_m3','vehicle_id','driver_id','pump_vehicle_id','pump_type','pour_reference','batching_time','dispatch_time','eta_site','unloading_start','unloading_end','notes','created_by','updated_by','deleted_by'],
        ] as $name => $columns) Schema::create($name, function (Blueprint $t) use ($columns) {
            $t->id(); foreach ($columns as $c) $t->text($c)->nullable(); $t->timestamps(); $t->softDeletes();
        });
        foreach (['mm_role_has_permissions' => ['role_id','permission_id'], 'mm_model_has_roles' => ['role_id','model_id','model_type'], 'mm_model_has_permissions' => ['permission_id','model_id','model_type']] as $name => $columns) Schema::create($name, function (Blueprint $t) use ($columns) { foreach ($columns as $c) $t->text($c); });
        Schema::create('personal_access_tokens', function (Blueprint $t) { $t->id(); $t->morphs('tokenable'); $t->string('name'); $t->string('token',64)->unique(); $t->text('abilities')->nullable(); $t->timestamp('last_used_at')->nullable(); $t->timestamp('expires_at')->nullable(); $t->timestamps(); });
        DB::table('mm_entities')->insert([['id'=>1,'is_suspended'=>0],['id'=>2,'is_suspended'=>0]]);
        DB::table('mm_plants')->insert([['id'=>1,'name'=>'Guindy','entity_id'=>1,'is_active'=>1],['id'=>2,'name'=>'Other','entity_id'=>2,'is_active'=>1]]);
        DB::table('mm_roles')->insert([['id'=>1,'name'=>'Sales Executive','code'=>'SALES_EXECUTIVE','guard_name'=>'web'],['id'=>2,'name'=>'Sales Manager','code'=>'SALES_MANAGER','guard_name'=>'web']]);
        foreach ([1,2] as $id) {
            DB::table('mm_users')->insert(['id'=>$id,'username'=>'User '.$id,'email'=>'user'.$id.'@example.test','mobile'=>$id===1?'9876543210':'9876543211','password'=>Hash::make('Test-password-123'),'is_active'=>1,'is_otp_enabled'=>1,'mobile_access_revision'=>0]);
            DB::table('mm_entity_users')->insert(['user_id'=>$id,'entity_id'=>1,'plant_id'=>1,'role_id'=>$id]);
            DB::table('mm_personnels')->insert(['id'=>$id,'plant_id'=>1,'user_id'=>$id]);
        }
        foreach (['CRM_LEAD.VIEW','CRM_LEAD.CREATE','CRM_LEAD.UPDATE','CRM_LEAD.DELETE','QUOTATION.VIEW','QUOTATION.CREATE','QUOTATION.UPDATE','QUOTATION.DELETE','QUOTATION.APPROVE','QUOTATION.VIEW_ALL','SALES_ORDER.VIEW','BATCHING_SCHEDULE.VIEW','BATCHING_SCHEDULE.UPDATE','PATRON.VIEW','SITE.VIEW'] as $i=>$name) {
            DB::table('mm_permissions')->insert(['id'=>$i+1,'name'=>$name,'guard_name'=>'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id'=>2,'permission_id'=>$i+1]);
            if (!in_array($name,['QUOTATION.APPROVE','QUOTATION.VIEW_ALL'])) DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$i+1]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->token = User::find(1)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        $this->reviewer = User::find(2)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        DB::table('mm_patrons')->insert(['id'=>1,'plant_id'=>1,'sales_executive_id'=>1,'legal_name'=>'Customer','patron_type'=>'["Customer"]','status'=>1,'operational_status'=>'active']);
        DB::table('mm_sites')->insert(['id'=>1,'plant_id'=>1,'patron_id'=>1,'name'=>'Site']);
        DB::table('mm_mix_designs')->insert(['id'=>1,'plant_id'=>1,'design_name'=>'M25','is_active'=>1]);
        DB::table('mm_taxes')->insert([['id'=>1,'plant_id'=>1,'tax_name'=>'GST18','tax_group'=>'GST','tax_type'=>'Sales','tax_rate'=>18,'parent_id'=>null,'status'=>1],['id'=>2,'plant_id'=>1,'tax_name'=>'CGST9','tax_group'=>'CGST','tax_type'=>'Sales','tax_rate'=>9,'parent_id'=>1,'status'=>1],['id'=>3,'plant_id'=>1,'tax_name'=>'SGST9','tax_group'=>'SGST','tax_type'=>'Sales','tax_rate'=>9,'parent_id'=>1,'status'=>1]]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token,'X-Plant-Id'=>'1']);
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
        $this->assertFalse(\App\Services\Mobile\MobileAccess::all('SALES_ORDER'));
        $this->assertEquals([101], \App\Services\Mobile\MobileAccess::orders()->pluck('id')->all());
        DB::table('mm_personnels')->where('user_id', 1)->delete();
        request()->attributes->remove('mobile_personnel:1:1');
        $this->assertSame([], \App\Services\Mobile\MobileAccess::orders()->pluck('id')->all());
    }

    public function test_lead_mix_design_ids_are_saved_returned_and_plant_validated(): void
    {
        DB::table('mm_mix_designs')->insert([
            ['id'=>991, 'plant_id'=>1, 'design_name'=>'Lead Mix', 'is_active'=>1],
            ['id'=>992, 'plant_id'=>2, 'design_name'=>'Other Mix', 'is_active'=>1],
            ['id'=>993, 'plant_id'=>1, 'design_name'=>'Inactive Mix', 'is_active'=>0],
        ]);
        $this->withToken($this->token);
        $response = $this->postJson('/api/mobile/v1/leads', array_replace($this->lead(), ['mix_design_id'=>991]))
            ->assertCreated()->assertJsonPath('data.mix_design_id', 991)->assertJsonPath('data.mix_design.design_name', 'Lead Mix');
        $id = $response->json('data.id');
        foreach ([992, 993, 'Lead Mix'] as $value) {
            $this->putJson('/api/mobile/v1/leads/'.$id, array_replace($this->lead(), ['mix_design_id'=>$value]))
                ->assertUnprocessable()->assertJsonValidationErrors('mix_design_id');
        }
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertOk()->assertJsonPath('data.mix_design_id', 991);
    }

    public function test_quotation_responses_include_priced_items_and_mix_design_details(): void
    {
        DB::table('mm_product_units')->insert(['id'=>901,'unit_name'=>'Cubic metre']);
        DB::table('mm_concrete_grades')->insert(['id'=>901,'plant_id'=>1,'name'=>'M25']);
        DB::table('mm_products')->insert(['id'=>901,'plant_id'=>1,'title'=>'Demo Cement','code'=>'CEM']);
        DB::table('mm_mix_designs')->where('id',1)->update(['concrete_grade_id'=>901,'unit_id'=>901,'rate_per_qty'=>546]);
        DB::table('mm_mix_design_items')->insert(['plant_id'=>1,'mix_design_id'=>1,'product_id'=>901,'uom_id'=>901,'rate'=>6,'actual_quantity'=>300]);
        $body=$this->quote();
        $body['items'][0]['uom_id']=901;
        $created=$this->postJson('/api/mobile/v1/quotations',$body)->assertCreated();
        $id=$created->json('data.id');
        $assertDetails=function ($response, string $prefix) {
            $response->assertJsonPath($prefix.'.items.0.mix_design.design_name','M25')
                ->assertJsonPath($prefix.'.items.0.mix_design.concrete_grade.name','M25')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.product.title','Demo Cement')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.actual_quantity','300.0000')
                ->assertJsonPath($prefix.'.items.0.mix_design.items.0.rate','6.0000')
                ->assertJsonPath($prefix.'.items.0.uom.unit_name','Cubic metre')
                ->assertJsonStructure(['data']);
            foreach (['quantity','rate','discount_amount','tax_amount','untaxed_amount','amount_total','pump_rate'] as $field) {
                $this->assertNotNull($response->json($prefix.'.items.0.'.$field));
            }
        };
        $assertDetails($created,'data');
        $assertDetails($this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk(),'data');
        $assertDetails($this->getJson('/api/mobile/v1/quotations')->assertOk(),'data.data.0');
        $assertDetails($this->putJson('/api/mobile/v1/quotations/'.$id,$body)->assertOk(),'data');
        $assertDetails($this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk(),'data');
    }

    private function convertedQuotationLead(int $id = 501): void
    {
        DB::table('mm_mix_designs')->where('id',1)->update(['rate_per_qty'=>546]);
        DB::table('mm_crm_leads')->insert([
            'id'=>$id, 'plant_id'=>1, 'assigned_to'=>1, 'contact_name'=>'Quote Contact',
            'lead_number'=>'LD-DEMO-'.$id, 'customer_id'=>1, 'converted_at'=>now(), 'status'=>'Converted',
            'mix_design_id'=>1, 'estimated_quantity'=>3.55, 'project_name'=>'Lead Project', 'delivery_location'=>'Site delivery',
        ]);
        DB::table('mm_crm_deals')->insert(['plant_id'=>1,'lead_id'=>$id,'stage'=>'Requirement Received']);
    }

    public function test_create_quotation_from_lead_uses_source_details_and_shared_pricing(): void
    {
        $this->convertedQuotationLead();
        $result=$this->postJson('/api/mobile/v1/leads/501/quotation',['patron_id'=>1,'tax_id'=>1,'is_tax_inclusive'=>true])
            ->assertCreated()->assertJsonPath('data.patron_id',fn ($value) => (int) $value === 1)->assertJsonPath('data.site_id',fn ($value) => (int) $value === 1)
            ->assertJsonPath('data.sales_executive_id',1)->assertJsonPath('data.mobile_approval_state','draft')
            ->assertJsonPath('data.items.0.mix_design.design_name','M25')->assertJsonPath('data.amount_total','1938.00');
        $this->assertEquals(3.55,$result->json('data.items.0.quantity'));
        $this->assertEquals(546,$result->json('data.items.0.rate'));
        $this->assertStringContainsString('Lead Project',$result->json('data.notes'));
        $this->assertEquals($result->json('data.id'),DB::table('mm_crm_deals')->where('lead_id',501)->value('quotation_id'));
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertUnprocessable()->assertJsonValidationErrors('lead_id');
        $this->assertSame(1,DB::table('mm_quotations')->count());
    }

    public function test_lead_quotation_rejects_inaccessible_and_incomplete_source_data(): void
    {
        $this->convertedQuotationLead();
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertNotFound();
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token]);
        DB::table('mm_crm_leads')->where('id',501)->update(['converted_at'=>null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertUnprocessable()->assertJsonValidationErrors('lead_id');
        DB::table('mm_crm_leads')->where('id',501)->update(['converted_at'=>now(),'estimated_quantity'=>null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertUnprocessable()->assertJsonValidationErrors('items.0.quantity');
        DB::table('mm_crm_leads')->where('id',501)->update(['estimated_quantity'=>3.55]);
        DB::table('mm_mix_designs')->where('id',1)->update(['is_active'=>0]);
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertUnprocessable()->assertJsonValidationErrors('mix_design_id');
        $this->assertSame(0,DB::table('mm_quotations')->count());
        $this->assertNull(DB::table('mm_crm_deals')->where('lead_id',501)->value('quotation_id'));
    }

    public function test_lead_quotation_requires_unambiguous_customer_site_and_allows_price_override(): void
    {
        $this->convertedQuotationLead();
        $this->postJson('/api/mobile/v1/leads/501/quotation',['patron_id'=>'Customer name'])->assertUnprocessable()->assertJsonValidationErrors('patron_id');
        $this->postJson('/api/mobile/v1/leads/501/quotation',['patron_id'=>999999])->assertUnprocessable()->assertJsonValidationErrors('patron_id');
        DB::table('mm_sites')->insert(['id'=>502,'plant_id'=>1,'patron_id'=>1,'name'=>'Second site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation',[])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        DB::table('mm_sites')->insert(['id'=>503,'plant_id'=>2,'patron_id'=>2,'name'=>'Other plant site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation',['site_id'=>503])->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $result=$this->postJson('/api/mobile/v1/leads/501/quotation',['site_id'=>502,'quantity'=>2,'rate'=>600])
            ->assertCreated()->assertJsonPath('data.site_id',fn ($value) => (int) $value === 502)->assertJsonPath('data.amount_total','1200.00');
        $this->assertEquals(600,$result->json('data.items.0.rate'));
    }

    public function test_lead_quotation_uses_logged_in_user_without_personnel_assignment(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_personnels')->where('user_id',1)->delete();
        $id = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.sales_executive_id', null)->json('data.id');
        $this->assertEquals(1, DB::table('mm_quotations')->where('id',$id)->value('created_by'));
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/quotations')->assertOk()->assertJsonPath('data.total',1);
        $this->putJson('/api/mobile/v1/quotations/'.$id, $this->quote())->assertOk();
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertNotFound();
    }

    public function test_lead_quotation_preserves_personnel_foreign_key_when_user_id_differs(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_personnels')->where('user_id',1)->update(['id'=>101]);
        $id = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.sales_executive_id',101)->json('data.id');
        $this->assertEquals(1, DB::table('mm_quotations')->where('id',$id)->value('created_by'));
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk();
    }

    public function test_lead_quotation_accepts_global_uom_with_full_pricing_payload(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_product_units')->insert(['id'=>1, 'unit_name'=>'Cubic metre', 'unit_code'=>'m3']);
        $payload = [
            'site_id'=>1, 'quantity'=>30, 'rate'=>4200, 'uom_id'=>1, 'tax_id'=>1,
            'is_tax_inclusive'=>false, 'concrete_pump'=>'36m Boom Pump', 'pump_rate'=>350,
            'patron_id'=>1, 'discount_type'=>'amount', 'discount'=>1500,
            'quote_date'=>'2026-10-17', 'validity_date'=>'2026-11-01',
            'notes'=>'Quotation includes 36m Boom Pump charges. Pour scheduled for early morning.',
        ];
        $this->postJson('/api/mobile/v1/leads/501/quotation', array_replace($payload, ['uom_id'=>999]))
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.uom_id');
        DB::table('mm_product_units')->where('id',1)->update(['deleted_at'=>now()]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', $payload)
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.uom_id');
        DB::table('mm_product_units')->where('id',1)->update(['deleted_at'=>null]);
        $this->postJson('/api/mobile/v1/leads/501/quotation', $payload)->assertCreated()
            ->assertJsonPath('data.items.0.uom_id', fn ($id) => (int) $id === 1)
            ->assertJsonPath('data.amount_total', '147323.00');
        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        $this->assertEquals([1], array_column($lookups->json('data.units'), 'id'));
    }

    private function unconvertedQuotationLead(): void
    {
        foreach ([
            'mm_patrons' => ['code', 'displayed'],
            'mm_contacts' => ['displayed'],
            'mm_crm_deals' => ['customer_id', 'name', 'expected_close_date'],
            'mm_sites' => ['site_address_1', 'site_address_2', 'type', 'status', 'is_active', 'is_restricted'],
        ] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) $t->text($column)->nullable();
            });
        }
        foreach ([
            'mm_contact_types' => ['type'], 'mm_address_types' => ['type'],
            'mm_addresses' => ['plant_id', 'contact_id', 'address_type_id', 'line_1', 'line_2', 'is_primary', 'status'],
        ] as $table => $columns) {
            Schema::create($table, function (Blueprint $t) use ($columns) {
                $t->id();
                foreach ($columns as $column) $t->text($column)->nullable();
                $t->timestamps(); $t->softDeletes();
            });
        }
        DB::table('mm_contact_types')->insert(['id'=>1, 'type'=>'Primary']);
        DB::table('mm_address_types')->insert(['id'=>1, 'type'=>'Office']);
        $this->convertedQuotationLead();
        DB::table('mm_crm_deals')->where('lead_id',501)->delete();
        DB::table('mm_crm_leads')->where('id',501)->update([
            'status'=>'New', 'converted_at'=>null, 'customer_id'=>null,
            'company_name'=>'New Company', 'phone'=>'9876500000', 'email'=>'quote@example.test', 'address'=>'Office address',
        ]);
    }

    public function test_lead_quotation_automatically_creates_customer_contact_address_and_site(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_personnels')->where('user_id',1)->delete();
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['quantity'=>0])
            ->assertUnprocessable()->assertJsonValidationErrors('quantity');
        $this->assertSame(1, DB::table('mm_patrons')->count());
        $this->assertSame(0, DB::table('mm_contacts')->count());
        $this->assertNull(DB::table('mm_crm_leads')->where('id',501)->value('converted_at'));

        $this->postJson('/api/mobile/v1/leads/501/quotation', ['tax_id'=>999])
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
        $contact = DB::table('mm_contacts')->where('patron_id',$customerId)->first();
        $this->assertEquals('Quote Contact', $contact->name);
        $this->assertEquals('9876500000', $contact->mobile);
        $this->assertEquals('quote@example.test', $contact->email);
        $this->assertEquals('Office address', DB::table('mm_addresses')->where('contact_id',$contact->id)->value('line_1'));
        $site = DB::table('mm_sites')->find($result->json('data.site_id'));
        $this->assertEquals([$customerId], json_decode($site->patron_id, true));
        $this->assertEquals('Lead Project', $site->name);
        $this->assertEquals('Site delivery', $site->site_address_1);
        $this->assertEquals('Converted', DB::table('mm_crm_leads')->where('id',501)->value('status'));
        $this->assertEquals($result->json('data.id'), DB::table('mm_crm_deals')->where('lead_id',501)->value('quotation_id'));
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertUnprocessable();
        $this->assertSame(2, DB::table('mm_patrons')->count());
    }

    public function test_lead_quotation_reuses_same_name_site_without_user_or_customer_restriction(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_sites')->insert(['id'=>502, 'plant_id'=>1, 'patron_id'=>'[999]', 'name'=>' lead PROJECT ']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.site_id', fn ($id) => (int) $id === 502);
        $this->assertSame(2, DB::table('mm_sites')->count());
        $this->assertEquals('[999]', DB::table('mm_sites')->where('id',502)->value('patron_id'));
    }

    public function test_lead_quotation_ignores_deleted_and_foreign_same_name_sites(): void
    {
        $this->unconvertedQuotationLead();
        DB::table('mm_sites')->insert([
            ['id'=>502, 'plant_id'=>1, 'name'=>'Lead Project', 'deleted_at'=>now()],
            ['id'=>503, 'plant_id'=>2, 'name'=>'Lead Project', 'deleted_at'=>null],
        ]);
        $result = $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated();
        $this->assertNotContains((int) $result->json('data.site_id'), [502,503]);
        $this->assertSame(4, DB::table('mm_sites')->count());
    }

    public function test_converted_lead_quotation_can_select_any_site_in_its_plant(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_sites')->insert(['id'=>502, 'plant_id'=>1, 'patron_id'=>null, 'name'=>'Shared site']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', ['site_id'=>502])->assertCreated()
            ->assertJsonPath('data.site_id', fn ($id) => (int) $id === 502);
    }

    public function test_converted_lead_reuses_matching_name_before_default_customer_site(): void
    {
        $this->convertedQuotationLead();
        DB::table('mm_sites')->insert(['id'=>502, 'plant_id'=>1, 'patron_id'=>999, 'name'=>'Lead Project']);
        $this->postJson('/api/mobile/v1/leads/501/quotation', [])->assertCreated()
            ->assertJsonPath('data.site_id', fn ($id) => (int) $id === 502);
        $this->assertSame(2, DB::table('mm_sites')->count());
    }

    private function lead(): array { return ['contact_name'=>'Arun','phone'=>'9876543210','email'=>null,'enquiry_date'=>'2026-10-09','source'=>'Referral','priority'=>'High','status'=>'New','expected_value'=>10000]; }

    private function seedExecutiveVisibilityRecords(): void
    {
        DB::table('mm_patrons')->insert([
            ['id'=>2, 'plant_id'=>1, 'sales_executive_id'=>2, 'legal_name'=>'Other customer', 'patron_type'=>'["Customer"]', 'operational_status'=>'active'],
            ['id'=>3, 'plant_id'=>1, 'sales_executive_id'=>1, 'legal_name'=>'New assigned customer', 'patron_type'=>'["Customer"]', 'operational_status'=>'active'],
        ]);
        DB::table('mm_sites')->insert([
            ['id'=>2, 'plant_id'=>1, 'patron_id'=>2, 'name'=>'Other site'],
            ['id'=>3, 'plant_id'=>1, 'patron_id'=>3, 'name'=>'New assigned site'],
        ]);
        foreach ([
            [101, 1, 2, 1, null], // Assigned to me, created by someone else.
            [102, 1, 1, 2, null], // Created by me, assigned to someone else.
            [103, 1, 1, null, null],
            [104, 2, 1, 1, null],
            [105, 1, 1, 1, '2026-10-14 00:00:00'],
        ] as [$id, $plant, $creator, $executive, $deleted]) {
            $base = ['id'=>$id, 'plant_id'=>$plant, 'created_by'=>$creator,
                'sales_executive_id'=>$executive, 'site_id'=>$id === 102 ? 2 : 1, 'deleted_at'=>$deleted];
            $customer = $id === 102 ? 2 : 1;
            DB::table('mm_sales_orders')->insert($base + ['customer_id'=>$customer, 'mix_design_id'=>1, 'status'=>1, 'total_qty'=>10]);
            DB::table('mm_quotations')->insert($base + ['patron_id'=>$customer, 'reference'=>'QUOTE-'.$id, 'status'=>1, 'mobile_approval_state'=>'pending']);
            DB::table('mm_customer_pos')->insert($base + ['patron_id'=>$customer, 'reference'=>'PO-'.$id]);
        }
        foreach ([101=>1, 102=>2] as $id=>$owner) {
            DB::table('mm_crm_leads')->insert(['id'=>$id, 'plant_id'=>1, 'assigned_to'=>$owner,
                'customer_id'=>$owner, 'contact_name'=>'Lead '.$id, 'source'=>'Referral', 'status'=>'New']);
            DB::table('mm_crm_activities')->insert(['id'=>$id, 'plant_id'=>1, 'lead_id'=>$id,
                'type'=>'Site Visit', 'due_at'=>now()]);
            DB::table('mm_concrete_batching_schedules')->insert(['id'=>$id, 'plant_id'=>1,
                'sales_order_id'=>$id, 'site_id'=>$owner, 'mix_design_id'=>1, 'status'=>'scheduled']);
        }
    }

    public function test_executive_visibility_is_consistent_across_lists_details_lookups_and_summaries(): void
    {
        $this->seedExecutiveVisibilityRecords();
        foreach (['CRM_LEAD', 'QUOTATION', 'SALES_ORDER', 'PATRON', 'SITE'] as $module) {
            $name = $module.'.VIEW_ALL';
            $id = DB::table('mm_permissions')->where('name', $name)->value('id')
                ?? DB::table('mm_permissions')->insertGetId(['name'=>$name, 'guard_name'=>'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id'=>1, 'permission_id'=>$id]);
        }
        DB::table('mm_role_has_permissions')->where('role_id', 1)->whereIn('permission_id',
            DB::table('mm_permissions')->where('name', 'BATCHING_SCHEDULE.VIEW')->select('id'))->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (['leads', 'quotations', 'orders', 'customer-pos', 'batch-schedules', 'follow-ups'] as $resource) {
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', 1);
            $this->assertEquals([101], array_column($response->json('data.data'), 'id'));
        }
        foreach (['customers', 'sites'] as $resource) {
            $expected = $resource === 'sites' ? [1, 2, 3] : [1, 3];
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total', count($expected));
            $this->assertEqualsCanonicalizing($expected, array_column($response->json('data.data'), 'id'));
            $this->getJson('/api/mobile/v1/'.$resource.'/2')->assertStatus($resource === 'sites' ? 200 : 404);
        }
        foreach (['leads', 'quotations', 'orders', 'batch-schedules'] as $resource) {
            $this->getJson('/api/mobile/v1/'.$resource.'/102')->assertNotFound();
        }
        $lookups = $this->getJson('/api/mobile/v1/lookups')->assertOk();
        foreach (['customers', 'sites'] as $key) {
            $this->assertEqualsCanonicalizing($key === 'sites' ? [1, 2, 3] : [1, 3], array_column($lookups->json('data.'.$key), 'id'));
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
        $this->seedExecutiveVisibilityRecords();
        DB::table('mm_crm_activities')->insert([
            ['id'=>201, 'plant_id'=>1, 'lead_id'=>101, 'assigned_to'=>2, 'type'=>'Site Visit', 'subject'=>'Other executive task', 'due_at'=>now()->subDay()],
            ['id'=>202, 'plant_id'=>1, 'lead_id'=>101, 'assigned_to'=>1, 'type'=>'Call', 'subject'=>'My task', 'due_at'=>now()->addHour()],
            ['id'=>203, 'plant_id'=>1, 'lead_id'=>102, 'assigned_to'=>1, 'type'=>'Call', 'subject'=>'Foreign lead task', 'due_at'=>now()],
        ]);
        $response=$this->getJson('/api/mobile/v1/follow-ups?view=all')->assertOk();
        $this->assertEqualsCanonicalizing([101,202],array_column($response->json('data.data'),'id'));
        $lead=$this->getJson('/api/mobile/v1/leads/101')->assertOk();
        $this->assertEqualsCanonicalizing([101,202],array_column($lead->json('data.activities'),'id'));
        $this->putJson('/api/mobile/v1/follow-ups/201',[])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/201/complete',[])->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/201')->assertNotFound();
        $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.overdue_followups',0);
        $this->getJson('/api/mobile/v1/marketing/summary')->assertOk()->assertJsonPath('data.site_visits',1);
    }

    public function test_managers_and_administrators_are_also_restricted_to_their_own_mobile_records(): void
    {
        $this->seedExecutiveVisibilityRecords();
        foreach (['SALES_MANAGER', 'ADMINISTRATOR', 'SAAS_OWNER'] as $code) {
            DB::table('mm_roles')->where('id',2)->update(['code'=>$code,'name'=>$code === 'SAAS_OWNER' ? 'Saas Owner' : 'Mobile '.$code]);
            if ($code === 'SAAS_OWNER') User::find(2)->assignRole(\App\Models\Role::find(2));
            app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer,'X-Plant-Id'=>'1']);
            foreach (['leads','quotations','orders','customer-pos','batch-schedules','follow-ups'] as $resource) {
                $result=$this->getJson('/api/mobile/v1/'.$resource)->assertOk()->assertJsonPath('data.total',1);
                $this->assertEquals([102],array_column($result->json('data.data'),'id'),$code.' '.$resource);
            }
            foreach (['leads','quotations','orders','batch-schedules','customers'] as $resource) {
                $this->getJson('/api/mobile/v1/'.$resource.'/'.(in_array($resource,['customers','sites']) ? 1 : 101))->assertNotFound();
            }
            $this->postJson('/api/mobile/v1/quotations/101/review',['decision'=>'approved'])->assertNotFound();
            $this->postJson('/api/mobile/v1/leads',$this->lead()+['assigned_to'=>1])->assertForbidden();
            $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.active_orders',1);
            $lookups=$this->getJson('/api/mobile/v1/lookups')->assertOk();
            $this->assertEquals([102],array_column($lookups->json('data.customer_pos'),'id'));
            $this->assertEquals([102],array_column($lookups->json('data.sales_orders'),'id'));
        }
    }

    public function test_executive_writes_reject_inaccessible_foreign_keys_and_records(): void
    {
        $this->seedExecutiveVisibilityRecords();
        $order = ['customer_id'=>1, 'site_id'=>1, 'mix_design_id'=>1, 'total_qty'=>1, 'rate'=>100];
        $this->postJson('/api/mobile/v1/orders', array_replace($order, ['customer_id'=>2]))
            ->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->postJson('/api/mobile/v1/orders', array_replace($order, ['site_id'=>2]))
            ->assertUnprocessable()->assertJsonValidationErrors('site_id');
        $this->postJson('/api/mobile/v1/orders', $order + ['customer_po_id'=>102])
            ->assertUnprocessable()->assertJsonValidationErrors('customer_po_id');
        $this->postJson('/api/mobile/v1/quotations', array_replace($this->quote(), ['patron_id'=>2, 'site_id'=>2]))
            ->assertUnprocessable()->assertJsonValidationErrors(['patron_id']);
        $this->postJson('/api/mobile/v1/batch-schedules', ['site_id'=>1, 'mix_design_id'=>1, 'sales_order_id'=>102])
            ->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $this->postJson('/api/mobile/v1/leads', $this->lead() + ['assigned_to'=>2])->assertForbidden();
        foreach (['leads', 'quotations', 'orders', 'batch-schedules'] as $resource) {
            $this->putJson('/api/mobile/v1/'.$resource.'/102', [])->assertNotFound();
            $this->deleteJson('/api/mobile/v1/'.$resource.'/102')->assertNotFound();
        }
        $this->postJson('/api/mobile/v1/quotations/102/submit')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/102/review', ['decision'=>'approved'])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/102/complete')->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/102')->assertNotFound();
    }

    public function test_direct_customer_assignment_allows_first_sale_and_controls_owner(): void
    {
        $this->seedExecutiveVisibilityRecords();
        $this->postJson('/api/mobile/v1/quotations', array_replace($this->quote(), [
            'patron_id'=>3, 'site_id'=>3, 'sales_executive_id'=>2,
        ]))->assertCreated()->assertJsonPath('data.sales_executive_id', 1);
        $this->postJson('/api/mobile/v1/orders', ['customer_id'=>3, 'site_id'=>3, 'mix_design_id'=>1,
            'total_qty'=>1, 'rate'=>100, 'sales_executive_id'=>2])->assertCreated()
            ->assertJsonPath('data.sales_executive_id', 1);
    }

    public function test_customer_and_site_access_inherits_only_visible_business_relationships(): void
    {
        foreach (range(11, 16) as $id) {
            DB::table('mm_patrons')->insert(['id'=>$id, 'plant_id'=>1, 'sales_executive_id'=>2,
                'legal_name'=>'Related '.$id, 'patron_type'=>'["Customer"]', 'operational_status'=>'active']);
            DB::table('mm_sites')->insert(['id'=>$id, 'plant_id'=>1, 'patron_id'=>$id, 'name'=>'Related '.$id]);
        }
        DB::table('mm_crm_leads')->insert(['plant_id'=>1, 'assigned_to'=>1, 'customer_id'=>11]);
        DB::table('mm_quotations')->insert(['plant_id'=>1, 'created_by'=>2, 'sales_executive_id'=>1, 'patron_id'=>12, 'site_id'=>12]);
        DB::table('mm_customer_pos')->insert(['plant_id'=>1, 'created_by'=>2, 'sales_executive_id'=>1, 'patron_id'=>13, 'site_id'=>13]);
        DB::table('mm_sales_orders')->insert([
            ['plant_id'=>1, 'sales_executive_id'=>1, 'customer_id'=>14, 'site_id'=>14, 'deleted_at'=>null],
            ['plant_id'=>1, 'sales_executive_id'=>1, 'customer_id'=>15, 'site_id'=>15, 'deleted_at'=>'2026-10-14 00:00:00'],
            ['plant_id'=>2, 'sales_executive_id'=>1, 'customer_id'=>16, 'site_id'=>16, 'deleted_at'=>null],
        ]);
        foreach (['customers', 'sites'] as $resource) {
            $response = $this->getJson('/api/mobile/v1/'.$resource)->assertOk();
            $this->assertEqualsCanonicalizing($resource === 'sites' ? [1, 11, 12, 13, 14, 15, 16] : [1, 11, 12, 13, 14], array_column($response->json('data.data'), 'id'));
            foreach ([15, 16] as $id) {
                $this->getJson('/api/mobile/v1/'.$resource.'/'.$id)->assertStatus($resource === 'sites' ? 200 : 404);
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
        $this->postJson('/api/mobile/v1/orders', ['customer_id'=>1, 'site_id'=>1, 'mix_design_id'=>1,
            'total_qty'=>1, 'rate'=>100])->assertForbidden();
        $this->assertSame(5, DB::table('mm_sales_orders')->count());
    }

    public function test_personnel_and_queries_follow_the_selected_plant_across_requests(): void
    {
        $this->seedExecutiveVisibilityRecords();
        DB::table('mm_plants')->insert(['id'=>3, 'name'=>'Second plant', 'entity_id'=>1, 'is_active'=>1]);
        DB::table('mm_entity_users')->insert(['user_id'=>1, 'entity_id'=>1, 'plant_id'=>3, 'role_id'=>1]);
        DB::table('mm_personnels')->insert(['id'=>31, 'plant_id'=>3, 'user_id'=>1]);
        DB::table('mm_sales_orders')->insert([
            ['id'=>301, 'plant_id'=>3, 'created_by'=>2, 'sales_executive_id'=>31],
            ['id'=>302, 'plant_id'=>3, 'created_by'=>1, 'sales_executive_id'=>1],
        ]);
        $this->getJson('/api/mobile/v1/orders')->assertOk()->assertJsonPath('data.data.0.id', 101);
        $this->withHeaders(['X-Plant-Id'=>'3'])->getJson('/api/mobile/v1/orders')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 301);
        $this->withHeaders(['X-Plant-Id'=>'1'])->getJson('/api/mobile/v1/orders')->assertOk()
            ->assertJsonPath('data.total', 1)->assertJsonPath('data.data.0.id', 101);
    }

    public function test_route_group_declares_sales_scope_once_after_plant_context(): void
    {
        $plantMiddleware = \App\Http\Middleware\MobilePlantContext::class;
        $scopeMiddleware = \App\Http\Middleware\MobileSalesScope::class;
        $scopedRoutes = 0;
        foreach ($this->mobileEndpoints() as [$method, $url, $middleware]) {
            if (!in_array($plantMiddleware, $middleware, true)) {
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
        \Illuminate\Support\Facades\Route::get('/testing/sales-mobile-scope', function (\Illuminate\Http\Request $request) {
            return ['executive'=>$request->attributes->get('mobile_sales_executive'),
                'personnel_loaded'=>$request->attributes->has('mobile_personnel:1:1'),
                'fresh_queries'=>\App\Services\Mobile\MobileAccess::orders() !== \App\Services\Mobile\MobileAccess::orders()];
        })->middleware(['auth:sanctum', \App\Http\Middleware\MobilePlantContext::class,
            \App\Http\Middleware\MobileSalesScope::class]);
        $this->getJson('/testing/sales-mobile-scope')->assertOk()
            ->assertExactJson(['executive'=>true, 'personnel_loaded'=>true, 'fresh_queries'=>true]);
    }

    private function mobileEndpoints(): array
    {
        $endpoints = [];
        foreach (app('router')->getRoutes() as $route) {
            if (!str_starts_with($route->uri(), 'api/mobile/')) continue;
            foreach (array_diff($route->methods(), ['HEAD']) as $method) {
                $endpoints[] = [$method, '/'.preg_replace('/\{[^}]+\}/', '999', $route->uri()), $route->gatherMiddleware()];
            }
        }
        return $endpoints;
    }

    public function test_legacy_public_auth_success_validation_inactive_and_otp_replay(): void
    {
        $this->withHeaders(['Authorization'=>'']);
        $this->getJson('/api/mobile/status')->assertOk()->assertJsonPath('success',true);
        $this->postJson('/api/mobile/login',[])->assertUnprocessable()->assertJsonValidationErrors(['email','password']);
        $this->postJson('/api/mobile/login',['email'=>'user1@example.test','password'=>'wrong'])->assertUnauthorized();
        $this->postJson('/api/mobile/login',['email'=>'user1@example.test','password'=>'Test-password-123'])->assertOk()->assertJsonPath('2fa_required',true);
        DB::table('mm_users')->where('id',1)->update(['is_otp_enabled'=>0]);
        $this->postJson('/api/mobile/login',['email'=>'user1@example.test','password'=>'Test-password-123'])->assertOk()->assertJsonStructure(['token','user']);
        $this->postJson('/api/mobile/send-otp',[])->assertUnprocessable();
        $this->postJson('/api/mobile/send-otp',['email'=>'missing@example.test'])->assertUnprocessable();
        $this->postJson('/api/mobile/send-otp',['email'=>'user1@example.test'])->assertOk()->assertJsonPath('expires_in',300);
        Cache::put('email_otp:user1@example.test',Hash::make('123456'),300);
        $this->postJson('/api/mobile/verify-otp',['email'=>'user1@example.test','otp'=>'000000'])->assertUnprocessable();
        $this->postJson('/api/mobile/verify-otp',['email'=>'user1@example.test','otp'=>'123456'])->assertOk()->assertJsonStructure(['token']);
        $this->postJson('/api/mobile/verify-otp',['email'=>'user1@example.test','otp'=>'123456'])->assertUnprocessable();
        DB::table('mm_users')->where('id',1)->update(['is_active'=>0]);
        Cache::flush();
        $this->postJson('/api/mobile/login',['email'=>'user1@example.test','password'=>'Test-password-123'])->assertForbidden();
        $this->postJson('/api/mobile/send-otp',['email'=>'user1@example.test'])->assertForbidden();
        Cache::put('email_otp:user1@example.test',Hash::make('123456'),300);
        $this->postJson('/api/mobile/verify-otp',['email'=>'user1@example.test','otp'=>'123456'])->assertForbidden();
    }

    public function test_legacy_reporting_routes_delegate_filters_and_return_empty_results(): void
    {
        $methods = ['sales-summary'=>'getSalesSummary','sales-details'=>'getSalesDetails',
            'customer-details'=>'getCustomerDetails','top-mix-designs'=>'getTopMixDesignsFromBatches',
            'truck-dispatch-details'=>'getDispatchDetailsByTruck','dispatch-details'=>'getDispatchDetails',
            'dispatch-batching-summary'=>'getDispatchBatchingSummary','stock-details'=>'getStockDetails'];
        $service = \Mockery::mock(\App\Services\DashboardService::class);
        $filters = ['from_date'=>'2026-10-01','to_date'=>'2026-10-14','plant_id'=>'1','type'=>'Cash'];
        foreach ($methods as $path=>$method) $service->shouldReceive($method)->with($filters)->once()->andReturn([]);
        $service->shouldReceive('getSalesSummary')->with([])->once()->andReturn(['total'=>100]);
        $service->shouldReceive('getStockDetails')->with([])->once()->andReturn([]);
        $this->app->instance(\App\Services\DashboardService::class,$service);
        foreach ($methods as $path=>$method) $this->getJson('/api/mobile/'.$path.'?'.http_build_query($filters+['ignored'=>'value']))
            ->assertOk()->assertExactJson(['success'=>true,'data'=>[]]);
        $this->getJson('/api/mobile/dashboard')->assertOk()->assertJsonPath('data.sales_summary.total',100);
        $this->getJson('/api/mobile/user')->assertOk()->assertJsonPath('data.id',1)->assertJsonMissingPath('data.password');
    }

    public function test_public_otp_route_throttles_and_malformed_payloads(): void
    {
        foreach (['/api/mobile/send-otp'=>6,'/api/mobile/verify-otp'=>10,
            '/api/mobile/v1/auth/send-otp'=>5,'/api/mobile/v1/auth/verify-otp'=>10,'/api/mobile/v1/auth/login'=>5] as $url=>$limit) {
            Cache::flush();
            for ($attempt=0; $attempt<$limit; $attempt++) $this->postJson($url,[])->assertUnprocessable();
            $this->postJson($url,[])->assertStatus(429);
        }
    }

    public function test_legacy_reporting_invalid_filters_and_server_errors_are_safe(): void
    {
        $service = \Mockery::mock(\App\Services\DashboardService::class);
        $this->app->instance(\App\Services\DashboardService::class,$service);
        foreach (['dashboard','sales-summary','sales-details','customer-details','top-mix-designs',
            'truck-dispatch-details','dispatch-details','dispatch-batching-summary','stock-details'] as $path) {
            $this->getJson('/api/mobile/'.$path.'?from_date=invalid&plant_id=-1')->assertUnprocessable()
                ->assertJsonValidationErrors(['from_date','plant_id'])->assertJsonMissingPath('trace');
            $this->getJson('/api/mobile/'.$path.'?from_date=2026-10-14&to_date=2026-10-01')->assertUnprocessable()
                ->assertJsonValidationErrors('to_date');
        }
        config(['app.debug'=>true]);
        $service->shouldReceive('getSalesSummary')->once()->andThrow(new \RuntimeException('Private database details'));
        $this->getJson('/api/mobile/sales-summary')->assertStatus(500)->assertExactJson(['message'=>'Server error.']);
    }

    public function test_mobile_user_management_valid_crud_and_invalid_input(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        $this->makeReviewerSystemAdmin();
        $this->getJson('/api/mobile/users')->assertOk()->assertJsonPath('success',true);
        $body = ['username'=>'Mobile Test','email'=>'mobile-new@example.test','password'=>'Strong-password-123','is_active'=>true,'is_otp_enabled'=>false];
        $id = $this->postJson('/api/mobile/users',$body)->assertOk()->json('user.id');
        $this->getJson('/api/mobile/users/'.$id)->assertOk()->assertJsonPath('email',$body['email']);
        $this->putJson('/api/mobile/users/'.$id,array_replace($body,['username'=>'Updated']))->assertOk();
        $this->postJson('/api/mobile/users',$body)->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->patchJson('/api/mobile/users/'.$id,[])->assertUnprocessable();
        $this->deleteJson('/api/mobile/users/'.$id)->assertOk();
        $this->deleteJson('/api/mobile/users/'.$id)->assertNotFound();
        $this->getJson('/api/mobile/users/999')->assertNotFound();
    }

    public function test_v1_foreign_and_unowned_resources_cannot_be_read_changed_or_deleted(): void
    {
        foreach ([['leads','mm_crm_leads'],['quotations','mm_quotations'],['orders','mm_sales_orders']] as [$resource,$table]) {
            foreach ([[900,2,1],[901,1,2]] as [$id,$plant,$owner]) {
                $data = ['id'=>$id,'plant_id'=>$plant,'created_by'=>$owner];
                if ($resource === 'leads') $data['assigned_to'] = $owner;
                DB::table($table)->insert($data);
                $url = '/api/mobile/v1/'.$resource.'/'.$id;
                $this->getJson($url)->assertNotFound();
                $this->putJson($url,$resource === 'leads' ? $this->lead() : ($resource === 'quotations' ? $this->quote() : []))->assertNotFound();
                $this->deleteJson($url)->assertNotFound();
            }
        }
    }

    public function test_every_protected_mobile_route_rejects_missing_or_invalid_tokens(): void
    {
        $checked = 0;
        foreach ($this->mobileEndpoints() as [$method,$url,$middleware]) {
            if (!in_array('auth:sanctum',$middleware)) continue;
            foreach (['','Bearer invalid-token'] as $authorization) {
                $this->withHeaders(['Authorization'=>$authorization])->json($method,$url,[])->assertUnauthorized();
            }
            $checked++;
        }
        $this->assertGreaterThan(50,$checked);
    }

    public function test_every_plant_scoped_route_rejects_missing_and_foreign_plant(): void
    {
        foreach ($this->mobileEndpoints() as [$method,$url,$middleware]) {
            if (!in_array(\App\Http\Middleware\MobilePlantContext::class,$middleware)) continue;
            $this->withHeaders(['Authorization'=>'Bearer '.$this->token,'X-Plant-Id'=>''])->json($method,$url,[])
                ->assertUnprocessable()->assertJsonValidationErrors('plant_id');
            $this->withHeaders(['Authorization'=>'Bearer '.$this->token,'X-Plant-Id'=>'2'])->json($method,$url,[])->assertForbidden();
        }
    }

    public function test_v1_listing_boundaries_and_successful_empty_results(): void
    {
        foreach (['leads','follow-ups','quotations','orders','customer-pos','batch-schedules','customers','sites','mix-designs','taxes'] as $path) {
            $url = '/api/mobile/v1/'.$path;
            $this->getJson($url.'?per_page=1')->assertOk()->assertJsonPath('data.per_page',1);
            $this->getJson($url.'?per_page=100')->assertOk()->assertJsonPath('data.per_page',100);
            foreach (['0','101','abc'] as $value) $this->getJson($url.'?per_page='.$value)->assertUnprocessable()->assertJsonValidationErrors('per_page');
        }
        foreach (['leads','quotations','orders','customer-pos','customers','sites','mix-designs','taxes'] as $path) {
            $this->getJson('/api/mobile/v1/'.$path.'?search=nonexistent')->assertOk()->assertJsonPath('data.total',0);
        }
    }

    public function test_v1_resource_crud_conversion_and_followup_lifecycle(): void
    {
        $permission = DB::table('mm_permissions')->insertGetId(['name'=>'CRM_LEAD.CONVERT','guard_name'=>'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$permission]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        if (!Schema::hasTable('mm_crm_deals')) Schema::create('mm_crm_deals',function (Blueprint $t) {
            $t->id(); foreach (['plant_id','lead_id','customer_id','name','stage','expected_value','expected_close_date','created_by','updated_by','deleted_by'] as $field) $t->text($field)->nullable();
            $t->timestamps(); $t->softDeletes();
        });
        foreach (['customer_id','name','expected_close_date'] as $field) {
            if (!Schema::hasColumn('mm_crm_deals',$field)) Schema::table('mm_crm_deals',fn (Blueprint $t) => $t->text($field)->nullable());
        }
        $lead = $this->postJson('/api/mobile/v1/leads',$this->lead())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/leads/'.$lead)->assertOk();
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert',['customer_id'=>1])->assertUnprocessable();
        $this->putJson('/api/mobile/v1/leads/'.$lead,array_replace($this->lead(),['status'=>'Qualified']))->assertOk();
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert',['customer_id'=>1])->assertOk()->assertJsonPath('data.status','Converted');
        $this->postJson('/api/mobile/v1/leads/'.$lead.'/convert',['customer_id'=>1])->assertUnprocessable();
        $activity = ['lead_id'=>$lead,'type'=>'Call','subject'=>'Follow up','due_at'=>'2026-10-15 10:00:00'];
        $id = $this->postJson('/api/mobile/v1/follow-ups',$activity)->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/follow-ups/'.$id,$activity+['description'=>'Updated'])->assertOk();
        $this->postJson('/api/mobile/v1/follow-ups/'.$id.'/complete')->assertOk();
        $this->postJson('/api/mobile/v1/follow-ups/'.$id.'/complete')->assertOk();
        $this->putJson('/api/mobile/v1/follow-ups/'.$id,$activity)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/follow-ups/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/customers/1')->assertOk();
        $this->getJson('/api/mobile/v1/sites/1')->assertOk();
        $quote = $this->postJson('/api/mobile/v1/quotations',$this->quote())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/quotations/'.$quote)->assertOk();
        $this->deleteJson('/api/mobile/v1/quotations/'.$quote)->assertOk();
        $body = ['customer_id'=>1,'site_id'=>1,'mix_design_id'=>1,'total_qty'=>1,'rate'=>100];
        $order = $this->postJson('/api/mobile/v1/orders',$body)->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/orders/'.$order)->assertOk();
        $this->getJson('/api/mobile/v1/orders')->assertOk();
        $this->deleteJson('/api/mobile/v1/orders/'.$order)->assertOk();
        $this->deleteJson('/api/mobile/v1/leads/'.$lead)->assertUnprocessable();
        $draftLead = $this->postJson('/api/mobile/v1/leads',array_replace($this->lead(),['phone'=>'9876543299']))->assertCreated()->json('data.id');
        $this->deleteJson('/api/mobile/v1/leads/'.$draftLead)->assertOk();
    }

    public function test_v1_missing_records_and_invalid_create_payloads(): void
    {
        foreach (['leads','quotations','orders','batch-schedules','customers','sites'] as $resource) {
            $this->getJson('/api/mobile/v1/'.$resource.'/999')->assertNotFound();
            $this->getJson('/api/mobile/v1/'.$resource.'/abc')->assertNotFound();
        }
        foreach (['leads','quotations','orders','batch-schedules'] as $resource) {
            $this->postJson('/api/mobile/v1/'.$resource,[])->assertUnprocessable();
            $this->putJson('/api/mobile/v1/'.$resource.'/999',[])->assertNotFound();
            $this->deleteJson('/api/mobile/v1/'.$resource.'/999')->assertNotFound();
        }
        $this->postJson('/api/mobile/v1/follow-ups',[])->assertUnprocessable();
        $this->putJson('/api/mobile/v1/follow-ups/999',[])->assertNotFound();
        $this->postJson('/api/mobile/v1/follow-ups/999/complete')->assertNotFound();
        $this->deleteJson('/api/mobile/v1/follow-ups/999')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/999/submit')->assertNotFound();
        $this->postJson('/api/mobile/v1/quotations/999/review',['decision'=>'approved'])->assertNotFound();
        $this->postJson('/api/mobile/v1/leads/999/convert',[])->assertForbidden();
    }
    public function test_customer_po_listing_scopes_filters_and_permissions(): void
    {
        foreach ([
            [1,1,1,null,null], [2,1,2,1,null], [3,1,2,2,null],
            [4,2,1,1,null], [5,1,1,1,'2026-10-14 00:00:00'],
        ] as [$id,$plant,$creator,$executive,$deleted]) {
            DB::table('mm_customer_pos')->insert(['id'=>$id,'plant_id'=>$plant,'created_by'=>$creator,
                'sales_executive_id'=>$executive,'patron_id'=>1,'site_id'=>1,'reference'=>'CPO-'.$id,
                'customer_po_reference'=>'CUSTOMER-'.$id,'status'=>0,'amount_untaxed'=>100,'amount_tax'=>18,
                'amount_total'=>118,'deleted_at'=>$deleted]);
        }
        DB::table('mm_customer_po_items')->insert(['customer_po_id'=>2,'mix_design_id'=>1,'quantity'=>10,'rate'=>10]);
        $listing = $this->getJson('/api/mobile/v1/customer-pos?per_page=1')->assertOk()
            ->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.id',2)
            ->assertJsonPath('data.data.0.patron.legal_name','Customer')->assertJsonPath('data.data.0.site.name','Site')
            ->assertJsonCount(1,'data.data.0.items');
        $this->assertEqualsCanonicalizing(['id','reference','customer_po_reference','patron_id','site_id','order_date',
            'status','is_tax_inclusive','amount_untaxed','amount_tax','amount_total','patron','site','items'],
            array_keys($listing->json('data.data.0')));
        $this->assertEqualsCanonicalizing(['id','mix_design_id','quantity','rate','tax_id','concrete_pump','pump_rate',
            'untaxed_amount','tax_amount','amount_total'], array_keys($listing->json('data.data.0.items.0')));
        $this->getJson('/api/mobile/v1/customer-pos?search=CUSTOMER-2&customer_id=1&site_id=1&status=0')
            ->assertOk()->assertJsonPath('data.total',1)->assertJsonPath('data.data.0.id',2);
        $this->getJson('/api/mobile/v1/customer-pos?search=CPO-3')->assertOk()->assertJsonPath('data.total',0);
        $this->getJson('/api/mobile/v1/customer-pos?status=1')->assertOk()->assertJsonPath('data.total',0);
        $this->getJson('/api/mobile/v1/customer-pos?customer_id=2')->assertOk()->assertJsonPath('data.total',0);
        $this->getJson('/api/mobile/v1/customer-pos?per_page=101&status=3')->assertUnprocessable();
        DB::table('mm_permissions')->insert(['id'=>100,'name'=>'SALES_ORDER.VIEW_ALL','guard_name'=>'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>100]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/mobile/v1/customer-pos')->assertOk()->assertJsonPath('data.total',1);
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/mobile/v1/customer-pos')->assertOk()->assertJsonPath('data.total',1);
        $this->withHeaders(['Authorization'=>''])->getJson('/api/mobile/v1/customer-pos')->assertUnauthorized();
    }
    public function test_dispatches_without_module_permissions_are_paged_and_scoped(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([[1,1,1],[2,1,2],[3,2,1]] as [$id,$plant,$creator]) {
            DB::table('mm_sales_orders')->insert(['id'=>$id,'plant_id'=>$plant,'created_by'=>$creator,'sales_executive_id'=>$creator]);
        }
        DB::table('mm_machines')->insert(['id'=>1,'plant_id'=>1,'registration'=>'TN-TEST']);
        DB::table('mm_batches')->insert([
            ['id'=>1,'plant_id'=>1,'sales_order_id'=>1,'batch_no'=>1,'batch_size'=>5,'deleted_at'=>null],
            ['id'=>2,'plant_id'=>1,'sales_order_id'=>1,'batch_no'=>2,'batch_size'=>5,'deleted_at'=>'2026-10-14 00:00:00'],
        ]);
        foreach ([[1,1,1,1,null],[2,1,1,null,null],[3,1,1,2,null],[4,2,1,null,null],[5,1,2,null,null],[6,1,1,null,'2026-10-14 00:00:00']] as [$id,$plant,$order,$batch,$deleted]) {
            DB::table('mm_dispatches')->insert(['id'=>$id,'plant_id'=>$plant,'sales_order_id'=>$order,
                'batch_id'=>$batch,'truck_id'=>1,'dispatch_time'=>'2026-10-14 10:00:00','dispatch_no'=>$id,
                'delivered_qty'=>5,'dispatch_status'=>'Delivered','deleted_at'=>$deleted]);
        }
        $response = $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=1')->assertOk()
            ->assertJsonPath('data.total',2)->assertJsonPath('data.data.0.id',1)
            ->assertJsonPath('data.data.0.truck.registration','TN-TEST')->assertJsonPath('data.data.0.batch.id',1);
        $this->assertEqualsCanonicalizing(['id','sales_order_id','batch_id','truck_id','prefix','dispatch_no',
            'dispatch_time','delivery_time','delivered_qty','dispatch_status','truck','batch'], array_keys($response->json('data.data.0')));
        $this->assertEqualsCanonicalizing(['id','batch_no','batch_size'], array_keys($response->json('data.data.0.batch')));
        $this->assertEqualsCanonicalizing(['id','registration'], array_keys($response->json('data.data.0.truck')));
        $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=1&page=2')->assertOk()
            ->assertJsonPath('data.data.0.id',2)->assertJsonPath('data.data.0.batch',null);
        foreach ([2,3,999] as $order) $this->getJson('/api/mobile/v1/orders/'.$order.'/dispatches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?per_page=101')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number/dispatches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=abc')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/dispatches?page=3&per_page=1')->assertOk()->assertJsonCount(0,'data.data');
        $this->withHeaders(['Authorization'=>''])->getJson('/api/mobile/v1/orders/1/dispatches')->assertUnauthorized();
    }

    public function test_batches_without_module_permissions_are_paged_and_scoped(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        foreach ([[1,1,1],[2,1,2],[3,2,1]] as [$id,$plant,$creator]) {
            DB::table('mm_sales_orders')->insert(['id'=>$id,'plant_id'=>$plant,'created_by'=>$creator,'sales_executive_id'=>$creator]);
        }
        foreach ([[1,1,1,2,null],[2,1,1,1,null],[3,1,1,3,'2026-10-14 00:00:00'],[4,2,1,4,null],[5,1,2,5,null]] as [$id,$plant,$order,$number,$deleted]) {
            DB::table('mm_batches')->insert(['id'=>$id,'plant_id'=>$plant,'sales_order_id'=>$order,
                'batch_no'=>$number,'batch_size'=>5,'status'=>1,'deleted_at'=>$deleted]);
        }
        $response = $this->getJson('/api/mobile/v1/orders/1/batches?per_page=1')->assertOk()
            ->assertJsonPath('success',true)->assertJsonPath('data.total',2)->assertJsonPath('data.data.0.id',2);
        $this->assertEqualsCanonicalizing(['id','batch_no','sales_order_id','batch_size','status','start_time','end_time'],
            array_keys($response->json('data.data.0')));
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=1&page=2')->assertOk()->assertJsonPath('data.data.0.id',1);
        $this->getJson('/api/mobile/v1/orders/2/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/3/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/999/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=101')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?per_page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number/batches')->assertNotFound();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=0')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=abc')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/1/batches?page=3&per_page=1')->assertOk()->assertJsonCount(0,'data.data');
        $this->withHeaders(['Authorization'=>''])->getJson('/api/mobile/v1/orders/1/batches')->assertUnauthorized();
    }

    private function quote(): array { return ['reference'=>'QT-TEST-1','patron_id'=>1,'site_id'=>1,'quote_date'=>'2026-10-09','is_tax_inclusive'=>true,'items'=>[['mix_design_id'=>1,'quantity'=>3.55,'rate'=>546,'tax_id'=>1]]]; }
    public function test_order_update_with_customer_po_uses_matching_line_and_validates_missing_lines(): void
    {
        DB::table('mm_customer_pos')->insert(['id'=>58,'plant_id'=>1,'patron_id'=>1,'site_id'=>1,'created_by'=>1,'sales_executive_id'=>1,'is_tax_inclusive'=>1]);
        DB::table('mm_customer_po_items')->insert(['customer_po_id'=>58,'mix_design_id'=>1,'rate'=>4500,'tax_id'=>1,'concrete_pump'=>'pump','pump_rate'=>100]);
        DB::table('mm_mix_designs')->insert(['id'=>23,'plant_id'=>1,'design_name'=>'Other mix','is_active'=>1]);
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'sales_executive_id'=>1,'customer_id'=>1,'site_id'=>1,'mix_design_id'=>1,'total_qty'=>10,'rate'=>250,'status'=>1]);
        $body = ['customer_id'=>1,'site_id'=>1,'mix_design_id'=>1,'customer_po_id'=>58,'total_qty'=>50,'rate'=>1];
        $this->putJson('/api/mobile/v1/orders/1',$body)->assertOk()->assertJsonPath('data.rate',4500)
            ->assertJsonPath('data.tax_id',1)->assertJsonPath('data.is_tax_inclusive',true)->assertJsonPath('data.concrete_pump','pump');
        $this->assertEquals(58,DB::table('mm_sales_orders')->where('id',1)->value('customer_po_id'));
        $this->putJson('/api/mobile/v1/orders/1',array_replace($body,['mix_design_id'=>23]))
            ->assertUnprocessable()->assertJsonValidationErrors(['mix_design_id']);
        $this->assertEquals(1,DB::table('mm_sales_orders')->where('id',1)->value('mix_design_id'));
        DB::table('mm_customer_po_items')->where('customer_po_id',58)->update(['deleted_at'=>'2026-10-14 00:00:00']);
        $this->putJson('/api/mobile/v1/orders/1',$body)->assertUnprocessable()->assertJsonValidationErrors(['mix_design_id']);
        $this->putJson('/api/mobile/v1/orders/1',array_replace($body,['customer_po_id'=>null,'mix_design_id'=>23]))
            ->assertOk()->assertJsonPath('data.customer_po_id',null)->assertJsonPath('data.rate',1);
    }

    public function test_orders_can_be_created_and_updated_without_customer_po(): void
    {
        $body = ['customer_id'=>1,'site_id'=>1,'mix_design_id'=>1,'total_qty'=>10,'rate'=>4500];
        $id = $this->postJson('/api/mobile/v1/orders',$body)->assertCreated()
            ->assertJsonPath('data.customer_po_id',null)->assertJsonPath('data.rate',4500)->json('data.id');
        $this->putJson('/api/mobile/v1/orders/'.$id,array_replace($body,['rate'=>5000]))
            ->assertOk()->assertJsonPath('data.customer_po_id',null)->assertJsonPath('data.rate',5000);
        $this->postJson('/api/mobile/v1/orders',$body+['customer_po_id'=>null])->assertCreated();
        $missingRate = $body; unset($missingRate['rate']);
        $this->postJson('/api/mobile/v1/orders',$missingRate)->assertUnprocessable()->assertJsonValidationErrors(['rate'])
            ->assertJsonMissingValidationErrors(['customer_po_id']);
        $this->postJson('/api/mobile/v1/orders',$body+['customer_po_id'=>999])->assertUnprocessable()->assertJsonValidationErrors(['customer_po_id']);
    }

    public function test_quotation_status_and_approval_state_stay_in_sync(): void
    {
        $id = $this->postJson('/api/mobile/v1/quotations',$this->quote())->assertCreated()
            ->assertJsonPath('data.mobile_approval_state','draft')->json('data.id');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk()
            ->assertJsonPath('data.mobile_approval_state','pending');
        $this->assertEquals(1,DB::table('mm_quotations')->where('id',$id)->value('status'));
        DB::table('mm_quotations')->where('id',$id)->update(['sales_executive_id'=>2]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review',['decision'=>'rejected','comment'=>'Revise'])->assertOk()
            ->assertJsonPath('data.mobile_approval_state','rejected');
        $this->assertEquals(3,DB::table('mm_quotations')->where('id',$id)->value('status'));
        DB::table('mm_quotations')->where('id',$id)->update(['sales_executive_id'=>1]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token]);
        $this->putJson('/api/mobile/v1/quotations/'.$id,$this->quote())->assertOk()->assertJsonPath('data.mobile_approval_state','draft');
        $this->assertEquals(0,DB::table('mm_quotations')->where('id',$id)->value('status'));
        DB::table('mm_quotations')->where('id',$id)->update(['status'=>2,'mobile_approval_state'=>'draft']);
        $this->getJson('/api/mobile/v1/quotations/'.$id)->assertOk()->assertJsonPath('data.mobile_approval_state','approved');
        $this->getJson('/api/mobile/v1/quotations?approval_state=approved')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/mobile/v1/quotations?approval_state=draft')->assertOk()->assertJsonPath('data.total',0);
        $this->putJson('/api/mobile/v1/quotations/'.$id,$this->quote())->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/quotations/'.$id)->assertUnprocessable();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertUnprocessable();
    }

    public function test_quotation_actions_without_permissions_and_concise_mobile_errors(): void
    {
        config(['app.debug'=>true]);
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $id = $this->postJson('/api/mobile/v1/quotations',$this->quote())->assertCreated()->json('data.id');
        $this->getJson('/api/mobile/v1/quotations')->assertOk();
        $this->putJson('/api/mobile/v1/quotations/'.$id,$this->quote())->assertOk();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk();
        $this->putJson('/api/mobile/v1/quotations/'.$id,$this->quote())->assertUnprocessable()
            ->assertExactJson(['message'=>'Submitted or approved quotations cannot be edited or deleted.']);
        \Illuminate\Support\Facades\Route::get('/api/mobile/v1/testing-server-error', fn () => throw new \RuntimeException('Private database details'));
        $this->getJson('/api/mobile/v1/testing-server-error')->assertStatus(500)->assertExactJson(['message'=>'Server error.']);
        $this->postJson('/api/mobile/v1/auth/login',[])->assertUnprocessable()->assertJsonValidationErrors(['email','password'])
            ->assertJsonMissingPath('trace')->assertJsonMissingPath('exception')->assertJsonMissingPath('file');
    }

    public function test_login_requires_email_and_password_without_issuing_tokens(): void
    {
        $tokensBefore = DB::table('personal_access_tokens')->count();
        $this->withHeaders(['Authorization'=>''])->postJson('/api/mobile/v1/auth/login', [])
            ->assertUnprocessable()->assertExactJson([
                'message' => 'The email field is required. (and 1 more error)',
                'errors' => [
                    'email' => ['The email field is required.'],
                    'password' => ['The password field is required.'],
                ],
            ]);
        $this->postJson('/api/mobile/v1/auth/login', ['email'=>'user1@example.test'])
            ->assertUnprocessable()->assertJsonValidationErrors(['password'])->assertJsonMissingValidationErrors(['email']);
        $this->postJson('/api/mobile/v1/auth/login', ['password'=>'Test-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email'])->assertJsonMissingValidationErrors(['password']);
        $this->postJson('/api/mobile/v1/auth/login', ['email'=>'invalid-email','password'=>'Test-password-123'])
            ->assertUnprocessable()->assertJsonValidationErrors(['email']);
        $this->assertSame($tokensBefore, DB::table('personal_access_tokens')->count());
        Http::assertNothingSent();
    }

    public function test_login_profile_logout_and_token_revocation(): void
    {
        $this->postJson('/api/mobile/v1/auth/login',['email'=>'user1@example.test','password'=>'wrong'])->assertUnauthorized();
        $data=$this->postJson('/api/mobile/v1/auth/login',['email'=>'user1@example.test','password'=>'Test-password-123'])->assertOk()->json('data');
        Http::assertNothingSent();
        $this->assertArrayNotHasKey('password',$data['user']); $this->assertArrayNotHasKey('api_key',$data['user']);
        $this->withHeaders(['Authorization'=>'Bearer '.$data['token']])->getJson('/api/mobile/v1/me')->assertOk()->assertJsonPath('data.id',1);
        $this->postJson('/api/mobile/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/mobile/v1/me')->assertUnauthorized();
    }
    public function test_plant_membership_required_and_missing_header_rejected(): void
    {
        $this->withHeaders(['X-Plant-Id'=>'2'])->getJson('/api/mobile/v1/leads')->assertForbidden();
        $this->withHeaders(['X-Plant-Id'=>''])->getJson('/api/mobile/v1/leads')->assertUnprocessable();
        $this->withHeaders(['X-Plant-Id'=>'1'])->getJson('/api/mobile/v1/leads')->assertOk();
    }
    public function test_superadmin_can_login_and_access_active_plants_in_suspended_entities(): void
    {
        DB::table('mm_entities')->where('id',1)->update(['is_suspended'=>1]);
        DB::table('mm_roles')->insert(['id'=>3,'name'=>'Saas Owner','code'=>'SAAS_OWNER','guard_name'=>'web']);
        DB::table('mm_model_has_roles')->insert(['role_id'=>3,'model_id'=>2,'model_type'=>User::class]);
        DB::table('mm_entity_users')->where('user_id',2)->delete();
        $this->postJson('/api/mobile/v1/auth/login',['email'=>'user2@example.test','password'=>'Test-password-123'])->assertOk()->assertJsonCount(2,'data.plants');
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer,'X-Plant-Id'=>'1'])->getJson('/api/mobile/v1/dashboard')->assertOk();
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token,'X-Plant-Id'=>'1'])->getJson('/api/mobile/v1/dashboard')->assertForbidden();
        DB::table('mm_plants')->where('id',1)->update(['is_active'=>0]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer,'X-Plant-Id'=>'1'])->getJson('/api/mobile/v1/dashboard')->assertForbidden();
    }
    public function test_crm_crud_followup_completion_and_ownership(): void
    {
        $id=$this->postJson('/api/mobile/v1/leads',$this->lead())->assertCreated()->json('data.id');
        $this->putJson('/api/mobile/v1/leads/'.$id,array_replace($this->lead(),['status'=>'Contacted']))->assertOk()->assertJsonPath('data.status','Contacted');
        $activity=$this->postJson('/api/mobile/v1/follow-ups',['lead_id'=>$id,'type'=>'Site Visit','subject'=>'Visit','due_at'=>'2026-10-10 10:00:00'])->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/follow-ups/'.$activity.'/complete')->assertOk();
        $this->getJson('/api/mobile/v1/follow-ups?view=completed')->assertOk()->assertJsonPath('data.total',1);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer])->getJson('/api/mobile/v1/leads/'.$id)->assertNotFound();
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token])->deleteJson('/api/mobile/v1/leads/'.$id)->assertOk();
        $this->getJson('/api/mobile/v1/leads/'.$id)->assertNotFound();
    }
    public function test_context_permission_denial_does_not_use_another_plant_role(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        $this->getJson('/api/mobile/v1/leads')->assertForbidden();
    }
    public function test_tax_components_totals_and_separate_approval_user(): void
    {
        $id=$this->postJson('/api/mobile/v1/quotations',$this->quote())->assertCreated()->assertJsonPath('data.amount_total','1938.00')->json('data.id');
        $this->assertEquals(295.68,DB::table('mm_quotation_items')->where('quotation_id',$id)->value('tax_amount'));
        $this->assertEquals(-0.31,DB::table('mm_quotations')->where('id',$id)->value('adjustment'));
        $this->putJson('/api/mobile/v1/quotations/'.$id,array_replace($this->quote(),['notes'=>'Updated']))->assertOk();
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/submit')->assertOk()->assertJsonPath('data.mobile_approval_state','pending');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review',['decision'=>'approved'])->assertForbidden();
        $this->putJson('/api/mobile/v1/quotations/'.$id,$this->quote())->assertUnprocessable();
        DB::table('mm_quotations')->where('id',$id)->update(['sales_executive_id'=>2]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer])->postJson('/api/mobile/v1/quotations/'.$id.'/review',['decision'=>'approved'])->assertOk()->assertJsonPath('data.mobile_approval_state','approved');
        $this->postJson('/api/mobile/v1/quotations/'.$id.'/review',['decision'=>'approved'])->assertUnprocessable();
    }
    public function test_wrong_plant_and_purchase_taxes_cannot_be_selected(): void
    {
        DB::table('mm_taxes')->where('id',1)->update(['tax_type'=>'Purchase']);
        $this->postJson('/api/mobile/v1/quotations',$this->quote())->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
        DB::table('mm_taxes')->where('id',1)->update(['tax_type'=>'Sales','plant_id'=>2]);
        $this->postJson('/api/mobile/v1/quotations',$this->quote())->assertUnprocessable()->assertJsonValidationErrors('items.0.tax_id');
    }
    public function test_mobile_whatsapp_otp_is_passwordless_and_single_use(): void
    {
        $tokens=DB::table('personal_access_tokens')->count();
        $challenge=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'+91 9876543210'])->assertStatus(202)->assertJsonPath('data.channel','whatsapp')->assertJsonMissingPath('data.token')->json('data.challenge_id');
        $this->assertSame($tokens,DB::table('personal_access_tokens')->count());
        Http::assertSent(fn($r)=>$r['Contact']==='919876543210' && $r['Template']==='sales_executive_login' && preg_match('/^[0-9]{6}$/',$r['Param']));
        $entry=Cache::get('sales-mobile:'.$challenge); $entry['otp']=Hash::make('123456'); Cache::put('sales-mobile:'.$challenge,$entry,300);
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'111111'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertOk()->assertJsonPath('data.user.id',1);
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertUnprocessable();
    }
    public function test_expired_otp_and_attempt_limit_cannot_issue_tokens(): void
    {
        $tokens=DB::table('personal_access_tokens')->count();
        $challenge=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry=Cache::get('sales-mobile:'.$challenge); $entry['otp']=Hash::make('123456'); Cache::put('sales-mobile:'.$challenge,$entry,300);
        for($i=0;$i<5;$i++) $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'111111'])->assertUnprocessable();
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertStatus(429);
        $fresh=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->json('data.challenge_id');
        $this->travel(6)->minutes();
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$fresh,'otp'=>'123456'])->assertUnprocessable();
        $this->travelBack();
        $this->assertSame($tokens,DB::table('personal_access_tokens')->count());
    }
    public function test_whatsapp_failure_and_duplicate_mobile_do_not_authenticate(): void
    {
        $tokens=DB::table('personal_access_tokens')->count();
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['https://app.tendigit.in/*'=>Http::response(['status'=>'failed','message'=>'Invalid template'],200)]);
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(503)->assertJsonMissingPath('data.token');
        DB::table('mm_users')->where('id',2)->update(['mobile'=>'+91 9876543210']);
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertUnprocessable();
        $this->assertSame($tokens,DB::table('personal_access_tokens')->count());
    }
    public function test_successful_whatsapp_response_with_false_error_flag_is_accepted(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(['https://app.tendigit.in/*'=>Http::response(['error'=>false,'status'=>'success','message'=>'Message submitted'],200)]);
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->assertJsonPath('data.channel','whatsapp');
    }
    public function test_whatsapp_certificate_failure_returns_safe_specific_error(): void
    {
        Http::swap(new \Illuminate\Http\Client\Factory);
        Http::fake(fn()=>throw new \Illuminate\Http\Client\ConnectionException('cURL error 60: certificate subject name does not match'));
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(503)
            ->assertJsonPath('error_code','WHATSAPP_TLS_ERROR')->assertJsonMissingPath('exception')->assertJsonMissingPath('trace')->assertJsonMissingPath('data.challenge_id');
    }
    public function test_swagger_page_and_openapi_spec_are_available_without_login(): void
    {
        $this->get('/api/mobile/v1/docs')->assertOk()->assertSee('SwaggerUIBundle',false)->assertSee('X-Plant-Id');
        $spec=$this->getJson('/api/mobile/v1/openapi.json')->assertOk()->json();
        $this->assertSame('3.0.3',$spec['openapi']);
        $this->assertSame([], $spec['paths']['/auth/send-otp']['post']['security']);
        $this->assertArrayHasKey('202',$spec['paths']['/auth/send-otp']['post']['responses']);
        $this->assertArrayHasKey('bearerAuth',$spec['components']['securitySchemes']);
        $this->assertArrayHasKey('/quotations/{quotation}/review',$spec['paths']);
    }
    public function test_changed_or_inactive_mobile_account_cannot_verify(): void
    {
        $challenge=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry=Cache::get('sales-mobile:'.$challenge);$entry['otp']=Hash::make('123456');Cache::put('sales-mobile:'.$challenge,$entry,300);
        DB::table('mm_users')->where('id',1)->update(['mobile'=>'9876543219']);
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertUnprocessable();
        DB::table('mm_users')->where('id',1)->update(['is_active'=>0]);
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543219'])->assertForbidden();
    }
    public function test_production_progress_excludes_draft_cancelled_and_deleted_trips(): void
    {
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'sales_executive_id'=>1,'total_qty'=>40]);
        foreach ([1=>1,2=>4,3=>5] as $id=>$status) DB::table('mm_batches')->insert(['id'=>$id,'plant_id'=>1,'sales_order_id'=>1,'batch_size'=>6,'status'=>$status]);
        foreach ([1=>'Draft',2=>'Delivered',3=>'Cancelled'] as $id=>$status) DB::table('mm_dispatches')->insert(['id'=>$id,'plant_id'=>1,'sales_order_id'=>1,'batch_id'=>$id,'delivered_qty'=>6,'dispatch_status'=>$status]);
        $this->getJson('/api/mobile/v1/orders/1')->assertOk()->assertJsonPath('data.progress.completed_batches',1)->assertJsonPath('data.progress.committed_m3',12)->assertJsonPath('data.progress.delivered_m3',6)->assertJsonPath('data.progress.remaining_delivery_m3',34);
        DB::table('mm_batches')->where('id',2)->update(['deleted_at'=>now()]);
        $this->getJson('/api/mobile/v1/orders/1')->assertOk()->assertJsonPath('data.progress.delivered_m3',0);
    }
    public function test_dashboard_customers_and_order_mutation_guards(): void
    {
        foreach (['SALES_ORDER.CREATE','SALES_ORDER.UPDATE','SALES_ORDER.DELETE'] as $name) {
            $id=DB::table('mm_permissions')->insertGetId(['name'=>$name,'guard_name'=>'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
        }
        DB::table('mm_customer_pos')->insert(['id'=>1,'plant_id'=>1,'patron_id'=>1,'site_id'=>1,'created_by'=>1,'sales_executive_id'=>1,'is_tax_inclusive'=>1]);
        DB::table('mm_customer_po_items')->insert(['customer_po_id'=>1,'mix_design_id'=>1,'quantity'=>40,'rate'=>546,'tax_id'=>1,'pump_rate'=>0]);
        $body=['customer_id'=>1,'site_id'=>1,'mix_design_id'=>1,'customer_po_id'=>1,'total_qty'=>40];
        $id=$this->postJson('/api/mobile/v1/orders',$body+['rate'=>1])->assertCreated()->json('data.id');
        $this->assertEquals(546,DB::table('mm_sales_orders')->where('id',$id)->value('rate'));
        $this->putJson('/api/mobile/v1/orders/'.$id,$body)->assertOk();
        $this->getJson('/api/mobile/v1/dashboard')->assertOk()->assertJsonPath('data.active_orders',1);
        $this->getJson('/api/mobile/v1/customers')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/mobile/v1/customers/1')->assertOk()->assertJsonPath('data.progress.remaining_delivery_m3',40);
        DB::table('mm_batches')->insert(['plant_id'=>1,'sales_order_id'=>$id,'status'=>1,'batch_size'=>6]);
        $this->putJson('/api/mobile/v1/orders/'.$id,$body)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/orders/'.$id)->assertUnprocessable();
        $this->getJson('/api/mobile/v1/orders/not-a-number')->assertNotFound();
    }
    public function test_schedule_rejects_wrong_order_mix_and_linked_edits(): void
    {
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'sales_executive_id'=>1,'site_id'=>1,'mix_design_id'=>1,'total_qty'=>40,'status'=>1]);
        DB::table('mm_mix_designs')->insert(['id'=>2,'plant_id'=>1,'design_name'=>'M30','is_active'=>1]);
        $body=['sales_order_id'=>1,'site_id'=>1,'mix_design_id'=>2,'qty_m3'=>6,'order_volume_m3'=>40,'schedule_date'=>'2026-10-10','pump_type'=>'direct_pour'];
        $this->postJson('/api/mobile/v1/batch-schedules',$body)->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $body['mix_design_id']=1;
        $schedule=$this->postJson('/api/mobile/v1/batch-schedules',$body)->assertOk()->assertJsonPath('schedule.remaining_volume_m3','34.000')->json('schedule.id');
        $this->getJson('/api/mobile/v1/batch-schedules')->assertOk()->assertJsonPath('data.total',1);
        $this->getJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertOk()->assertJsonPath('data.id',$schedule);
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule,$body)->assertOk();
        $this->postJson('/api/mobile/v1/batch-schedules',array_replace($body,['qty_m3'=>35]))->assertUnprocessable()->assertJsonValidationErrors('qty_m3');
        DB::table('mm_concrete_batching_schedules')->where('id',$schedule)->update(['batch_id'=>1]);
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule,$body)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertUnprocessable();
        DB::table('mm_concrete_batching_schedules')->where('id',$schedule)->update(['batch_id'=>null]);
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertOk();
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertNotFound()
            ->assertExactJson(['message'=>'Batch schedule not found.']);
    }
    public function test_missing_and_inaccessible_schedules_return_concise_errors(): void
    {
        config(['app.debug'=>true]);
        DB::table('mm_sales_orders')->insert([
            ['id'=>1,'plant_id'=>1,'created_by'=>2], ['id'=>2,'plant_id'=>2,'created_by'=>1],
        ]);
        DB::table('mm_concrete_batching_schedules')->insert([
            ['id'=>1,'plant_id'=>1,'sales_order_id'=>1,'status'=>'scheduled'],
            ['id'=>2,'plant_id'=>2,'sales_order_id'=>2,'status'=>'scheduled'],
        ]);
        foreach ([1,2,999] as $id) {
            $url = '/api/mobile/v1/batch-schedules/'.$id;
            $this->getJson($url)->assertNotFound()->assertExactJson(['message'=>'Batch schedule not found.']);
            $this->putJson($url,[])->assertNotFound()->assertExactJson(['message'=>'Batch schedule not found.']);
            $this->deleteJson($url)->assertNotFound()->assertExactJson(['message'=>'Batch schedule not found.']);
        }
        $this->assertEquals(2,DB::table('mm_concrete_batching_schedules')->whereNull('deleted_at')->count());
    }
    public function test_approval_migration_and_route_compilation(): void
    {
        Schema::table('mm_quotations',fn(Blueprint $t)=>$t->dropColumn(['mobile_approval_state','mobile_submitted_by','mobile_submitted_at','mobile_reviewed_by','mobile_reviewed_at','mobile_approval_comment']));
        $migration=require base_path('database/migrations/2026_10_09_180500_add_sales_mobile_quotation_approval.php');
        $migration->up();
        $this->assertTrue(Schema::hasColumn('mm_quotations','mobile_approval_state'));
        $this->assertTrue(DB::table('mm_permissions')->where('name','CRM_LEAD.VIEW_ALL')->exists());
        $migration->down();
        $this->assertFalse(Schema::hasColumn('mm_quotations','mobile_approval_state'));
        $routes=new \Illuminate\Routing\RouteCollection;
        foreach(app('router')->getRoutes() as $route) if(str_starts_with($route->uri(),'api/mobile/v1/')) $routes->add($route);
        $this->assertIsArray($routes->compile());
    }
    public function test_dropdowns_marketing_and_revoked_membership(): void
    {
        foreach (['TCS', 'CESS'] as $index => $group) {
            DB::table('mm_taxes')->insert(['id'=>900+$index,'plant_id'=>1,'tax_name'=>$group,'tax_group'=>$group,'tax_type'=>'Sales','tax_rate'=>1,'parent_id'=>null,'status'=>1]);
            DB::table('mm_taxes')->insert(['id'=>910+$index,'plant_id'=>1,'tax_name'=>$group.' child','tax_group'=>$group,'tax_type'=>'Sales','tax_rate'=>1,'parent_id'=>1,'status'=>1]);
        }
        $this->getJson('/api/mobile/v1/lookups')->assertOk()->assertJsonCount(1,'data.sales_taxes')->assertJsonCount(2,'data.sales_taxes.0.children');
        $this->getJson('/api/mobile/v1/taxes')->assertOk()->assertJsonCount(1,'data.data')->assertJsonCount(2,'data.data.0.children');
        DB::table('mm_role_has_permissions')->where('role_id',1)->whereIn('permission_id',DB::table('mm_permissions')->where('name','BATCHING_SCHEDULE.VIEW')->select('id'))->delete();
        $this->getJson('/api/mobile/v1/lookups')->assertOk()->assertJsonPath('data.sales_taxes.0.tax_type','Sales')->assertJsonPath('data.mix_designs.0.design_name','M25');
        $this->postJson('/api/mobile/v1/leads',$this->lead())->assertCreated();
        $this->getJson('/api/mobile/v1/marketing/summary')->assertOk()->assertJsonPath('data.by_source.0.total',1);
        DB::table('mm_entity_users')->where('user_id',1)->update(['deleted_at'=>now()]);
        $this->getJson('/api/mobile/v1/dashboard')->assertForbidden();
    }
    private function makeReviewerSystemAdmin(): void
    {
        DB::table('mm_roles')->insert(['id'=>3,'name'=>'Saas Owner','code'=>'SAAS_OWNER','guard_name'=>'web']);
        DB::table('mm_model_has_roles')->insert(['role_id'=>3,'model_id'=>2,'model_type'=>User::class]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
    }
    public function test_admin_can_grant_by_email_and_revoke_mobile_sessions_without_changing_roles(): void
    {
        $webToken=User::find(1)->createToken('web-login',['*']);
        DB::table('mm_users')->where('id',1)->update(['is_otp_enabled'=>0]);
        $this->postJson('/api/mobile/v1/auth/login',['email'=>'user1@example.test','password'=>'Test-password-123'])->assertForbidden();
        $this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertForbidden();
        $this->makeReviewerSystemAdmin();
        $this->putJson('/testing/mobile-access',['email'=>'USER1@example.test','enabled'=>true])->assertOk()->assertJsonPath('data.is_otp_enabled',true);
        $this->assertSame('1',(string)DB::table('mm_entity_users')->where('user_id',1)->value('role_id'));
        $this->putJson('/testing/mobile-access',['email'=>'user1@example.test','enabled'=>false])->assertOk()->assertJsonPath('data.is_otp_enabled',false);
        $this->assertEquals(0,User::find(1)->tokens()->where('name','like','sales-mobile:%')->count());
        $this->assertTrue(DB::table('personal_access_tokens')->where('id',$webToken->accessToken->id)->exists());
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token])->getJson('/api/mobile/v1/me')->assertUnauthorized();
    }
    public function test_revoked_mobile_access_invalidates_requests_and_pending_otp_even_after_regrant(): void
    {
        $challenge=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry=Cache::get('sales-mobile:'.$challenge);$entry['otp']=Hash::make('123456');Cache::put('sales-mobile:'.$challenge,$entry,300);
        $this->makeReviewerSystemAdmin();
        $this->putJson('/testing/mobile-access',['email'=>'user1@example.test','enabled'=>false])->assertOk();
        $this->putJson('/testing/mobile-access',['email'=>'user1@example.test','enabled'=>true])->assertOk();
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertForbidden();
        DB::table('mm_users')->where('id',2)->update(['is_otp_enabled'=>0]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
        $this->getJson('/api/mobile/v1/plants')->assertForbidden();
    }
    public function test_mobile_access_is_checked_for_the_bearer_token_owner(): void
    {
        DB::table('mm_users')->where('id',1)->update(['is_otp_enabled'=>0]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token])->getJson('/api/mobile/v1/me')
            ->assertForbidden()->assertJsonPath('message', 'Mobile app access has not been granted or has been removed. Contact your administrator.');
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer])->getJson('/api/mobile/v1/me')
            ->assertOk()->assertJsonPath('data.id',2)->assertJsonPath('data.email','user2@example.test');
        DB::table('mm_users')->where('id',1)->update(['is_otp_enabled'=>1]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token])->getJson('/api/mobile/v1/me')
            ->assertOk()->assertJsonPath('data.id',1);
    }

    public function test_regular_users_cannot_grant_themselves_mobile_access(): void
    {
        $this->putJson('/testing/mobile-access',['email'=>'user1@example.test','enabled'=>true])->assertForbidden();
    }
    public function test_entity_admin_can_manage_members_but_not_another_entity(): void
    {
        session(['active_entity_id'=>1,'active_plant_id'=>1]);
        DB::table('mm_roles')->where('id',2)->update(['code'=>'ADMINISTRATOR']);
        DB::table('mm_users')->insert(['id'=>3,'username'=>'Other','email'=>'other@example.test','is_active'=>1,'is_otp_enabled'=>0]);
        DB::table('mm_entity_users')->insert(['user_id'=>3,'entity_id'=>2,'plant_id'=>2,'role_id'=>1]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->reviewer]);
        $this->putJson('/testing/mobile-access',['email'=>'user1@example.test','enabled'=>false])->assertOk();
        $this->putJson('/testing/mobile-access',['email'=>'other@example.test','enabled'=>true])->assertNotFound();
        $this->assertEquals(0,DB::table('mm_users')->where('id',3)->value('is_otp_enabled'));
    }
    public function test_mobile_access_migration_defaults_to_denied(): void
    {
        Schema::table('mm_users',fn(Blueprint $t)=>$t->dropColumn('mobile_access_revision'));
        $migration=require base_path('database/migrations/2026_10_12_131000_add_mobile_access_to_users.php');
        $migration->up();
        $this->assertEquals(0,DB::table('mm_users')->where('id',1)->value('mobile_access_enabled'));
        $migration->down();
        $this->assertFalse(Schema::hasColumn('mm_users','mobile_access_enabled'));
    }
    public function test_otp_flag_migration_preserves_grants_and_removes_old_column(): void
    {
        Schema::table('mm_users', fn(Blueprint $t) => $t->boolean('mobile_access_enabled')->default(false));
        DB::table('mm_users')->where('id',1)->update(['is_otp_enabled'=>0,'mobile_access_enabled'=>1,'mobile_access_revision'=>4]);
        DB::table('mm_users')->where('id',2)->update(['is_otp_enabled'=>1,'mobile_access_enabled'=>0]);
        DB::table('mm_users')->insert(['id'=>3,'username'=>'Denied','email'=>'denied@example.test','is_otp_enabled'=>0,'mobile_access_enabled'=>0]);
        $migration=require base_path('database/migrations/2026_10_12_153300_use_otp_enabled_for_mobile_access.php');
        $migration->up();
        $this->assertFalse(Schema::hasColumn('mm_users','mobile_access_enabled'));
        $this->assertEquals(1,User::find(1)->is_otp_enabled);
        $this->assertEquals(1,User::find(2)->is_otp_enabled);
        $this->assertEquals(0,User::find(3)->is_otp_enabled);
        $this->assertEquals(4,User::find(1)->mobile_access_revision);
        $migration->down();
        $this->assertEquals(1,DB::table('mm_users')->where('id',1)->value('mobile_access_enabled'));
        $this->assertEquals(0,DB::table('mm_users')->where('id',3)->value('mobile_access_enabled'));
    }
    public function test_user_edit_otp_flag_revokes_mobile_tokens_and_invalidates_pending_challenge(): void
    {
        $challenge=$this->postJson('/api/mobile/v1/auth/send-otp',['mobile'=>'9876543210'])->assertStatus(202)->json('data.challenge_id');
        $entry=Cache::get('sales-mobile:'.$challenge);$entry['otp']=Hash::make('123456');Cache::put('sales-mobile:'.$challenge,$entry,300);
        $webToken=User::find(1)->createToken('web-login',['*']);
        $user=User::find(1);
        $user->update(['is_otp_enabled'=>false]);
        $this->assertEquals(1,$user->mobile_access_revision);
        $this->assertEquals(0,$user->tokens()->where('name','like','sales-mobile:%')->count());
        $this->assertTrue(DB::table('personal_access_tokens')->where('id',$webToken->accessToken->id)->exists());
        $user->update(['is_otp_enabled'=>true]);
        $this->assertEquals(2,$user->mobile_access_revision);
        $this->postJson('/api/mobile/v1/auth/verify-otp',['challenge_id'=>$challenge,'otp'=>'123456'])->assertForbidden();
    }
}
