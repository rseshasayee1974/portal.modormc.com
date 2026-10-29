<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:02:11 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\{Patron, Plant, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Role;

class PatronQuickCreateTest extends TestCase
{
    use RefreshDatabase;

    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->plant = Plant::factory()->create();
        $user = User::factory()->create();
        $user->assignRole(Role::firstOrCreate(
            ['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']
        ));
        $this->actingAs($user)->withSession([
            'active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id,
        ]);
    }

    private function payload(array $extra = []): array
    {
        return array_merge([
            'legal_name' => 'Popup Test Customer', 'patron_type' => ['Customer'],
            'operational_status' => 'active', 'status' => true, 'displayed' => true,
            'gstin' => '33ABCDE1234F1Z5',
            'aadhar_number' => '', 'contact_mobile' => '',
        ], $extra);
    }

    public function test_returns_created_patron_for_immediate_selection(): void
    {
        $response = $this->postJson(route('patrons.store'), $this->payload())
            ->assertCreated()->assertJsonPath('patron.legal_name', 'Popup Test Customer')
            ->assertJsonPath('patron.patron_type', ['Customer'])
            ->assertJsonPath('patron.plant_id', $this->plant->id);
        $this->assertDatabaseHas('mm_patrons', [
            'id' => $response->json('patron.id'), 'plant_id' => $this->plant->id,
        ]);
    }

    public function test_vendor_supports_aadhaar_instead_of_gstin(): void
    {
        $this->withoutExceptionHandling();
        $this->postJson(route('patrons.store'), $this->payload([
            'patron_type' => ['Vendor', 'Supplier'], 'gstin' => '', 'aadhar_number' => '123456789012',
        ]))->assertCreated()->assertJsonPath('patron.patron_type', ['Vendor', 'Supplier']);
    }

    public function test_duplicate_name_returns_field_error_and_does_not_create_duplicate(): void
    {
        $this->postJson(route('patrons.store'), $this->payload())->assertCreated();
        $this->postJson(route('patrons.store'), $this->payload())
            ->assertUnprocessable()->assertJsonValidationErrors('legal_name');
        $this->assertSame(1, Patron::where('legal_name', 'Popup Test Customer')->count());
    }

    public function test_popup_must_supply_one_existing_required_identifier(): void
    {
        $this->postJson(route('patrons.store'), $this->payload(['gstin' => '']))
            ->assertUnprocessable()->assertJsonValidationErrors(['gstin', 'aadhar_number']);
        $this->assertSame(0, Patron::where('legal_name', 'Popup Test Customer')->count());
    }

    public function test_creation_permission_is_required(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson(route('patrons.store'), $this->payload())->assertForbidden();
    }
}
