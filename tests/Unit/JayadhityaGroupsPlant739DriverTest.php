<?php

/*
 * Author: ragul-onemodo
 * Created: 2026-10-31 09:22:57 Asia/Calcutta (UTC+05:30)
 */

namespace Tests\Unit;

use App\Services\BatchSheet\Drivers\JayadhityaGroupsPlant739Driver;
use App\Services\BatchSheet\Drivers\PlantDriverRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

class JayadhityaGroupsPlant739DriverTest extends TestCase
{
    // Header and total rows transcribed from the supplied GOKUL 2.pdf.
    private const TEXT = <<<'TEXT'
Batch Number : 7227	Recipe Name : M35 MARS	Mixer Capacity : 1.00
Batch Date : 27-10-2026	Recipe Code : M35 MARS	Batch Size : 1.0000
Batch Start Time : 23:11:17	Truck No : TN12AC8311	Production Qty : 2.00
Batch End Time : 23:15:13	Truck Driver : Kali	Order No : MARS RMC
Customer : MARS RMC	Adj / Manual Qty : 0.00	Ordered Qty : 100.00
Site : ALAMATHI	With This Load : 2.00
Mass of Recipe Targets in kg : 2462
Actual Values in kg
368 427 632 544 0 0 325 0 53 0 0 126 10 0 0 2.03 0.00 0.00 0.00 0.0
371 426 627 548 0 0 320 0 50 0 0 128 10 0 0 1.99 0.00 0.00 0.00 0.0
Total Set Weight in kg
744 850 1254 1094 0 0 640 0 100 0 0 256 0 0 0 4.00 0.00 0.00 0.00 0.0
Mass of Total Set Weight in kg : 4942.00
Total Actual Weight in kg
739 853 1259 1092 0 0 645 0 103 0 0 254 0 0 0 4.02 0.00 0.00 0.00 0.0
Mass of Total Actual Weight in kg : 4949.02
Difference in Percentage
-0.67 0.4 0.4 -0.2 0.0 0.0 0.8 0.0 3.0 0.0 0.0 -0.8 0 0.0 0.0 0.5 0.0 0.0 0.0 0.0
Total Mass Difference in Percentage : 0.14
BATCH SHEET REPORT
JAYADHITYA GROUPS
Plant Type : M1 C	Plant Sl.No : 739
TEXT;

    public function test_extracts_headers_without_adjacent_column_values(): void
    {
        $parsed = (new JayadhityaGroupsPlant739Driver)->parse(self::TEXT);
        $header = $parsed['headerFields'];
        self::assertSame('7227', $header['batch_number']);
        self::assertSame('2026-10-27', $header['batch_date']);
        self::assertSame('23:11:17', $header['batch_start_time']);
        self::assertSame('23:15:13', $header['batch_end_time']);
        self::assertSame('MARS RMC', $header['customer']);
        self::assertSame('M35 MARS', $header['recipe_name']);
        self::assertSame('M35 MARS', $header['recipe_code']);
        self::assertSame('TN12AC8311', $header['truck_number']);
        self::assertSame('Kali', $header['driver']);
        self::assertSame('MARS RMC', $header['order_number']);
        self::assertSame('ALAMATHI', $header['site']);
        self::assertSame('M1 C', $header['plant_type']);
        self::assertSame('739', $header['plant_serial']);
        self::assertSame(1.0, $header['batch_size']);
        self::assertSame(2.0, $header['production_qty']);
        self::assertSame(100.0, $header['ordered_qty']);
        self::assertSame(0.0, $header['manual_qty']);
        self::assertSame(2462.0, $header['recipe_target_weight']);
        self::assertSame(4942.0, $header['total_set_weight']);
        self::assertSame(4949.02, $header['total_actual_weight']);
        self::assertSame(0.14, $header['total_difference_percent']);

        $rows = array_column($parsed['materialRows'], null, 'material_name');
        self::assertCount(8, $rows);
        self::assertSame(739.0, $rows['MSAND 1']['actual_qty']);
        self::assertSame(-5.0, $rows['MSAND 1']['deviation_quantity']);
        self::assertSame(103.0, $rows['CEMENT 3']['actual_qty']);
        self::assertSame(254.0, $rows['WATER']['actual_qty']);
        self::assertSame(4.02, $rows['ADMIX 1']['actual_qty']);
        self::assertEqualsWithDelta(4949.02, array_sum(array_column($rows, 'actual_qty')), 0.001);
    }

    public function test_registry_discovers_driver_and_matches_exact_serial(): void
    {
        $previousLogger = Log::getFacadeRoot();
        Log::swap(new NullLogger);
        try {
            $registry = new PlantDriverRegistry;
            self::assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $registry->resolve(self::TEXT));
            self::assertInstanceOf(JayadhityaGroupsPlant739Driver::class, $registry->resolve('', ['plant_serial' => '739']));
        } finally {
            Log::swap($previousLogger);
        }

        $driver = new JayadhityaGroupsPlant739Driver;
        self::assertFalse($driver->canHandle(str_replace('Plant Sl.No : 739', 'Plant Sl.No : 1739', self::TEXT)));
        self::assertFalse($driver->canHandle("BATCH SHEET REPORT\nPlant Sl.No : 121\nTotal Actual Weight in kg\n739"));
    }

    public function test_values_are_read_from_document_and_missing_fields_are_not_invented(): void
    {
        $text = str_replace(['MARS RMC', 'Ordered Qty : 100.00'], ['ANOTHER CUSTOMER', 'Ordered Qty : 0.00'], self::TEXT);
        $text = preg_replace('/Batch Size :[^\n]+/', '', $text);
        $headers = (new JayadhityaGroupsPlant739Driver)->parse($text)['headerFields'];
        self::assertSame('ANOTHER CUSTOMER', $headers['customer']);
        self::assertSame(0.0, $headers['ordered_qty']);
        self::assertArrayNotHasKey('batch_size', $headers);
    }

    public function test_incomplete_material_matrix_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new JayadhityaGroupsPlant739Driver)->parse(str_replace('744 850 1254', '744 850', self::TEXT));
    }
}
