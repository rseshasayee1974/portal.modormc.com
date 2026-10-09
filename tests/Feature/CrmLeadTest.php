<?php
/*
Author: ragul-onemodo
Created: 2026-10-09 12:35:50 Asia/Calcutta (UTC+05:30)
*/
namespace Tests\Feature;

use App\Models\{CrmLead, CrmActivity, CrmDeal, Patron, Plant, User, EntityUser, ContactType, AddressType, Permission, Role};
use App\Services\{CrmLeadService, InstallCrmModule};
use Illuminate\Foundation\Testing\{RefreshDatabase, TestCase};
use Illuminate\Support\Facades\{DB, Storage};
use Illuminate\Http\UploadedFile;

class CrmLeadTest extends TestCase
{
    use RefreshDatabase;
    private Plant $plant;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware([
            \App\Http\Middleware\SetEntityContext::class,
            \App\Http\Middleware\RequireOtpVerification::class,
            \App\Http\Middleware\SetEntityTimezone::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
        $this->plant = Plant::factory()->create();
        // Mirror the seeded Master menu ID used by AuthorizesModule.
        DB::table('mm_menus')->whereIn('alias', ['crm', 'crm-leads'])->delete();
        DB::table('mm_menus')->updateOrInsert(['id' => 2], ['alias' => 'master', 'title' => 'Master', 'link' => 'master/addresstypes', 'menutype' => 1, 'parent_id' => 0, 'level' => 0, 'published' => 1, 'permission_name' => 'MASTER.ALL']);
        InstallCrmModule::run();
        $this->user = User::factory()->create(['is_active' => true]);
        session(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
        $this->actingAs($this->user);
        $this->user->givePermissionTo(Permission::where('module', 'CRM_LEAD')->get());
        ContactType::firstOrCreate(['id' => 1], ['type' => 'Office']);
        AddressType::firstOrCreate(['id' => 1], ['type' => 'Billing']);
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'enquiry_date' => '2026-10-09', 'contact_name' => 'CRM Contact', 'company_name' => 'CRM Company',
            'phone' => '9876543210', 'email' => 'contact@example.test', 'source' => 'Referral', 'status' => 'New',
            'priority' => 'High', 'assigned_to' => $this->user->id, 'project_name' => 'New Factory',
            'requirement' => 'M25 concrete', 'estimated_quantity' => 200, 'expected_value' => 100000,
            'expected_purchase_date' => '2026-11-01', 'address' => 'Factory Road', 'delivery_location' => 'Site A',
        ], $overrides);
    }

    private function lead(array $data = []): CrmLead
    {
        return app(CrmLeadService::class)->create($this->plant->id, $this->data($data));
    }

