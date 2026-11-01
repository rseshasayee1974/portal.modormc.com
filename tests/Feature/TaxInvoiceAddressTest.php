<?php

/*
Author: ragul-onemodo
Created: 2026-11-01 09:44:39 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\Address;
use App\Models\Contact;
use App\Models\Patron;
use App\Models\Site;
use App\Services\PrintDataFormatter;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

class TaxInvoiceAddressTest extends TestCase
{
    private function customer(): Patron
    {
        $contactAddress = (new Address(['line_1' => 'Billing Road', 'line_2' => 'Suite 4', 'city' => 'Chennai', 'zipcode' => '600001']))->setRelation('state', null);
        $contact = (new Contact(['is_primary' => true]))->setRelation('addresses', collect([$contactAddress]));
        return (new Patron(['legal_name' => 'Test Customer', 'gstin' => '33TEST1234A1Z5']))
            ->setRelation('contacts', collect([$contact]))->setRelation('addresses', collect());
    }

    private function renderAddresses(array $settings): string
    {
        $template = file_get_contents(resource_path('views/pdfs/invoices/tax_invoice.blade.php'));
        $start = strpos($template, '<!-- Col 1: Customer Billing Address -->');
        $end = strpos($template, '<!-- Col 3: Customer Ref -->', $start);
        $data = PrintDataFormatter::taxInvoiceAddresses($this->customer(), new Site([
            'name' => 'Delivery Site', 'site_address_1' => 'Shipping Road', 'site_address_2' => 'Gate 2', 'city' => 'Madurai', 'zipcode' => '625001',
        ]));
        return Blade::render('<table><tr>'.substr($template, $start, $end - $start).'</tr></table>', [
            'billTo' => $data['bill_to'], 'shipTo' => $data['ship_to'], 'pdfSettings' => $settings,
        ]);
    }

    public function test_primary_customer_contact_address_and_both_site_lines_are_used(): void
    {
        $data = PrintDataFormatter::taxInvoiceAddresses($this->customer(), new Site([
            'name' => 'Delivery Site', 'site_address_1' => 'Shipping Road', 'site_address_2' => 'Gate 2', 'city' => 'Madurai', 'zipcode' => '625001',
        ]));
        $this->assertSame('Billing Road Suite 4', $data['bill_to']['address']);
        $this->assertSame('Shipping Road, Gate 2', $data['ship_to']['address']);
        $this->assertSame('Madurai', $data['ship_to']['city']);
        $this->assertSame('625001', $data['ship_to']['pin']);
        $this->assertSame('33TEST1234A1Z5', $data['ship_to']['gstin']);
    }

    public function test_customer_address_is_the_fallback_when_no_shipping_site_exists(): void
    {
        $data = PrintDataFormatter::taxInvoiceAddresses($this->customer(), null);
        $this->assertSame($data['bill_to']['address'], $data['ship_to']['address']);
        $this->assertSame($data['bill_to']['name'], $data['ship_to']['name']);
        $this->assertSame($data['bill_to']['pin'], $data['ship_to']['pin']);
        $empty = PrintDataFormatter::taxInvoiceAddresses(null, null);
        $this->assertSame('', $empty['bill_to']['address']);
        $this->assertSame('', $empty['ship_to']['address']);
    }

    public function test_customer_direct_address_still_works_without_contacts(): void
    {
        $address = (new Address(['line_1' => 'Direct Billing Road', 'city' => 'Salem', 'zipcode' => '636001']))->setRelation('state', null);
        $customer = (new Patron(['legal_name' => 'Direct Customer']))->setRelation('contacts', collect())->setRelation('addresses', collect([$address]));
        $data = PrintDataFormatter::taxInvoiceAddresses($customer, null);
        $this->assertSame('Direct Billing Road', trim($data['bill_to']['address']));
        $this->assertSame('636001', $data['bill_to']['pin']);
    }

    public function test_real_blade_blocks_follow_each_switch_and_are_enabled_by_default(): void
    {
        foreach ([[true, true], [false, true], [true, false], [false, false]] as [$bill, $ship]) {
            $html = $this->renderAddresses(['bill_to' => $bill, 'ship_to' => $ship]);
            $this->assertSame($bill, str_contains($html, '>Customer Billing Address</div>'));
            $this->assertSame($bill, str_contains($html, 'Billing Road Suite 4'));
            $this->assertSame($ship, str_contains($html, '>Customer Shipping Address</div>'));
            $this->assertSame($ship, str_contains($html, 'Shipping Road, Gate 2'));
        }
        $html = $this->renderAddresses([]);
        $this->assertStringContainsString('Billing Road Suite 4', $html);
        $this->assertStringContainsString('Shipping Road, Gate 2', $html);
    }

    public function test_invoice_address_switches_load_from_the_invoice_plant_settings(): void
    {
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));
        DB::statement('CREATE TABLE mm_custom_settings (id INTEGER PRIMARY KEY, plant_id INTEGER, module_name TEXT, settings TEXT, deleted_at TEXT)');
        DB::table('mm_custom_settings')->insert([
            ['plant_id' => 1, 'module_name' => 'invoices', 'settings' => json_encode(['pdf' => ['bill_to' => false, 'ship_to' => true]])],
            ['plant_id' => 2, 'module_name' => 'invoices', 'settings' => json_encode(['pdf' => ['bill_to' => true, 'ship_to' => false]])],
        ]);
        $html = $this->renderAddresses(PrintDataFormatter::getCustomSettings(1, 'invoices')['pdf']);
        $this->assertStringNotContainsString('Billing Road Suite 4', $html);
        $this->assertStringContainsString('Shipping Road, Gate 2', $html);
        $other = $this->renderAddresses(PrintDataFormatter::getCustomSettings(2, 'invoices')['pdf']);
        $this->assertStringContainsString('Billing Road Suite 4', $other);
        $this->assertStringNotContainsString('Shipping Road, Gate 2', $other);
    }
}
