<?php
/*
Author: ragul-onemodo
Created: 2026-11-28 18:15:38 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Services\QualitySampleDirectory;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;

class QualitySampleDirectoryTest extends TestCase
{
    public function test_directory_pages_tests_and_searches_across_pages_within_the_selected_plant(): void
    {
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());
        $tables = [
            'mm_sales_orders' => 'plant_id INTEGER, order_no TEXT, site_id INTEGER, customer_id INTEGER',
            'mm_dispatches' => 'plant_id INTEGER, sales_order_id INTEGER, batch_id INTEGER, dispatch_time TEXT, dispatch_no TEXT, dispatch_status TEXT',
            'mm_batches' => 'plant_id INTEGER, status INTEGER, batch_no TEXT',
            'mm_qc_samples' => 'plant_id INTEGER, dispatch_id INTEGER, batch_id INTEGER, customer_id INTEGER, sample_no TEXT, site_name TEXT, truck_no TEXT, source_location TEXT, concrete_grade_id INTEGER, material_id INTEGER',
            'mm_qc_tests' => 'sample_id INTEGER, age_days INTEGER, overall_status TEXT, test_no TEXT, tested_by INTEGER',
            'mm_patrons' => 'plant_id INTEGER, legal_name TEXT, operational_status TEXT',
            'mm_sites' => 'plant_id INTEGER, name TEXT',
            'mm_concrete_grades' => 'plant_id INTEGER, name TEXT',
            'mm_products' => 'plant_id INTEGER, title TEXT',
            'mm_users' => 'username TEXT',
            'mm_dispatch_statuses' => 'dispatch_id INTEGER, invoice_id INTEGER, invoice_number TEXT',
            'mm_invoices' => 'plant_id INTEGER, invoice_number TEXT',
        ];
        foreach ($tables as $table => $columns) {
            DB::statement("CREATE TABLE {$table} (id INTEGER PRIMARY KEY, deleted_at TEXT, {$columns})");
        }
        DB::table('mm_qc_samples')->insert([
            ['id' => 1, 'plant_id' => 1, 'batch_id' => 1, 'sample_no' => 'Sample A'],
            ['id' => 2, 'plant_id' => 1, 'batch_id' => null, 'sample_no' => 'Standalone'],
            ['id' => 3, 'plant_id' => 2, 'batch_id' => null, 'sample_no' => 'Other plant'],
        ]);
        for ($i = 1; $i <= 35; $i++) {
            DB::table('mm_qc_tests')->insert(['id' => $i, 'sample_id' => 1, 'age_days' => 7,
                'overall_status' => 'pass', 'test_no' => "TEST-{$i}"]);
        }
        DB::table('mm_sales_orders')->insert(['id' => 1, 'plant_id' => 1, 'order_no' => 'SO-123']);
        DB::table('mm_batches')->insert(['id' => 1, 'plant_id' => 1, 'status' => 4]);
        foreach ([1 => 3, 2 => 7, 3 => 2] as $id => $age) {
            DB::table('mm_dispatches')->insert(['id' => $id, 'plant_id' => 1, 'sales_order_id' => 1,
                'batch_id' => $id === 1 ? 1 : null, 'dispatch_no' => "DP-{$id}", 'dispatch_status' => 'Dispatched',
                'dispatch_time' => now('Asia/Kolkata')->subDays($age)->toDateString().' 12:00:00']);
        }
        $directory = new QualitySampleDirectory();
        $get = fn (array $filters = [], bool $picker = false) => $directory->page(1, $filters, [7, 15, 28], 'Asia/Kolkata', $picker);
        $first = $get();
        $second = $get(['page' => 2]);
        $this->assertSame(37, $first['total']);
        $this->assertCount(30, $first['entries']);
        $this->assertCount(7, $second['entries']);
        $all = $first['entries']->concat($second['entries']);
        $this->assertCount(35, $all->pluck('test_id')->filter()->unique());
        $this->assertContains(35, $second['entries']->pluck('test_id')->all());
        $this->assertSame([35], $get(['search' => 'test-35'])['entries']->pluck('test_id')->all());
        $this->assertSame(0, $get(['search' => 'Other plant'])['total']);
        $this->assertSame(35, $get(['age' => 7, 'status' => 'pass'])['total']);
        $this->assertSame(1, $get(['status' => 'not_sampled'])['total']);
        $this->assertSame(0, $get(['search' => 'DP-3'])['total']);
        $this->assertSame(1, $get(['search' => 'DP-3'], true)['total']);
        $this->assertSame(1, $get(['search' => 'standalone'])['total']);
        $this->assertSame(0, $get(['search' => '%'])['total']);
        $this->assertSame(2, $get(['page' => 9])['page']);
    }
}
