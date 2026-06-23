<?php

namespace App\Services\BatchSheet\DTOs;

class ParsedDocument
{
    public string $rawText = '';
    public array $headerFields = []; // key-value pairs (raw labels to raw values)
    public array $materialRows = []; // array of ['material_name', 'target_qty', 'actual_qty', 'deviation_quantity']
    public float $confidence = 100.0;
    public string $parserUsed = '';
    public array $fieldScores = [];  // confidence score per field (key => 0-100)
    public array $metadata = [];

    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }
    }
}
