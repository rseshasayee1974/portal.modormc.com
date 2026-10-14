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
            'mm_users' => ['username','email','mobile','password','is_active','is_otp_enabled','last_login','lockout_until','default_plant_id','default_entity_id','two_factor_confirmed_at','mobile_access_revision'],
            'mm_entities' => ['name','is_suspended'], 'mm_plants' => ['name','entity_id','is_active','gstin'],
            'mm_personnels' => ['plant_id','user_id','first_name','last_name'], 'mm_roles' => ['name','code','guard_name'],
            'mm_permissions' => ['name','guard_name','module','description','is_system'], 'mm_entity_users' => ['user_id','entity_id','plant_id','role_id'],
            'mm_crm_leads' => ['plant_id','lead_number','enquiry_date','contact_name','company_name','phone','email','address','source','status','priority','assigned_to','project_name','requirement','estimated_quantity','delivery_location','expected_value','expected_purchase_date','remarks','lost_reason','customer_id','converted_at','created_by','updated_by','deleted_by'],
            'mm_crm_activities' => ['plant_id','lead_id','type','subject','description','due_at','completed_at','assigned_to','created_by','updated_by','deleted_by'],
            'mm_patrons' => ['plant_id','legal_name','patron_type','status','operational_status'], 'mm_sites' => ['plant_id','name'],
            'mm_mix_designs' => ['plant_id','design_name','design_code','is_active'], 'mm_product_units' => ['plant_id','name'],
            'mm_taxes' => ['plant_id','tax_name','tax_type','tax_rate','parent_id','status','tax_group'],
            'mm_quotations' => ['plant_id','reference','patron_id','site_id','quote_date','validity_date','notes','is_tax_inclusive','sales_executive_id','adjustment','status','is_customer_po','amount_untaxed','amount_tax','amount_total','created_by','updated_by','deleted_by','mobile_approval_state','mobile_submitted_by','mobile_submitted_at','mobile_reviewed_by','mobile_reviewed_at','mobile_approval_comment'],
            'mm_quotation_items' => ['discount_type','discount','discount_amount','quotation_id','mix_design_id','uom_id','quantity','rate','tax_id','tax_amount','untaxed_amount','amount_total','concrete_pump','pump_rate','created_by','updated_by','deleted_by'],
            'mm_contacts' => ['patron_id','plant_id','name','phone'],
            'mm_customer_pos' => ['plant_id','quotation_id','patron_id','site_id','created_by','sales_executive_id','is_tax_inclusive','reference','customer_po_reference','status','order_date','amount_untaxed','amount_tax','amount_total'],
            'mm_customer_po_items' => ['customer_po_id','mix_design_id','rate','tax_id','quantity','concrete_pump','pump_rate','untaxed_amount','tax_amount','amount_total'],
            'mm_custom_settings' => ['plant_id','module_name','settings'],
            'mm_batches' => ['plant_id','sales_order_id','batch_no','batch_size','status'],
            'mm_sales_orders' => ['plant_id','created_by','updated_by','deleted_by','sales_executive_id','customer_id','site_id','mix_design_id','total_qty','rate','tax_id','is_tax_inclusive','customer_po_id','produced_qty','status','order_no','prefix','concrete_pump','pump_rate','scheduled_start','scheduled_end'],
            'mm_dispatches' => ['plant_id','sales_order_id','batch_id','dispatch_status','delivered_qty','dispatch_time','delivery_time'],
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
        foreach (['CRM_LEAD.VIEW','CRM_LEAD.CREATE','CRM_LEAD.UPDATE','CRM_LEAD.DELETE','QUOTATION.VIEW','QUOTATION.CREATE','QUOTATION.UPDATE','QUOTATION.DELETE','QUOTATION.APPROVE','QUOTATION.VIEW_ALL','SALES_ORDER.VIEW','BATCHING_SCHEDULE.VIEW','BATCHING_SCHEDULE.UPDATE','PATRON.VIEW'] as $i=>$name) {
            DB::table('mm_permissions')->insert(['id'=>$i+1,'name'=>$name,'guard_name'=>'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id'=>2,'permission_id'=>$i+1]);
            if (!in_array($name,['QUOTATION.APPROVE','QUOTATION.VIEW_ALL'])) DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$i+1]);
        }
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->token = User::find(1)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        $this->reviewer = User::find(2)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        DB::table('mm_patrons')->insert(['id'=>1,'plant_id'=>1,'legal_name'=>'Customer','patron_type'=>'["Customer"]','status'=>1,'operational_status'=>'active']);
        DB::table('mm_sites')->insert(['id'=>1,'plant_id'=>1,'name'=>'Site']);
        DB::table('mm_mix_designs')->insert(['id'=>1,'plant_id'=>1,'design_name'=>'M25','is_active'=>1]);
        DB::table('mm_taxes')->insert([['id'=>1,'plant_id'=>1,'tax_name'=>'GST18','tax_type'=>'Sales','tax_rate'=>18,'parent_id'=>null,'status'=>1],['id'=>2,'plant_id'=>1,'tax_name'=>'CGST9','tax_type'=>'Sales','tax_rate'=>9,'parent_id'=>1,'status'=>1],['id'=>3,'plant_id'=>1,'tax_name'=>'SGST9','tax_type'=>'Sales','tax_rate'=>9,'parent_id'=>1,'status'=>1]]);
        $this->withHeaders(['Authorization'=>'Bearer '.$this->token,'X-Plant-Id'=>'1']);
    }
    private function lead(): array { return ['contact_name'=>'Arun','phone'=>'9876543210','email'=>null,'enquiry_date'=>'2026-10-09','source'=>'Referral','priority'=>'High','status'=>'New','expected_value'=>10000]; }
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
            ->assertJsonPath('data.total',2)->assertJsonPath('data.data.0.id',2)
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
        $this->getJson('/api/mobile/v1/customer-pos')->assertOk()->assertJsonPath('data.total',3);
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        $this->getJson('/api/mobile/v1/customer-pos')->assertForbidden();
        $this->withHeaders(['Authorization'=>''])->getJson('/api/mobile/v1/customer-pos')->assertUnauthorized();
    }
    private function quote(): array { return ['reference'=>'QT-TEST-1','patron_id'=>1,'site_id'=>1,'quote_date'=>'2026-10-09','is_tax_inclusive'=>true,'items'=>[['mix_design_id'=>1,'quantity'=>3.55,'rate'=>546,'tax_id'=>1]]]; }
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
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'total_qty'=>40]);
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
        DB::table('mm_customer_pos')->insert(['id'=>1,'plant_id'=>1,'patron_id'=>1,'site_id'=>1,'created_by'=>1,'is_tax_inclusive'=>1]);
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
        foreach(['BATCHING_SCHEDULE.CREATE','BATCHING_SCHEDULE.DELETE'] as $name) {
            $id=DB::table('mm_permissions')->insertGetId(['name'=>$name,'guard_name'=>'web']);
            DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
        }
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'site_id'=>1,'mix_design_id'=>1,'total_qty'=>40,'status'=>1]);
        DB::table('mm_mix_designs')->insert(['id'=>2,'plant_id'=>1,'design_name'=>'M30','is_active'=>1]);
        $body=['sales_order_id'=>1,'site_id'=>1,'mix_design_id'=>2,'qty_m3'=>6,'order_volume_m3'=>40,'schedule_date'=>'2026-10-10','pump_type'=>'direct_pour'];
        $this->postJson('/api/mobile/v1/batch-schedules',$body)->assertUnprocessable()->assertJsonValidationErrors('sales_order_id');
        $body['mix_design_id']=1;
        $schedule=$this->postJson('/api/mobile/v1/batch-schedules',$body)->assertOk()->assertJsonPath('schedule.remaining_volume_m3','34.000')->json('schedule.id');
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule,$body)->assertOk();
        $this->postJson('/api/mobile/v1/batch-schedules',array_replace($body,['qty_m3'=>35]))->assertUnprocessable()->assertJsonValidationErrors('qty_m3');
        DB::table('mm_concrete_batching_schedules')->where('id',$schedule)->update(['batch_id'=>1]);
        $this->putJson('/api/mobile/v1/batch-schedules/'.$schedule,$body)->assertUnprocessable();
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertUnprocessable();
        DB::table('mm_concrete_batching_schedules')->where('id',$schedule)->update(['batch_id'=>null]);
        $this->deleteJson('/api/mobile/v1/batch-schedules/'.$schedule)->assertOk();
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