    public function test_create_edit_duplicate_validation_and_soft_delete(): void
    {
        $response = $this->postJson(route('crm.leads.store'), $this->data(['phone' => '+91 98765 43210', 'email' => 'CONTACT@EXAMPLE.TEST']));
        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
        $lead = CrmLead::firstOrFail();
        $this->assertSame('9876543210', $lead->phone);
        $this->assertSame('contact@example.test', $lead->email);
        $this->assertStringStartsWith('LD/', $lead->lead_number);
        $this->assertSame($this->plant->id, $lead->plant_id);
        $this->assertSame(1, $lead->activities()->count());
        $this->postJson(route('crm.leads.store'), $this->data())->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson(route('crm.leads.store'), $this->data(['phone' => '9876500000']))->assertUnprocessable()->assertJsonValidationErrors('email');
        $this->postJson(route('crm.leads.store'), $this->data(['phone' => '', 'email' => '']))->assertUnprocessable()->assertJsonValidationErrors(['phone', 'email']);
        $this->putJson(route('crm.leads.update', $lead), $this->data(['status' => 'Contacted']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Contacted', $lead->fresh()->status);
        $this->assertSame(2, $lead->activities()->count());
        $this->putJson(route('crm.leads.update', $lead), $this->data(['status' => 'Converted']))->assertUnprocessable()->assertJsonValidationErrors('status');
        $this->deleteJson(route('crm.leads.destroy', $lead))->assertRedirect();
        $this->assertSoftDeleted('mm_crm_leads', ['id' => $lead->id]);
        $this->postJson(route('crm.leads.store'), $this->data())->assertRedirect()->assertSessionHasNoErrors();
    }

    public function test_plant_isolation_assignment_and_permissions(): void
    {
        $lead = $this->lead();
        $otherPlant = Plant::factory()->create();
        $foreignLead = CrmLead::withoutGlobalScopes()->create(array_merge($this->data(['phone' => '9000000000']), ['plant_id' => $otherPlant->id]));
        $this->getJson(route('crm.leads.show', $foreignLead))->assertNotFound();
        $this->putJson(route('crm.leads.update', $foreignLead), $this->data())->assertNotFound();
        $this->deleteJson(route('crm.leads.destroy', $foreignLead))->assertNotFound();
        $this->postJson(route('crm.leads.assign'), ['ids' => [$foreignLead->id], 'assigned_to' => $this->user->id])->assertUnprocessable()->assertJsonValidationErrors('ids.0');
        $outsider = User::factory()->create(['is_active' => true]);
        $this->postJson(route('crm.leads.assign'), ['ids' => [$lead->id], 'assigned_to' => $outsider->id])->assertUnprocessable()->assertJsonValidationErrors('assigned_to');
        $role = Role::firstOrCreate(['code' => 'CRM_TEST', 'guard_name' => 'web'], ['name' => 'CRM Test']);
        EntityUser::create(['user_id' => $outsider->id, 'entity_id' => $this->plant->entity_id, 'plant_id' => $this->plant->id, 'role_id' => $role->id]);
        $this->postJson(route('crm.leads.assign'), ['ids' => [$lead->id], 'assigned_to' => $outsider->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($outsider->id, $lead->fresh()->assigned_to);
        $this->user->revokePermissionTo('CRM_LEAD.ASSIGN');
        $this->postJson(route('crm.leads.assign'), ['ids' => [$lead->id], 'assigned_to' => $this->user->id])->assertForbidden();
        $this->putJson(route('crm.leads.update', $lead), $this->data())->assertForbidden();
        $this->user->revokePermissionTo('CRM_LEAD.VIEW');
        $this->getJson(route('crm.leads.show', $lead))->assertForbidden();
        $this->user->revokePermissionTo('CRM_LEAD.CREATE');
        $this->postJson(route('crm.leads.store'), $this->data())->assertForbidden();
        session()->forget('active_plant_id');
        $this->user->givePermissionTo('CRM_LEAD.CREATE');
        $this->postJson(route('crm.leads.store'), $this->data())->assertForbidden();
    }

    public function test_qualified_conversion_preserves_contact_and_creates_one_deal(): void
    {
        $lead = $this->lead();
        $this->postJson(route('crm.leads.convert', $lead), [])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $lead->update(['status' => 'Qualified']);
        $this->postJson(route('crm.leads.convert', $lead), [])->assertRedirect()->assertSessionHasNoErrors();
        $lead->refresh();
        $this->assertSame('Converted', $lead->status);
        $this->assertNotNull($lead->converted_at);
        $this->assertSame('CRM Company', $lead->customer->legal_name);
        $this->assertContains('Customer', $lead->customer->patron_type);
        $this->assertSame('9876543210', $lead->customer->contacts()->first()->mobile);
        $this->assertSame('Factory Road', $lead->customer->contacts()->first()->addresses()->first()->line_1);
        $this->assertSame('Requirement Received', $lead->deal->stage);
        $this->assertEquals(100000, $lead->deal->expected_value);
        $this->postJson(route('crm.leads.convert', $lead), [])->assertUnprocessable();
        $this->assertSame(1, CrmDeal::count());
        $this->assertSame(1, Patron::where('legal_name', 'CRM Company')->count());
        $this->putJson(route('crm.leads.update', $lead), $this->data(['status' => 'Qualified']))->assertUnprocessable();
        $this->deleteJson(route('crm.leads.destroy', $lead))->assertUnprocessable();
        $this->assertNotNull($lead->fresh());
    }

    public function test_conversion_duplicate_customer_and_same_plant_reuse(): void
    {
        $lead = $this->lead(['status' => 'Qualified']);
        $customer = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'CRM Company', 'patron_type' => ['Customer']]);
        $this->postJson(route('crm.leads.convert', $lead), [])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->assertNull($lead->fresh()->converted_at);
        $this->assertSame(0, CrmDeal::count());
        $foreign = Patron::factory()->create(['plant_id' => Plant::factory()->create()->id, 'patron_type' => ['Customer']]);
        $this->postJson(route('crm.leads.convert', $lead), ['customer_id' => $foreign->id])->assertUnprocessable()->assertJsonValidationErrors('customer_id');
        $this->postJson(route('crm.leads.convert', $lead), ['customer_id' => $customer->id])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($customer->id, $lead->fresh()->customer_id);
        $this->assertSame(0, $customer->contacts()->count());
    }

    public function test_followups_completion_note_timeline_and_dashboard(): void
    {
        $lead = $this->lead();
        $this->postJson(route('crm.leads.activity', $lead), ['type' => 'Call', 'subject' => 'Follow up'])->assertUnprocessable()->assertJsonValidationErrors('due_at');
        $this->postJson(route('crm.leads.activity', $lead), ['type' => 'Call', 'subject' => 'Follow up', 'due_at' => now()->subDay()->format('Y-m-d H:i:s')])->assertRedirect()->assertSessionHasNoErrors();
        $activity = $lead->activities()->where('type', 'Call')->firstOrFail();
        $this->assertNull($activity->completed_at);
        $response = $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertOk();
        $response->assertJsonPath('props.metrics.overdue', 1)->assertJsonPath('props.metrics.total', 1)->assertJsonPath('props.metrics.by_source.Referral', 1);
        $this->postJson(route('crm.leads.activity', $lead), ['type' => 'Note', 'subject' => 'Site requirement', 'description' => 'Customer requires M25'])->assertRedirect();
        $this->assertNotNull($lead->activities()->where('type', 'Note')->first()->completed_at);
        $other = $this->lead(['phone' => '9999999999', 'email' => 'second@example.test']);
        $this->patchJson(route('crm.leads.complete', ['lead' => $other, 'activity' => $activity]))->assertNotFound();
        $this->patchJson(route('crm.leads.complete', ['lead' => $lead, 'activity' => $activity]))->assertRedirect();
        $this->assertNotNull($activity->fresh()->completed_at);
        $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertJsonPath('props.metrics.overdue', 0);
        $this->getJson(route('crm.leads.show', $lead))->assertOk()->assertJsonPath('lead.contact_name', 'CRM Contact');
    }

    public function test_deal_stages_and_document_links_are_customer_scoped(): void
    {
        $lead = $this->lead(['status' => 'Qualified']);
        app(CrmLeadService::class)->convert($lead, null);
        $lead->refresh();
        $dealData = ['stage' => 'Negotiation', 'expected_value' => 150000, 'expected_close_date' => '2026-11-05'];
        $this->putJson(route('crm.leads.deal', $lead), $dealData)->assertRedirect();
        $this->assertSame('Negotiation', $lead->deal()->first()->stage);
        $this->putJson(route('crm.leads.deal', $lead), array_merge($dealData, ['stage' => 'Lost']))->assertUnprocessable()->assertJsonValidationErrors('lost_reason');
        $quote = \App\Models\Quotation::factory()->create(['plant_id' => $this->plant->id, 'patron_id' => $lead->customer_id]);
        $this->putJson(route('crm.leads.deal', $lead), array_merge($dealData, ['quotation_id' => $quote->id]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($quote->id, $lead->deal()->first()->quotation_id);
        $quote->update(['patron_id' => Patron::factory()->create(['plant_id' => $this->plant->id])->id]);
        $this->putJson(route('crm.leads.deal', $lead), array_merge($dealData, ['quotation_id' => $quote->id]))->assertUnprocessable()->assertJsonValidationErrors('quotation_id');
    }

    public function test_private_attachment_access_and_invalid_file(): void
    {
        Storage::fake('local');
        $lead = $this->lead();
        $this->post(route('crm.leads.attach', $lead), ['file' => UploadedFile::fake()->create('proposal.pdf', 12, 'application/pdf')])->assertRedirect()->assertSessionHasNoErrors();
        $attachment = $lead->attachments()->firstOrFail();
        Storage::disk('local')->assertExists($attachment->path);
        $this->get(route('crm.leads.download', ['lead' => $lead, 'attachment' => $attachment]))->assertOk()->assertDownload('proposal.pdf');
        $other = $this->lead(['phone' => '9999999999', 'email' => 'other@example.test']);
        $this->getJson(route('crm.leads.download', ['lead' => $other, 'attachment' => $attachment]))->assertNotFound();
        $this->postJson(route('crm.leads.attach', $lead), ['file' => UploadedFile::fake()->create('script.php', 1, 'application/x-httpd-php')])->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->deleteJson(route('crm.leads.destroy', $lead))->assertRedirect();
        $this->getJson(route('crm.leads.download', ['lead' => $lead, 'attachment' => $attachment]))->assertNotFound();
    }

    public function test_menu_permission_installer_is_idempotent(): void
    {
        InstallCrmModule::run();
        InstallCrmModule::run();
        $this->assertSame(6, Permission::where('module', 'CRM_LEAD')->count());
        $this->assertSame(2, DB::table('mm_menus')->whereIn('alias', ['crm', 'crm-leads'])->count());
        $this->assertSame('CRM_LEAD.VIEW', DB::table('mm_menus')->where('alias', 'crm-leads')->value('permission_name'));
    }

    public function test_each_mutating_action_requires_its_permission(): void
    {
        $lead = $this->lead(['status' => 'Qualified']);
        foreach ([['CRM_LEAD.UPDATE', 'put', 'update', $this->data()], ['CRM_LEAD.DELETE', 'delete', 'destroy', []], ['CRM_LEAD.CONVERT', 'post', 'convert', []]] as [$permission, $method, $action, $data]) {
            $this->user->revokePermissionTo($permission);
            $this->json(strtoupper($method), route('crm.leads.' . $action, $lead), $data)->assertForbidden();
            $this->user->givePermissionTo($permission);
        }
        $this->assertNull($lead->fresh()->converted_at);
        $this->assertNotNull($lead->fresh());
    }

    public function test_walk_in_source_long_address_and_sales_manager_menu_access(): void
    {
        $address = str_repeat('A', 200) . str_repeat('B', 180);
        $this->postJson(route('crm.leads.store'), $this->data(['source' => 'Walk-in', 'address' => $address, 'status' => 'Qualified']))->assertRedirect()->assertSessionHasNoErrors();
        $lead = CrmLead::firstOrFail();
        $this->assertSame('Walk-in', $lead->source);
        $this->postJson(route('crm.leads.convert', $lead), [])->assertRedirect()->assertSessionHasNoErrors();
        $savedAddress = $lead->fresh()->customer->contacts()->first()->addresses()->first();
        $this->assertSame($lead->address, $savedAddress->line_1 . $savedAddress->line_2);
        $role = Role::firstOrCreate(['code' => 'SALES_MANAGER', 'guard_name' => 'web'], ['name' => 'Sales Manager']);
        InstallCrmModule::run();
        $this->assertSame(6, $role->permissions()->where('module', 'CRM_LEAD')->count());
        $roleUser = User::factory()->create(['is_active' => true]);
        EntityUser::create(['user_id' => $roleUser->id, 'entity_id' => $this->plant->entity_id, 'plant_id' => $this->plant->id, 'role_id' => $role->id]);
        $this->actingAs($roleUser)->getJson(route('crm.leads.show', $lead))->assertOk();
        $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.metrics.converted', 1)->assertJsonPath('props.metrics.conversion_rate', 100);
    }

    public function test_followups_continue_after_conversion_until_deal_is_closed(): void
    {
        $lead = $this->lead(['status' => 'Qualified']);
        $lead->activities()->create(['plant_id' => $this->plant->id, 'type' => 'Call', 'subject' => 'Discuss quotation', 'due_at' => now()->subDay()]);
        app(CrmLeadService::class)->convert($lead, null);
        $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.metrics.overdue', 1);
        $lead->deal()->first()->update(['stage' => 'Won']);
        $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertOk()->assertJsonPath('props.metrics.overdue', 0);
    }

    public function test_requirement_dropdown_contains_only_active_plant_mix_designs_and_preserves_names(): void
    {
        $plantId = $this->plant->id;
        \App\Models\Product::factory()->create(['plant_id' => $plantId, 'title' => 'Cement', 'status' => true]);
        \App\Models\MixDesign::factory()->create(['plant_id' => $plantId, 'design_name' => 'M25 Design', 'is_active' => true]);
        \App\Models\MixDesign::factory()->create(['plant_id' => $plantId, 'design_name' => 'M25 Design', 'is_active' => true]);
        \App\Models\Product::factory()->create(['plant_id' => $plantId, 'title' => 'M25 Design', 'status' => true]);
        \App\Models\Product::factory()->create(['plant_id' => $plantId, 'title' => 'Inactive Product', 'status' => false]);
        \App\Models\MixDesign::factory()->create(['plant_id' => $plantId, 'design_name' => 'Inactive Mix', 'is_active' => false]);
        \App\Models\Product::factory()->create(['plant_id' => $plantId, 'title' => 'Deleted Product', 'deleted_at' => now()]);
        \App\Models\MixDesign::factory()->create(['plant_id' => $plantId, 'design_name' => 'Deleted Mix', 'is_active' => true, 'deleted_at' => now()]);
        $foreignPlant = Plant::factory()->create();
        \App\Models\Product::factory()->create(['plant_id' => $foreignPlant->id, 'title' => 'Foreign Product']);
        \App\Models\MixDesign::factory()->create(['plant_id' => $foreignPlant->id, 'design_name' => 'Foreign Mix', 'is_active' => true]);
        $response = $this->get(route('crm.leads.index'), ['X-Inertia' => 'true'])->assertOk();
        $choices = $response->json('props.options.requirements');
        $this->assertCount(1, $choices);
        $this->assertSame([['label' => 'M25 Design', 'value' => 'M25 Design']], $choices);
        $lead = $this->lead(['requirement' => 'Legacy project requirement']);
        $this->putJson(route('crm.leads.update', $lead), $this->data(['requirement' => $lead->requirement]))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('Legacy project requirement', $lead->fresh()->requirement);
        $this->putJson(route('crm.leads.update', $lead), $this->data(['requirement' => 'M25 Design']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('M25 Design', $lead->fresh()->requirement);
    }
}
