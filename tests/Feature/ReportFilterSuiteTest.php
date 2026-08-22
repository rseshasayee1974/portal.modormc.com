<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Plant;
use Tests\TestCase;

class ReportFilterSuiteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $user = User::first() ?? User::factory()->create();
        $this->actingAs($user);
        session(['active_plant_id' => 1]);
    }

    /** @test */
    public function general_ledger_report_executes_with_filters()
    {
        $response = $this->get(route('reports.generate', [
            'type'       => 'ledger',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-08-20',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['transactions', 'opening_balance']);
    }

    /** @test */
    public function purchase_inward_report_executes_with_filters()
    {
        $response = $this->get(route('reports.generate', [
            'type'       => 'inventory_inward',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-08-20',
            'patron_id'  => 9,
            'truck_id'   => 10,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['transactions', 'total_quantity']);
    }

    /** @test */
    public function purchase_summary_report_executes_with_filters()
    {
        $response = $this->get(route('reports.generate', [
            'type'       => 'purchase',
            'start_date' => '2026-01-01',
            'end_date'   => '2026-08-20',
            'patron_id'  => 9,
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['transactions', 'product_summary', 'total_amount']);
    }

    /** @test */
    public function purchase_register_report_executes_with_filters()
    {
        $response = $this->get(route('reports.purchase-register', [
            'from_date'   => '2026-01-01',
            'to_date'     => '2026-08-20',
            'supplier_id' => 9,
            'gst_type'    => 'intra',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'totals', 'pagination']);
    }

    /** @test */
    public function sales_register_report_executes_with_filters()
    {
        $response = $this->get(route('reports.sales-register', [
            'from_date'      => '2026-01-01',
            'to_date'        => '2026-08-20',
            'customer_id'    => 1,
            'gst_type'       => 'intra',
            'payment_status' => 'paid',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['data', 'totals']);
    }

    /** @test */
    public function silo_stock_valuation_report_executes_with_filters()
    {
        $response = $this->get(route('reports.generate', [
            'type'             => 'silo_stock_valuation',
            'start_date'       => '2026-01-01',
            'end_date'         => '2026-08-20',
            'valuation_method' => 'FIFO',
        ]));

        $response->assertStatus(200);
        $response->assertJsonStructure(['transactions', 'total_ending_value_formatted']);
    }
}
