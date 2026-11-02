<?php
/*
Author: ragul-onemodo
Created: 2026-11-02 16:48:32 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Unit;

use App\Services\BatchSheet\Drivers\JayadhityaPlant2787Driver;
use App\Services\BatchSheet\Drivers\PlantDriverRegistry;
use Illuminate\Support\Facades\Log;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use UnexpectedValueException;

class JayadhityaPlant2787DriverTest extends TestCase
{
    // Transcribed from the user-supplied docket 2470, in visual reading order.
    public const TEXT = <<<'TEXT'
SBR CONSTRUCTIONS
MCI 370 Control System Ver 1.0
Batch Date : 01-Nov-2026 Plant Serial Number : 2787
Batch Start Time : 07:54:48 AM
Batch End Time : 08:03:15 AM
Batch Number / Docket Number : 2470 Ordered Quantity : 20.00 M3
Customer : VIMALNATH Production Quantity : 6.00 M3
Site : THIRUMAZHISAI Adj/Manual Quantity : 0.00 M3
Recipe Code : M25 JAI ADHITYA With This Load : 12.00 M3
Recipe Name : M25 JAI ADHITA Mixer Capacity : 1.00 M3
Truck Number : TN18AS5947 Batch Size : 1.00 M3
Truck Driver : CHITRASAN Net Wt From W.Bridge 0.00 Kg
Order Number : VIMALNATH
Batcher Name : Stetter
Actual in Kgs.
0 806 0.0 516 615 0 0 82 260 129 0 0 0 0.00 0.00
0 816 0.0 500 617 0 0 81 263 129 0 0 0 0.00 0.00
0 831 0.0 509 629 0 0 79 261 129 0 0 0 0.00 0.00
0 849 0.0 496 634 0 0 74 258 130 0 0 0 0.00 0.00
0 852 0.0 484 647 0 0 82 259 130 0 0 0 0.00 0.00
0 907 0.0 453 677 0 0 84 261 131 0 0 0 0.00 0.00
Total Set Weight in Kgs.
0 5100 2940 3840 0 0 480 1560 780 0 0.00 0.00
Total Actual in Kgs.
0 5061 2958 3819 0 0 482 1562 778 0 0.00 0.00
TEXT;

    public function test_extracts_actual_headers_load_quantity_totals_and_each_mixer_cycle(): void
    {
        $result = (new JayadhityaPlant2787Driver)->parse(self::TEXT);
        $header = $result['headerFields'];
        self::assertSame('2470', $header['batch_number']);
        self::assertSame('2026-11-01', $header['batch_date']);
        self::assertSame('07:54:48', $header['batch_start_time']);
        self::assertSame('08:03:15', $header['batch_end_time']);
        self::assertSame('VIMALNATH', $header['customer']);
        self::assertSame('THIRUMAZHISAI', $header['site']);
        self::assertSame('M25 JAI ADHITYA', $header['recipe_code']);
        self::assertSame('TN18AS5947', $header['truck_number']);
        self::assertSame(6.0, $header['batch_size']);
        self::assertSame(1.0, $header['run_size']);
        self::assertSame(12.0, $header['with_this_load']);
        $materials = array_column($result['materialRows'], null, 'material_name');
        self::assertCount(6, $materials);
        self::assertSame(5100.0, $materials['MSAND 1']['target_qty']);
        self::assertSame(5061.0, $materials['MSAND 1']['actual_qty']);
        self::assertSame([806.0, 816.0, 831.0, 849.0, 852.0, 907.0], $materials['MSAND 1']['runs']);
        self::assertSame(482.0, $materials['CEMENT 2']['actual_qty']);
        self::assertSame(778.0, $materials['WATER']['actual_qty']);
    }

    public function test_registry_discovers_and_matches_the_serial_without_matching_other_plants(): void
    {
        $previous = Log::getFacadeRoot();
        Log::swap(new NullLogger);
        try {
            $registry = new PlantDriverRegistry;
            self::assertInstanceOf(JayadhityaPlant2787Driver::class, $registry->resolve(self::TEXT));
            self::assertInstanceOf(JayadhityaPlant2787Driver::class, $registry->resolve('', ['plant_serial' => '2787']));
        } finally { Log::swap($previous); }
        self::assertFalse((new JayadhityaPlant2787Driver)->canHandle(str_replace('2787', '12787', self::TEXT)));
    }

    public function test_missing_materials_and_inconsistent_cycle_totals_are_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new JayadhityaPlant2787Driver)->parse(str_replace('0 5061 2958', '0 5062 2958', self::TEXT));
    }

    public function test_incomplete_matrix_is_rejected(): void
    {
        $this->expectException(UnexpectedValueException::class);
        (new JayadhityaPlant2787Driver)->parse(str_replace('0 5100 2940', '0 5100', self::TEXT));
    }
}
