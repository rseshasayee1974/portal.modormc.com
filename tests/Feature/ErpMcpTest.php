<?php
/*
Author: ragul-onemodo
Created: 2026-10-13 10:56:29 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\User;
use App\Services\Reports\{ReportServiceFactory, ReportServiceInterface};
use Illuminate\Support\Facades\{DB, Cache, Schema};
use Illuminate\Database\Schema\Blueprint;

/** Reuse the isolated sales-domain fixture and its regression checks. */
class ErpMcpTest extends SalesMobileApiTest
{
    private ?string $mcpToken = null;

    protected function setUp(): void
    {
        parent::setUp();
        Schema::create('mm_crm_deals', function (Blueprint $table) {
            $table->id();
            foreach (['plant_id','lead_id','stage','expected_value','created_by','updated_by','deleted_by'] as $field) $table->text($field)->nullable();
            $table->timestamps(); $table->softDeletes();
        });
    }


    private function rpc(string $method, array $params = [], array $headers = [])
    {
        $this->mcpToken ??= User::find(1)->createToken('erp-mcp:test',['erp-mcp'],now()->addDay())->plainTextToken;
        return $this->withHeaders(array_merge(['Authorization'=>'Bearer '.$this->mcpToken,'X-Plant-Id'=>'1','Accept'=>'application/json','MCP-Protocol-Version'=>'2025-11-25'], $headers))
            ->postJson('/mcp/erp',['jsonrpc'=>'2.0','id'=>1,'method'=>$method,'params'=>$params]);
    }

    private function callTool(string $name, array $arguments = [], array $headers = [])
    {
        return $this->rpc('tools/call',['name'=>$name,'arguments'=>$arguments],$headers);
    }

    private function leadData(): array
    {
        return ['contact_name'=>'MCP Customer','phone'=>'9988776655','enquiry_date'=>'2026-10-13','source'=>'Referral','priority'=>'High','status'=>'New','expected_value'=>50000];
    }

    private function payload($response): array
    {
        $response->assertOk()->assertJsonPath('result.isError',false);
        return json_decode($response->json('result.content.0.text'),true,512,JSON_THROW_ON_ERROR);
    }

