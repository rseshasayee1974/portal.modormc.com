<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Http\Controllers\Api\BatchOcrController;
use Illuminate\Http\UploadedFile;
use Smalot\PdfParser\Parser;

class BatchOcrTest extends TestCase
{
    /**
     * Helper to invoke the private/protected methods on BatchOcrController
     * or to simulate the controller parsing logic.
     */
    private function parsePdfWithController(string $filePath): array
    {
        $parser = new Parser();
        $pdf = $parser->parseFile($filePath);
        $text = $pdf->getText();

        // Invoke the controller's extraction using a reflection wrapper
        $controller = new BatchOcrController();
        $reflector = new \ReflectionClass(BatchOcrController::class);
        
        $method = $reflector->getMethod('extractMaterialsFromText');
        $method->setAccessible(true);
        
        return $method->invoke($controller, $text);
    }

    public function test_parsing_file_1()
    {
        $filePath = 'c:\\Users\\muthu\\Herd\\portal.modormc.com\\storage\\app\\public\\batch-sheets\\originals\\8FAAHHrmsaEyfJadT8DmwVtc2u3ousuerXFBUPOP.pdf';
        
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
        $filePath = 'c:\\Users\\muthu\\Herd\\portal.modormc.com\\storage\\app\\public\\batch-sheets\\originals\\GpVIHGKTo9QhQdV6hqxCdCkVYJw926WTO8DSgJvF.pdf';
        
        $this->assertFileExists($filePath);
        $materials = $this->parsePdfWithController($filePath);

        $this->assertNotEmpty($materials);
        $this->assertCount(1, $materials);

        $this->assertEquals('SAND', $materials[0]['item']);
        $this->assertEquals(0, $materials[0]['actual']);
    }

    public function test_parsing_file_3()
    {
        $filePath = 'c:\\Users\\muthu\\Herd\\portal.modormc.com\\storage\\app\\public\\batch-sheets\\originals\\m7seBadNqWnsBqER2ct5lVaj680HNn8FQIL1Tos7.pdf';
        
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
