<?php

namespace Tests\Feature;

use App\Models\{Machine, Patron, Plant, Product, ProductUnit, PurchaseOrder, PurchaseOrderHistory, PurchaseOrderItem, Quantity, User};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase;
use Spatie\Permission\Models\Role;

class InwardDetailsTest extends TestCase
{
    use RefreshDatabase;

    private PurchaseOrderHistory $inward;
    private PurchaseOrderItem $item;
    private Quantity $stock;
    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();
        $user = User::factory()->create();
        $this->plant = Plant::factory()->create();
        $unit = ProductUnit::factory()->create();
        $product = Product::factory()->create(['plant_id' => $this->plant->id, 'unit_id' => $unit->id]);
        $order = PurchaseOrder::factory()->create([
            'plant_id' => $this->plant->id, 'vendor_id' => Patron::factory()->create()->id,
            'state' => 'approved', 'receipt_status' => 1,
        ]);
        $this->item = PurchaseOrderItem::factory()->create([
            'order_id' => $order->id, 'plant_id' => $this->plant->id, 'product_id' => $product->id,
            'product_uom' => $unit->id, 'product_quantity' => 100, 'received_quantity' => 10,
            'invoiced_quantity' => 0, 'discount_type' => '%', 'discount_amount' => 0,
        ]);
        $this->inward = PurchaseOrderHistory::create([
            'plant_id' => $this->plant->id, 'order_id' => $order->id, 'order_item_id' => $this->item->id,
            'product_id' => $product->id, 'uom_id' => $unit->id, 'received_date' => '2026-10-03',
            'inward_no' => 'INW-EDIT', 'received_qty' => 10, 'truck_loaded' => 100, 'truck_empty' => 20,
            'truck_id' => Machine::factory()->create(['plant_id' => $this->plant->id])->id, 'status' => 1,
        ]);
        $this->stock = Quantity::create([
            'plant_id' => $this->plant->id, 'product_id' => $product->id, 'uom_id' => $unit->id,
            'quantity' => 10, 'opening_quantity' => 0, 'status' => 1,
        ]);
        $user->assignRole(Role::firstOrCreate(['name' => 'Platform Admin', 'guard_name' => 'web'], ['code' => 'PLATFORM_ADMIN']));
        $this->actingAs($user)->withSession(['active_plant_id' => $this->plant->id, 'active_entity_id' => $this->plant->entity_id]);
    }

    public function test_inwards_require_purchase_orders_and_reject_direct_stock_in(): void
    {
        $count = PurchaseOrderHistory::count();
        foreach ([['entry_mode' => 'direct'], []] as $mode) {
            $this->post(route('inwards.store'), $mode + [
                'received_date' => '2026-11-06',
                'items' => [['product_id' => $this->inward->product_id, 'received_qty' => 5]],
            ])->assertSessionHasErrors('order_id');
        }
        $this->assertSame($count, PurchaseOrderHistory::count());
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->post(route('inwards.store'), [
            'order_id' => $this->item->order_id, 'received_date' => '2026-11-06', 'inward_no' => '',
            'items' => [['order_item_id' => $this->item->id, 'received_qty' => 5]],
        ])->assertRedirect(route('inwards.index'))->assertSessionHasNoErrors();
        $this->assertSame($count + 1, PurchaseOrderHistory::count());
        $this->assertEquals(15, $this->stock->fresh()->quantity);
    }

    public function test_inward_number_formats_and_padding_preserve_sequence_and_settings(): void
    {
        $controller = \Mockery::mock(\App\Http\Controllers\CustomSettingController::class)->makePartial()->shouldAllowMockingProtectedMethods();
        $controller->shouldReceive('authorizeModule')->times(8)->with('edit');
        $plantId = $this->plant->id;
        $this->assertSame('INW/26-27/1', PurchaseOrderHistory::generateNextInwardNo($plantId, '2026-11-05'));
        foreach ([
            ['prefix_number_fy', 0, 'INW/1/26-27'],
            ['prefix_fy_number', 0, 'INW/26-27/2'],
            ['prefix_compact_fy_number', 0, 'INW/2627/3'],
            ['prefix_compact_fy_number', 4, 'INW/2627/0004'],
        ] as [$format, $padding, $expected]) {
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => [
                'inward_number_format' => $format, 'inward_number_padding' => (string) $padding,
            ]]));
            $controller->update(\Illuminate\Http\Request::create('/', 'POST', ['settings' => ['camera' => 1]]));
            $this->assertSame($expected, PurchaseOrderHistory::generateNextInwardNo($plantId, '2026-11-05'));
            $record = $this->inward->replicate();
            $record->inward_no = $expected;
            $record->save();
        }
        $record->delete();
        $this->assertSame('INW/2627/0005', PurchaseOrderHistory::generateNextInwardNo($plantId, '2027-03-31'));
        $this->assertSame('INW/2728/0001', PurchaseOrderHistory::generateNextInwardNo($plantId, '2027-04-01'));
        $this->assertSame('INW/26-27/1', PurchaseOrderHistory::generateNextInwardNo(Plant::factory()->create()->id, '2026-11-05'));
        $this->assertSame('INW-EDIT', $this->inward->fresh()->inward_no);
    }

    public function test_registry_paginates_searches_filters_and_exports_all_matching_rows(): void
    {
        for ($i = 1; $i <= 35; $i++) {
            $record = $this->inward->replicate();
            $record->inward_no = 'BULK-' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $record->received_date = '2026-10-04 23:59:59';
            $record->save();
        }
        $outside = $this->inward->replicate();
        $outside->plant_id = Plant::factory()->create()->id;
        $outside->inward_no = 'OTHER-PLANT';
        $outside->save();
        $this->get(route('inwards.index'))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('inwards', 30)->where('pagination.total', 36)->where('pagination.page', 1));
        $this->get(route('inwards.index', ['page' => 2]))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('inwards', 6)->where('pagination.total', 36)->where('pagination.page', 2));
        $this->get(route('inwards.index', ['search' => 'INW-EDIT']))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('inwards', 1)->where('inwards.0.inward_no', 'INW-EDIT'));
        $this->get(route('inwards.index', ['start_date' => '2026-10-04', 'end_date' => '2026-10-04', 'sort' => 'inward_no', 'direction' => 'asc']))
            ->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
                ->has('inwards', 30)->where('pagination.total', 35)->where('inwards.0.inward_no', 'BULK-001'));
        $this->get(route('inwards.index', ['end_date' => '2026-10-03']))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->has('inwards', 1)->where('pagination.total', 1));
        $export = $this->get(route('inwards.export', ['search' => 'BULK', 'start_date' => '2026-10-04', 'end_date' => '2026-10-04', 'page' => 2]));
        $export->assertOk()->assertDownload('stock-inward-report.csv');
        $csv = $export->streamedContent();
        $this->assertSame(36, substr_count($csv, "\n"), 'Header plus all 35 matching rows, regardless of page');
        $this->assertStringContainsString('BULK-001', $csv);
        $this->assertStringContainsString('BULK-035', $csv);
        $this->assertStringNotContainsString('INW-EDIT', $csv);
        $this->assertStringNotContainsString('OTHER-PLANT', $csv);
    }

    public function test_registry_sends_shared_purchase_order_once_without_receipt_histories(): void
    {
        $second = $this->inward->replicate();
        $second->inward_no = 'INW-SECOND';
        $second->save();

        $this->get(route('inwards.index'))->assertOk()->assertInertia(fn (\Inertia\Testing\AssertableInertia $page) => $page
            ->component('PurchaseOrders/Inwards/Index')
            ->has('inwards', 2)
            ->has('inwardOrders', 1)
            ->where('inwardOrders.0.id', $this->inward->order_id)
            ->has('inwardOrders.0.items', 1)
            ->missing('inwardOrders.0.billing_history')
            ->missing('inwardOrders.0.items.0.history')
            ->missing('inwards.0.order')
            ->where('inwards.0.order_id', $this->inward->order_id)
            ->has('inwards.0.is_billed')
            ->has('inwards.0.item')
            ->has('purchaseOrders')
        );
    }

    public function test_inward_report_filters_by_receipt_timestamp_instead_of_entry_date(): void
    {
        $this->inward->forceFill([
            'received_date' => '2026-10-03 00:00:00',
            'created_at' => '2026-10-03 10:15:00',
        ])->save();

        $late = $this->inward->replicate();
        $late->forceFill([
            'inward_no' => 'INW-LATE',
            'received_date' => '2026-10-03 12:00:00',
            'created_at' => '2026-10-03 10:30:00',
        ])->save();

        $backdated = $this->inward->replicate();
        $backdated->forceFill([
            'inward_no' => 'INW-BACKDATED',
            'received_date' => '2026-10-02 00:00:00',
            'created_at' => '2026-10-03 10:30:00',
        ])->save();

        $report = app(\App\Services\Reports\InventoryInwardReportService::class)->generate([
            'start' => '2026-10-03 10:15:00',
            'end' => '2026-10-03 11:00:00',
        ]);
        $this->assertSame(['INW-EDIT'], $report['transactions']->pluck('inward_no')->all());
        $this->assertSame('2026-10-03 10:15:00', $report['transactions']->first()['date']);
        $this->assertSame(10.0, $report['total_quantity']);
    }

    public function test_inward_report_date_only_and_exact_second_boundaries_are_consistent(): void
    {
        $this->inward->forceFill(['received_date' => '2026-10-03 23:59:59', 'created_at' => '2026-10-04 09:00:00'])->save();
        $service = app(\App\Services\Reports\InventoryInwardReportService::class);
        foreach ([
            ['2026-10-03', '2026-10-03'],
            ['2026-10-03 23:59:59', '2026-10-03 23:59:59'],
            ['2026-10-03T18:29:59Z', '2026-10-03T18:29:59Z'],
        ] as [$start, $end]) {
            $result = $service->generate(['start' => $start, 'end' => $end]);
            $this->assertSame(['INW-EDIT'], $result['transactions']->pluck('inward_no')->all());
            $this->assertSame('2026-10-03 23:59:59', $result['transactions']->first()['date']);
        }
        $this->assertCount(0, $service->generate(['start' => '2026-10-03 00:00:00', 'end' => '2026-10-03 23:59:58'])['transactions']);
    }

    public function test_inward_creation_preserves_received_date_hours_minutes_and_seconds(): void
    {
        $this->post(route('inwards.store'), [
            'order_id' => $this->item->order_id, 'received_date' => '2026-10-07 14:35:27',
            'inward_no' => 'INW-TIMED', 'items' => [[
                'order_item_id' => $this->item->id, 'received_qty' => 5, 'truck_loaded' => 5,
            ]],
        ])->assertRedirect(route('inwards.index'))->assertSessionHasNoErrors();
        $entry = PurchaseOrderHistory::where('inward_no', 'INW-TIMED')->firstOrFail();
        $this->assertSame('2026-10-07 14:35:27', $entry->getRawOriginal('received_date'));
        $report = app(\App\Services\Reports\InventoryInwardReportService::class)->generate([
            'start' => '2026-10-07 14:35:27', 'end' => '2026-10-07 14:35:27',
        ]);
        $this->assertSame(['INW-TIMED'], $report['transactions']->pluck('inward_no')->all());
    }

    public function test_inward_report_vendor_filter_works_without_direct_inward_vendor_column(): void
    {
        \Illuminate\Support\Facades\Schema::table('mm_purchase_order_history', function ($table) {
            $table->dropIndex('mm_purchase_order_history_vendor_id_index');
            $table->dropColumn('vendor_id');
        });
        $vendor = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'PO Vendor']);
        $this->inward->order->update(['vendor_id' => $vendor->id]);
        $this->inward->forceFill(['received_date' => '2026-10-03 10:15:00', 'created_at' => '2026-10-03 10:15:00'])->save();
        $service = app(\App\Services\Reports\InventoryInwardReportService::class);
        $filters = ['start' => '2026-10-03', 'end' => '2026-10-03', 'patron_id' => $vendor->id];
        $report = $service->generate($filters);
        $this->assertSame(['INW-EDIT'], $report['transactions']->pluck('inward_no')->all());
        $this->assertSame('PO Vendor', $report['transactions']->first()['vendor_name']);
        $filters['patron_id'] = $vendor->id + 1000;
        $this->assertCount(0, $service->generate($filters)['transactions']);
    }

    public function test_inward_report_party_filter_is_shared_by_view_excel_and_pdf(): void
    {
        $conversionUnit = ProductUnit::factory()->create(['unit_code' => 'CFT']);
        $this->inward->update(['conversion_quantity' => 3.1256, 'conversion_uom_id' => $conversionUnit->id]);
        $vendor = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Selected Vendor']);
        $this->inward->order->update(['vendor_id' => $vendor->id]);
        $otherVendor = Patron::factory()->create(['plant_id' => $this->plant->id, 'legal_name' => 'Excluded Vendor']);
        $otherOrder = PurchaseOrder::factory()->create(['plant_id' => $this->plant->id, 'vendor_id' => $otherVendor->id]);
        $otherInward = $this->inward->replicate();
        $otherInward->fill(['order_id' => $otherOrder->id, 'inward_no' => 'INW-EXCLUDED', 'received_qty' => 25]);
        $otherInward->save();
        $otherProduct = Product::factory()->create(['plant_id' => $this->plant->id, 'unit_id' => $this->inward->uom_id]);
        $otherProductInward = $this->inward->replicate();
        $otherProductInward->fill(['product_id' => $otherProduct->id, 'inward_no' => 'INW-PRODUCT-EXCLUDED', 'received_qty' => 40]);
        $otherProductInward->save();
        $filters = ['plant_id' => $this->plant->id, 'patron_id' => $vendor->id,
            'product_id' => $this->inward->product_id,
            'start' => '2026-10-01 00:00:00', 'end' => '2026-10-31 23:59:59'];
        $service = app(\App\Services\Reports\InventoryInwardReportService::class);
        $report = $service->generate($filters);
        $this->assertSame(3.1256, $report['transactions']->first()['conversion_quantity']);
        $this->assertSame('CFT', $report['transactions']->first()['conversion_uom']);
        $this->assertSame('3.1256 CFT', $report['transactions']->first()['conversion_quantity_display']);
        $this->assertSame(['INW-EDIT'], $report['transactions']->pluck('inward_no')->all());
        $this->assertSame(10.0, $report['total_quantity']);
        $all = $service->generate(array_replace($filters, ['patron_id' => null, 'product_id' => null]));
        $this->assertCount(3, $all['transactions']);
        $this->assertSame(75.0, $all['total_quantity']);
        $productOnly = $service->generate(array_replace($filters, ['patron_id' => null]));
        $this->assertCount(2, $productOnly['transactions']);
        $this->assertSame(35.0, $productOnly['total_quantity']);
        $this->assertCount(0, $service->generate(array_replace($filters, ['product_id' => 999999]))['transactions']);
        $empty = $service->generate(array_replace($filters, ['patron_id' => 999999]));
        $this->assertCount(0, $empty['transactions']);
        $this->assertSame(0.0, $empty['total_quantity']);

        // Check the actual queued Excel path, then inspect the generated workbook.
        $excelKey = 'inward-filter-excel-test';
        (new \App\Jobs\QueueReportExportJob('inventory_inward', $filters, $excelKey, 'excel'))
            ->handle(app(\App\Repositories\ReportRepository::class));
        $export = \Illuminate\Support\Facades\Cache::get($excelKey);
        $this->assertSame('completed', $export['status']);
        $path = storage_path('app/private/reports/'.$export['filename']);
        try {
            $workbook = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
            $cells = json_encode($workbook->getActiveSheet()->toArray());
            $this->assertStringContainsString('INW-EDIT', $cells);
            $this->assertStringContainsString('Conversion Quantity', $cells);
            $this->assertStringContainsString('3.1256 CFT', $cells);
            $this->assertStringNotContainsString('INW-EXCLUDED', $cells);
            $this->assertStringNotContainsString('INW-PRODUCT-EXCLUDED', $cells);
            $this->assertStringNotContainsString('Excluded Vendor', $cells);
            $workbook->disconnectWorksheets();
        } finally {
            \Illuminate\Support\Facades\File::delete($path);
        }

        // Verify the queued PDF renderer receives the same filtered rows and total.
        $pdf = \Mockery::mock(\Barryvdh\DomPDF\PDF::class);
        $pdf->shouldReceive('setPaper')->once()->andReturnSelf();
        $pdf->shouldReceive('setOption')->once()->andReturnSelf();
        $pdf->shouldReceive('save')->once()->andReturnSelf();
        \Barryvdh\DomPDF\Facade\Pdf::shouldReceive('loadView')->once()
            ->withArgs(function ($view, $data) {
                $this->assertSame(['INW-EDIT'], collect($data['transactions'])->pluck('inward_no')->all());
                $this->assertSame(10.0, $data['total_quantity']);
                $this->assertSame(10.0, $data['totals']['quantity']);
                $this->assertContains('Conversion Quantity', $data['headers']);
                $this->assertContains('conversion_quantity_display', $data['fields']);
                $this->assertSame('3.1256 CFT', collect($data['transactions'])->first()['conversion_quantity_display']);
                return true;
            })->andReturn($pdf);
        (new \App\Jobs\QueueReportExportJob('inventory_inward', $filters, 'inward-filter-pdf-test', 'pdf'))
            ->handle(app(\App\Repositories\ReportRepository::class));
        $this->assertSame('completed', \Illuminate\Support\Facades\Cache::get('inward-filter-pdf-test')['status']);
    }

    private function updateDetails(array $payload)
    {
        return $this->post(route('inwards.update-weight', $this->inward), $payload);
    }

    public function test_loaded_truck_creation_can_exceed_order_then_net_quantity_is_validated(): void
    {
        $this->post(route('inwards.store'), [
            'order_id' => $this->item->order_id,
            'received_date' => '2026-10-07',
            'inward_no' => 'INW-LOADED',
            'items' => [[
                'order_item_id' => $this->item->id,
                'received_qty' => 150,
                'truck_loaded' => 150,
            ]],
        ])->assertRedirect(route('inwards.index'))->assertSessionHasNoErrors();

        $loaded = PurchaseOrderHistory::where('inward_no', 'INW-LOADED')->firstOrFail();
        $this->assertEquals(150, $loaded->received_qty);
        $this->assertEquals(160, $this->item->fresh()->received_quantity);
        $this->assertEquals(160, $this->stock->fresh()->quantity);

        // Net 100 plus the earlier receipt of 10 exceeds the order of 100.
        $this->post(route('inwards.update-weight', $loaded), ['truck_empty' => 50])
            ->assertSessionHasErrors('received_qty');
        $this->assertEquals(150, $loaded->fresh()->received_qty);
        $this->assertNull($loaded->fresh()->truck_empty);
        $this->assertEquals(160, $this->stock->fresh()->quantity);

        // Net 90 plus the earlier receipt of 10 exactly fulfils the order.
        $this->post(route('inwards.update-weight', $loaded), ['truck_empty' => 60])
            ->assertSessionHasNoErrors();
        $this->assertEquals(90, $loaded->fresh()->received_qty);
        $this->assertEquals(100, $this->item->fresh()->received_quantity);
        $this->assertEquals(100, $this->stock->fresh()->quantity);
    }

    public function test_tare_weight_equal_or_greater_than_loaded_weight_is_rejected(): void
    {
        // $this->inward has truck_loaded = 100, truck_empty = 20
        $this->updateDetails(['truck_empty' => 100])->assertSessionHasErrors('truck_empty');
        $this->updateDetails(['truck_empty' => 120])->assertSessionHasErrors('truck_empty');
        $this->assertEquals(20, $this->inward->fresh()->truck_empty);
    }

    public function test_received_quantity_above_order_is_rejected_after_loaded_entry(): void
    {
        $this->updateDetails(['received_qty' => 101])->assertSessionHasErrors('received_qty');
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->item->fresh()->received_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
    }

    public function test_truck_only_edit_preserves_accepted_quantity_and_stock_even_after_billing(): void
    {
        $this->item->update(['invoiced_quantity' => 10]);
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $this->updateDetails(['truck_id' => $truck->id, 'uom_id' => $this->inward->uom_id])->assertSessionHasNoErrors();
        $this->assertEquals($truck->id, $this->inward->fresh()->truck_id);
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->item->fresh()->received_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['truck_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->inward->fresh()->truck_id);
    }

    public function test_received_unit_change_moves_stock_to_the_selected_unit(): void
    {
        $unit = ProductUnit::factory()->create();
        $destination = Quantity::create([
            'plant_id' => $this->plant->id, 'product_id' => $this->inward->product_id,
            'uom_id' => $unit->id, 'quantity' => 5, 'opening_quantity' => 0, 'status' => 1,
        ]);
        $this->updateDetails(['uom_id' => $unit->id])->assertSessionHasNoErrors();
        $this->updateDetails(['uom_id' => null])->assertSessionHasErrors('uom_id');
        $this->assertEquals($unit->id, $this->inward->fresh()->uom_id);
        $this->assertEquals(0, $this->stock->fresh()->quantity);
        $this->assertEquals(15, $destination->fresh()->quantity);
        $this->assertEquals(10, $this->item->fresh()->received_quantity);
    }

    public function test_insufficient_stock_rejects_the_entire_unit_update(): void
    {
        $this->stock->update(['quantity' => 3]);
        $unit = ProductUnit::factory()->create();
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $originalTruck = $this->inward->truck_id;
        $this->updateDetails(['uom_id' => $unit->id, 'truck_id' => $truck->id])->assertSessionHasErrors('uom_id');
        $this->assertEquals($originalTruck, $this->inward->fresh()->truck_id);
        $this->assertEquals($this->stock->uom_id, $this->inward->fresh()->uom_id);
        $this->assertEquals(3, $this->stock->fresh()->quantity);
        $this->assertDatabaseMissing('mm_quantity', ['product_id' => $this->inward->product_id, 'uom_id' => $unit->id]);
    }

    public function test_unit_and_quantity_change_updates_stock_and_receipt_together(): void
    {
        $unit = ProductUnit::factory()->create();
        $truck = Machine::factory()->create(['plant_id' => $this->plant->id]);
        $this->updateDetails(['uom_id' => $unit->id, 'received_qty' => 15, 'truck_id' => $truck->id,
            'conversion_uom_id' => $unit->id, 'conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals($unit->id, $this->inward->fresh()->uom_id);
        $this->assertEquals($truck->id, $this->inward->fresh()->truck_id);
        $this->assertEquals(3, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(15, $this->item->fresh()->received_quantity);
        $this->assertEquals(0, $this->stock->fresh()->quantity);
        $this->assertDatabaseHas('mm_quantity', ['plant_id' => $this->plant->id,
            'product_id' => $this->inward->product_id, 'uom_id' => $unit->id, 'quantity' => 15]);
        $this->updateDetails(['uom_id' => 99999999])->assertSessionHasErrors('uom_id');
    }

    public function test_weight_edit_keeps_stock_in_the_original_received_unit(): void
    {
        $unitId = $this->inward->uom_id;
        $this->updateDetails(['uom_id' => $unitId, 'truck_empty' => 30])->assertSessionHasNoErrors();
        $this->assertEquals($unitId, $this->inward->fresh()->uom_id);
        $this->assertEquals(70, $this->stock->fresh()->quantity);
        $this->assertEquals(70, $this->inward->fresh()->received_qty);
        $this->assertEquals(70, $this->item->fresh()->received_quantity);
        $this->assertDatabaseHas('mm_quantity', [
            'plant_id' => $this->plant->id, 'product_id' => $this->inward->product_id,
            'uom_id' => $unitId, 'quantity' => 70,
        ]);
    }

    public function test_billed_receipt_cannot_change_units(): void
    {
        $this->item->update(['invoiced_quantity' => 10]);
        $this->updateDetails(['uom_id' => ProductUnit::factory()->create()->id])->assertSessionHasErrors('uom_id');
        $this->assertEquals($this->stock->uom_id, $this->inward->fresh()->uom_id);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
    }

    public function test_conversion_edit_does_not_recalculate_accepted_quantity(): void
    {
        $unit = ProductUnit::factory()->create();
        $this->updateDetails(['conversion_uom_id' => $unit->id, 'conversion_quantity' => 2.5])->assertSessionHasNoErrors();
        $this->assertEquals($unit->id, $this->inward->fresh()->conversion_uom_id);
        $this->assertEquals(2.5, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['conversion_uom_id' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->inward->fresh()->conversion_uom_id);
    }

    public function test_convert_volume_is_saved_and_only_recalculates_billing_quantity(): void
    {
        $this->updateDetails(['convert_volume' => 4.5, 'conversion_quantity' => 999])->assertSessionHasNoErrors();
        $this->assertEquals(4.5, $this->inward->fresh()->convert_volume);
        $this->assertEquals(2.22, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->updateDetails(['received_qty' => 18])->assertSessionHasNoErrors();
        $this->assertEquals(4, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(18, $this->stock->fresh()->quantity);
        $this->updateDetails(['convert_volume' => null])->assertSessionHasNoErrors();
        $this->assertNull($this->inward->fresh()->convert_volume);
        $this->assertEquals(4, $this->inward->fresh()->conversion_quantity);
        $this->updateDetails(['convert_volume' => null, 'conversion_quantity' => 6.127])->assertSessionHasNoErrors();
        $this->assertEquals(6.13, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(18, $this->stock->fresh()->quantity);
        $this->updateDetails(['convert_volume' => 0])->assertSessionHasErrors('convert_volume');
        $this->updateDetails(['convert_volume' => -1])->assertSessionHasErrors('convert_volume');
        $this->item->update(['invoiced_quantity' => 18]);
        $this->updateDetails(['convert_volume' => 2])->assertSessionHasErrors('inward');
    }

    public function test_manual_converted_quantity_change_is_saved_with_an_existing_factor(): void
    {
        $this->inward->update(['convert_volume' => 4, 'conversion_quantity' => 2.5]);
        $this->updateDetails(['conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals(3, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
    }

    public function test_direct_stock_quantity_update_preserves_weights_and_explicit_billing_conversion(): void
    {
        $this->updateDetails(['received_qty' => 15, 'conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals(15, $this->inward->fresh()->received_qty);
        $this->assertEquals(15, $this->item->fresh()->received_quantity);
        $this->assertEquals(15, $this->stock->fresh()->quantity);
        $this->assertEquals(3, $this->inward->fresh()->conversion_quantity);
        $this->assertEquals(100, $this->inward->fresh()->truck_loaded);
        $this->assertEquals(20, $this->inward->fresh()->truck_empty);
    }

    public function test_inward_number_can_be_corrected_without_changing_billed_quantities(): void
    {
        $this->item->update(['invoiced_quantity' => 10]);

        $this->updateDetails(['inward_no' => 'GRN/00042-A'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('GRN/00042-A', $this->inward->fresh()->inward_no);
        $this->assertEquals(10, $this->inward->fresh()->received_qty);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
        $this->assertEquals(10, $this->item->fresh()->invoiced_quantity);
        $this->updateDetails(['inward_no' => '  '])->assertSessionHasErrors('inward_no');
        $this->updateDetails(['received_qty' => 5])->assertSessionHasErrors('inward');
    }

    public function test_unbilled_inward_can_change_when_an_earlier_inward_is_billed(): void
    {
        $later = $this->inward->replicate();
        $later->inward_no = 'INW-LATER';
        $later->save();
        $this->item->update(['received_quantity' => 20, 'invoiced_quantity' => 10]);
        $this->stock->update(['quantity' => 20]);
        $this->post(route('inwards.update-weight', $later), ['received_qty' => 15, 'conversion_quantity' => 3])->assertSessionHasNoErrors();
        $this->assertEquals(15, $later->fresh()->received_qty);
        $this->assertEquals(25, $this->stock->fresh()->quantity);
        $this->assertEquals(10, $this->item->fresh()->invoiced_quantity);
        $this->updateDetails(['received_qty' => 5])->assertSessionHasErrors('inward');
    }

    public function test_foreign_plant_truck_and_receipt_are_rejected(): void
    {
        $foreign = Plant::factory()->create();
        $truck = Machine::factory()->create(['plant_id' => $foreign->id]);
        $this->updateDetails(['truck_id' => $truck->id])->assertSessionHasErrors('truck_id');
        $this->withSession(['active_plant_id' => $foreign->id]);
        $this->updateDetails(['truck_id' => null])->assertNotFound();
    }

    public function test_saving_an_inward_does_not_grant_purchase_bill_permission(): void
    {
        $user = auth()->user();
        $user->syncRoles([]);
        $this->plant->update(['is_active' => 1]);
        \App\Models\Entity::whereKey($this->plant->entity_id)->update(['is_suspended' => 0]);
        $role = Role::firstOrCreate(['name' => 'Inward Editor', 'guard_name' => 'web'], ['code' => 'INWARD_EDITOR']);
        \App\Models\EntityUser::create([
            'user_id' => $user->id, 'entity_id' => $this->plant->entity_id,
            'plant_id' => $this->plant->id, 'role_id' => $role->id,
        ]);
        $permission = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'INWARD.UPDATE', 'guard_name' => 'web']);
        $user->syncPermissions([$permission]);
        $this->updateDetails(['truck_id' => null])->assertRedirect()->assertSessionHasNoErrors();
        $this->updateDetails([
            'truck_empty' => 30,
            'generate_bill' => true,
            'bill' => ['invoice_date' => '2026-10-06', 'unit_price' => 100],
        ])->assertForbidden();
        $this->assertEquals(20, $this->inward->fresh()->truck_empty);
        $this->assertEquals(10, $this->stock->fresh()->quantity);
    }
}
