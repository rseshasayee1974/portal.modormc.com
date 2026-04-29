<?php

namespace Tests\Feature;

use App\Models\AddressType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AddressTypeTest extends TestCase
{
    use RefreshDatabase;

    public function test_address_types_page_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/address-types');

        $response->assertStatus(200);
    }

    public function test_address_type_can_be_created(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/address-types', [
            'type' => 'Billing Address',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/address-types');
        
        $this->assertDatabaseHas('mm_address_types', [
            'type' => 'Billing Address',
        ]);
    }

    public function test_address_type_validation_fails_when_type_is_empty(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/address-types', [
            'type' => '',
        ]);

        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseEmpty('mm_address_types');
    }

    public function test_address_type_validation_fails_when_type_is_not_unique(): void
    {
        $user = User::factory()->create();
        AddressType::create(['type' => 'Shipping Address']);

        $response = $this->actingAs($user)->post('/address-types', [
            'type' => 'Shipping Address',
        ]);

        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseCount('mm_address_types', 1);
    }

    public function test_address_type_can_be_updated(): void
    {
        $user = User::factory()->create();
        $addressType = AddressType::create(['type' => 'Billing Address']);

        $response = $this->actingAs($user)->put("/address-types/{$addressType->id}", [
            'type' => 'Updated Address',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/address-types');

        $this->assertDatabaseHas('mm_address_types', [
            'id' => $addressType->id,
            'type' => 'Updated Address',
        ]);
    }

    public function test_address_type_validation_fails_on_update_when_type_is_not_unique(): void
    {
        $user = User::factory()->create();
        AddressType::create(['type' => 'Billing Address']);
        $addressType2 = AddressType::create(['type' => 'Shipping Address']);

        $response = $this->actingAs($user)->put("/address-types/{$addressType2->id}", [
            'type' => 'Billing Address',
        ]);

        $response->assertSessionHasErrors(['type']);
        $this->assertDatabaseHas('mm_address_types', [
            'id' => $addressType2->id,
            'type' => 'Shipping Address',
        ]);
    }

    public function test_address_type_can_be_deleted(): void
    {
        $user = User::factory()->create();
        $addressType = AddressType::create(['type' => 'Billing Address']);

        $response = $this->actingAs($user)->delete("/address-types/{$addressType->id}");

        $response->assertSessionHasNoErrors();
        $response->assertRedirect('/address-types');

        $this->assertDatabaseMissing('mm_address_types', [
            'id' => $addressType->id,
        ]);
    }
}
