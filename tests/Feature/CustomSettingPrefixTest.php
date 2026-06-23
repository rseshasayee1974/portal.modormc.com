<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use App\Models\CustomSetting;
use App\Models\Quotation;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomSettingPrefixTest extends TestCase
{
    use RefreshDatabase;

    protected $plant;
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:o/uhgklRUIi8R9GE5ftPdxE+yRmWNQOie8gIb4XV14g=']);

        $this->user = User::factory()->create();
        $this->actingAs($this->user);

        $this->plant = Plant::factory()->create(['name' => 'Prefix Test Plant']);
        session(['active_plant_id' => $this->plant->id]);
    }

    public function test_can_save_prefix_settings()
    {
        $data = [
            'module' => 'batching',
            'settings' => [
                'po_prefix' => 'XYZ-PO',
                'so_prefix' => 'ABC-WO',
                'quote_prefix' => 'MNO-QT',
            ]
        ];

        $response = $this->post(route('settings.customsetting.update'), $data);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('mm_custom_settings', [
            'plant_id' => $this->plant->id,
            'module_name' => 'batching'
        ]);

        $customSetting = CustomSetting::where('plant_id', $this->plant->id)
            ->where('module_name', 'batching')
            ->first();

        $this->assertEquals('XYZ-PO', $customSetting->settings['po_prefix']);
        $this->assertEquals('ABC-WO', $customSetting->settings['so_prefix']);
        $this->assertEquals('MNO-QT', $customSetting->settings['quote_prefix']);
    }

    public function test_quotation_reference_uses_custom_prefix()
    {
        CustomSetting::create([
            'plant_id' => $this->plant->id,
            'module_name' => 'batching',
            'settings' => [
                'quote_prefix' => 'MNO-QT',
            ],
            'module_id' => 0
        ]);

        $ref = Quotation::generateReference($this->plant->id);
        
        $this->assertStringStartsWith('MNO-QT-', $ref);
    }

    public function test_purchase_order_ref_id_uses_custom_prefix()
    {
        CustomSetting::create([
            'plant_id' => $this->plant->id,
            'module_name' => 'batching',
            'settings' => [
                'po_prefix' => 'XYZ-PO',
            ],
            'module_id' => 0
        ]);

        $refData = PurchaseOrder::generateNextRefId($this->plant->id);
        
        $this->assertStringStartsWith('XYZ-PO-', $refData['ref_no']);
    }

    public function test_work_order_order_no_uses_custom_prefix()
    {
        CustomSetting::create([
            'plant_id' => $this->plant->id,
            'module_name' => 'batching',
            'settings' => [
                'so_prefix' => 'ABC-WO',
            ],
            'module_id' => 0
        ]);

        $details = SalesOrder::generateOrderNo($this->plant->id, 'WO');
        
        $this->assertStringStartsWith('ABC-WO/', $details['full_number']);
    }

    public function test_can_store_new_module_settings()
    {
        $response = $this->post(route('settings.customsetting.store'), [
            'module' => 'fleet'
        ]);

        $response->assertRedirect();
        
        $this->assertDatabaseHas('mm_custom_settings', [
            'plant_id' => $this->plant->id,
            'module_name' => 'fleet',
            'settings' => '[]'
        ]);
    }

    public function test_can_delete_module_settings()
    {
        $setting = CustomSetting::create([
            'plant_id' => $this->plant->id,
            'module_name' => 'finance',
            'settings' => [],
            'module_id' => 0
        ]);

        $response = $this->delete(route('settings.customsetting.destroy', $setting->id));

        $response->assertRedirect();
        
        $this->assertDatabaseMissing('mm_custom_settings', [
            'id' => $setting->id
        ]);
    }
}