    private function grantReport(string $name): void
    {
        $id=DB::table('mm_permissions')->insertGetId(['name'=>'REPORT_'.strtoupper($name).'.VIEW','guard_name'=>'web']);
        DB::table('mm_role_has_permissions')->insert(['role_id'=>1,'permission_id'=>$id]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_mcp_initialization_and_permission_filtered_tools(): void
    {
        $this->rpc('initialize',['protocolVersion'=>'2025-11-25','capabilities'=>new \stdClass,'clientInfo'=>['name'=>'test','version'=>'1']])->assertOk()->assertJsonPath('result.serverInfo.name','Modo ERP');
        $names=array_column($this->rpc('tools/list')->assertOk()->json('result.tools'),'name');
        $this->assertContains('list_crm_leads',$names);
        $this->assertContains('sales_order_batch_progress',$names);
        $this->assertNotContains('run_report',$names);
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        $this->assertSame([], $this->rpc('tools/list')->assertOk()->json('result.tools'));
        $this->callTool('create_crm_lead',$this->leadData())->assertOk()->assertJsonPath('error.code',-32602);
    }

    public function test_mcp_requires_dedicated_token_and_valid_plant(): void
    {
        $this->withHeaders(['Authorization'=>'','X-Plant-Id'=>'1'])->postJson('/mcp/erp',['jsonrpc'=>'2.0','id'=>1,'method'=>'tools/list'])->assertUnauthorized();
        $mobile=User::find(1)->createToken('sales-mobile:test',['sales-mobile'])->plainTextToken;
        $this->rpc('tools/list',[],['Authorization'=>'Bearer '.$mobile])->assertForbidden();
        $this->rpc('tools/list',[],['X-Plant-Id'=>'2'])->assertForbidden();
        $this->rpc('tools/list',[],['X-Plant-Id'=>''])->assertUnprocessable();
        $this->rpc('tools/list',[],['Origin'=>'https://untrusted.example'])->assertForbidden();
        DB::table('mm_users')->where('id',1)->update(['is_active'=>0]);
        $this->rpc('tools/list')->assertForbidden();
    }

    public function test_mcp_lead_create_read_patch_and_cross_user_isolation(): void
    {
        $created=$this->payload($this->callTool('create_crm_lead',$this->leadData()));$id=$created['data']['id'];
        $this->assertEquals(1,$created['data']['plant_id']);
        $this->assertEquals(1,$created['data']['assigned_to']);
        $updated=$this->payload($this->callTool('update_crm_lead',['lead_id'=>$id,'project_name'=>'Updated project']));
        $this->assertSame('Updated project',$updated['data']['project_name']);
        $this->assertSame('MCP Customer',$updated['data']['contact_name']);
        $this->assertEquals($id,$this->payload($this->callTool('get_crm_lead',['lead_id'=>$id]))['data']['id']);
        DB::table('mm_crm_leads')->insert(['id'=>50,'plant_id'=>2,'assigned_to'=>1,'contact_name'=>'Other plant']);
        DB::table('mm_crm_leads')->insert(['id'=>51,'plant_id'=>1,'assigned_to'=>2,'contact_name'=>'Other owner']);
        foreach([50,51] as $other)$this->callTool('get_crm_lead',['lead_id'=>$other])->assertOk()->assertJsonPath('result.isError',true);
        $list=$this->payload($this->callTool('list_crm_leads',['per_page'=>10]));
        $this->assertCount(1,$list['data']['data']);
        $this->callTool('create_crm_lead',$this->leadData()+['plant_id'=>2])->assertOk()->assertJsonPath('result.isError',true);
        $this->callTool('create_crm_lead',$this->leadData()+['assigned_to'=>2])->assertOk()->assertJsonPath('result.isError',true);
    }

    public function test_mcp_followups_create_patch_complete_and_scope(): void
    {
        $id=$this->payload($this->callTool('create_crm_lead',$this->leadData()))['data']['id'];
        $followup=$this->payload($this->callTool('create_follow_up',['lead_id'=>$id,'type'=>'Call','subject'=>'Call customer','due_at'=>'2026-10-14 10:00:00']));
        $activity=$followup['data']['id'];
        $edit=$this->payload($this->callTool('update_follow_up',['activity_id'=>$activity,'subject'=>'Call revised']));
        $this->assertSame('Call revised',$edit['data']['subject']);
        $this->assertCount(1,$this->payload($this->callTool('list_follow_ups',['view'=>'all']))['data']['data']);
        $complete=$this->payload($this->callTool('complete_follow_up',['activity_id'=>$activity]));
        $this->assertNotNull($complete['data']['completed_at']);
        $this->payload($this->callTool('complete_follow_up',['activity_id'=>$activity]));
        $this->callTool('update_follow_up',['activity_id'=>$activity,'subject'=>'Forbidden edit'])->assertOk()->assertJsonPath('result.isError',true);
        DB::table('mm_crm_activities')->insert(['id'=>100,'plant_id'=>2,'lead_id'=>$id,'type'=>'Call','subject'=>'Other plant']);
        $this->callTool('complete_follow_up',['activity_id'=>100])->assertOk()->assertJsonPath('result.isError',true);
        $this->callTool('create_follow_up',['lead_id'=>$id,'type'=>'System','subject'=>'Forged system note'])->assertOk()->assertJsonPath('result.isError',true);
    }

    public function test_mcp_order_progress_and_batches_are_scoped(): void
    {
        DB::table('mm_sales_orders')->insert(['id'=>1,'plant_id'=>1,'created_by'=>1,'total_qty'=>40]);
        DB::table('mm_sales_orders')->insert(['id'=>2,'plant_id'=>2,'created_by'=>1,'total_qty'=>90]);
        DB::table('mm_sales_orders')->insert(['id'=>3,'plant_id'=>1,'created_by'=>2,'total_qty'=>90]);
        foreach([1=>1,2=>4,3=>5] as $id=>$status)DB::table('mm_batches')->insert(['id'=>$id,'plant_id'=>1,'sales_order_id'=>1,'batch_no'=>$id,'batch_size'=>6,'status'=>$status]);
        $progress=$this->payload($this->callTool('sales_order_batch_progress',['order_id'=>1]));
        $this->assertEquals(3,$progress['data']['progress']['batch_count']);
        $this->assertEquals(1,$progress['data']['progress']['completed_batches']);
        $this->assertCount(3,$this->payload($this->callTool('list_sales_order_batches',['order_id'=>1]))['data']['data']);
        foreach([2,3] as $other)$this->callTool('sales_order_batch_progress',['order_id'=>$other])->assertOk()->assertJsonPath('result.isError',true);
    }

    public function test_mcp_reports_use_report_permission_and_header_plant(): void
    {
        $this->grantReport('sales_register');
        $this->assertSame(['sales_register'],array_column($this->payload($this->callTool('list_reports'))['reports'],'type'));
        $factory=$this->mock(ReportServiceFactory::class);
        $service=new class implements ReportServiceInterface {
            public function targetName(array $params): string { return 'Test report'; }
            public function generate(array $params): array { return ['plant_id'=>$params['plant_id'],'data'=>[['n'=>1],['n'=>2],['n'=>3]],'totals'=>['amount'=>100]]; }
        };
        $factory->shouldReceive('make')->once()->with('sales_register')->andReturn($service);
        $report=$this->payload($this->callTool('run_report',['report'=>'sales_register','start_date'=>'2026-10-01','end_date'=>'2026-10-13','per_page'=>2,'page'=>2]));
        $this->assertEquals(1,$report['plant_id']);
        $this->assertSame([['n'=>3]],$report['data']['data']);
        $this->assertEquals(100,$report['data']['totals']['amount']);
        $this->callTool('run_report',['report'=>'customer_outstanding','start_date'=>'2026-10-01','end_date'=>'2026-10-13'])->assertOk()->assertJsonPath('result.isError',true);
        $this->callTool('run_report',['report'=>'sales_register','start_date'=>'2026-10-01','end_date'=>'2026-10-13','plant_id'=>2])->assertOk()->assertJsonPath('result.isError',true);
        $this->callTool('run_report',['report'=>'sales_register','start_date'=>'2026-10-01','end_date'=>'2026-10-13','patron_id'=>999])->assertOk()->assertJsonPath('result.isError',true);
    }

    public function test_mcp_token_revocation_and_locked_account(): void
    {
        $this->rpc('tools/list')->assertOk();
        User::find(1)->tokens()->where('name','like','erp-mcp:%')->delete();
        $this->rpc('tools/list')->assertUnauthorized();
        $this->mcpToken=null;
        DB::table('mm_users')->where('id',1)->update(['lockout_until'=>now()->addMinutes(10)]);
        $this->rpc('tools/list')->assertForbidden();
    }
    public function test_mcp_context_is_restored_and_global_role_cannot_override_context_permissions(): void
    {
        session(['active_plant_id'=>99,'active_entity_id'=>99]);
        $this->rpc('tools/list')->assertOk();
        $this->assertEquals(99,session('active_plant_id'));
        $this->assertEquals(99,session('active_entity_id'));
        DB::table('mm_model_has_roles')->insert(['role_id'=>2,'model_id'=>1,'model_type'=>User::class]);
        DB::table('mm_role_has_permissions')->where('role_id',1)->delete();
        $this->assertSame([], $this->rpc('tools/list')->assertOk()->json('result.tools'));
    }

    public function test_mcp_token_command_revokes_only_mcp_tokens(): void
    {
        $before=User::find(1)->tokens()->where('name','like','sales-mobile:%')->count();
        $this->artisan('mcp:erp-token',['email'=>'user1@example.test','--days'=>1])->assertSuccessful();
        $this->assertEquals(1,User::find(1)->tokens()->where('name','like','erp-mcp:%')->count());
        $this->artisan('mcp:erp-token',['email'=>'user1@example.test','--revoke'=>true])->assertSuccessful();
        $this->assertEquals(0,User::find(1)->tokens()->where('name','like','erp-mcp:%')->count());
        $this->assertEquals($before,User::find(1)->tokens()->where('name','like','sales-mobile:%')->count());
        $this->artisan('mcp:erp-token',['email'=>'user1@example.test','--days'=>100])->assertFailed();
    }

    public function test_mcp_register_pagination_is_not_applied_twice(): void
    {
        $this->grantReport('sales_register');
        $factory=$this->mock(ReportServiceFactory::class);
        $service=new class implements ReportServiceInterface {
            public function targetName(array $params): string { return 'Register'; }
            public function generate(array $params): array {
                return ['data'=>[['n'=>3]],'pagination'=>['current_page'=>$params['page'],'per_page'=>$params['per_page'],'total'=>3],'totals'=>['amount'=>100]];
            }
        };
        $factory->shouldReceive('make')->once()->with('sales_register')->andReturn($service);
        $report=$this->payload($this->callTool('run_report',['report'=>'sales_register','start_date'=>'2026-10-01','end_date'=>'2026-10-13','per_page'=>2,'page'=>2]));
        $this->assertSame([['n'=>3]],$report['data']['data']);
        $this->assertEquals(3,$report['pagination']['data']['total']);
        $this->assertEquals(2,$report['pagination']['data']['current_page']);
    }

}
