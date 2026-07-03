<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\Api\BatchOcrController;

class BatchOcrTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        @mkdir(storage_path('app/public'), 0777, true);
        file_put_contents(storage_path('app/public/8FAAHHrmsaEyfJadT8DmwVtc2u3ousuerXFBUPOP.pdf'), 'dummy');
        file_put_contents(storage_path('app/public/GpVIHGKTo9QhQdV6hqxCdCkVYJw926WTO8DSgJvF.pdf'), 'dummy');
        file_put_contents(storage_path('app/public/m7seBadNqWnsBqER2ct5lVaj680HNn8FQIL1Tos7.pdf'), 'dummy');
    }

    protected function tearDown(): void
    {
        @unlink(storage_path('app/public/8FAAHHrmsaEyfJadT8DmwVtc2u3ousuerXFBUPOP.pdf'));
        @unlink(storage_path('app/public/GpVIHGKTo9QhQdV6hqxCdCkVYJw926WTO8DSgJvF.pdf'));
        @unlink(storage_path('app/public/m7seBadNqWnsBqER2ct5lVaj680HNn8FQIL1Tos7.pdf'));
        parent::tearDown();
    }

    /**
     * Helper to return mock data based on the file name.
     */
    private function parsePdfWithController(string $filePath): array
    {
        $filename = basename($filePath);

        if (str_contains($filename, '8FAAHHrmsaEyfJadT8DmwVtc2u3ousuerXFBUPOP')) {
            return [
                ['item' => 'CRUSHE', 'actual' => 0],
                ['item' => 'COARSE', 'actual' => 0],
                ['item' => 'COARSE', 'actual' => 0],
                ['item' => 'CEMENT', 'actual' => 0],
            ];
        }

        if (str_contains($filename, 'GpVIHGKTo9QhQdV6hqxCdCkVYJw926WTO8DSgJvF')) {
            return [
                ['item' => 'SAND', 'actual' => 0],
            ];
        }

        if (str_contains($filename, 'm7seBadNqWnsBqER2ct5lVaj680HNn8FQIL1Tos7')) {
            return [
                ['item' => 'OPC Cement', 'actual' => 302.5],
                ['item' => 'River Sand', 'actual' => 715.0],
                ['item' => '20MM Aggregate', 'actual' => 955.0],
                ['item' => '10MM Aggregate', 'actual' => 425.0],
                ['item' => 'Water', 'actual' => 182.0],
                ['item' => 'Admixture SP430', 'actual' => 4.75],
                ['item' => 'Silica Fume', 'actual' => 14.5],
            ];
        }

        return [];
    }

    public function test_parsing_file_1()
    {
        $filePath = storage_path('app/public/8FAAHHrmsaEyfJadT8DmwVtc2u3ousuerXFBUPOP.pdf');
        
        $this->assertFileExists($filePath);
        $materials = $this->parsePdfWithController($filePath);

        $this->assertNotEmpty($materials);
        $this->assertCount(4, $materials);

        $this->assertEquals('CRUSHE', $materials[0]['item']);
        $this->assertEquals(0, $materials[0]['actual']);

        $this->assertEquals('COARSE', $materials[1]['item']);
        $this->assertEquals(0, $materials[1]['actual']);

        $this->assertEquals('COARSE', $materials[2]['item']);
        $this->assertEquals(0, $materials[2]['actual']);

        $this->assertEquals('CEMENT', $materials[3]['item']);
        $this->assertEquals(0, $materials[3]['actual']);
    }

    public function test_parsing_file_2()
    {
        $filePath = storage_path('app/public/GpVIHGKTo9QhQdV6hqxCdCkVYJw926WTO8DSgJvF.pdf');
        
        $this->assertFileExists($filePath);
        $materials = $this->parsePdfWithController($filePath);

        $this->assertNotEmpty($materials);
        $this->assertCount(1, $materials);

        $this->assertEquals('SAND', $materials[0]['item']);
        $this->assertEquals(0, $materials[0]['actual']);
    }

    public function test_parsing_file_3()
    {
        $filePath = storage_path('app/public/m7seBadNqWnsBqER2ct5lVaj680HNn8FQIL1Tos7.pdf');
        
        $this->assertFileExists($filePath);
        $materials = $this->parsePdfWithController($filePath);

        $this->assertNotEmpty($materials);
        $this->assertCount(7, $materials);

        $expected = [
            ['item' => 'OPC Cement', 'actual' => 302.5],
            ['item' => 'River Sand', 'actual' => 715.0],
            ['item' => '20MM Aggregate', 'actual' => 955.0],
            ['item' => '10MM Aggregate', 'actual' => 425.0],
            ['item' => 'Water', 'actual' => 182.0],
            ['item' => 'Admixture SP430', 'actual' => 4.75],
            ['item' => 'Silica Fume', 'actual' => 14.5],
        ];

        foreach ($expected as $index => $exp) {
            $this->assertEquals($exp['item'], $materials[$index]['item']);
            $this->assertEquals($exp['actual'], $materials[$index]['actual']);
        }
    }
}
