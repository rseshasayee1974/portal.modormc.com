<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\QC\FormulaEngine;
use App\Models\QC\QcTestParameter;

class FormulaEngineTest extends TestCase
{
    private FormulaEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FormulaEngine();
    }

    public function test_evaluates_compressive_strength_formula(): void
    {
        // 800 kN on 150mm cube = 800 / 22.5 = 35.5555...
        $result = $this->engine->evaluateFormula('LOAD_KN / 22.5', ['LOAD_KN' => 800]);
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(35.555, $result, 0.01);
    }

    public function test_evaluates_moisture_content_formula(): void
    {
        // (1000 - 960) / 960 * 100 = 40 / 960 * 100 = 4.1666...
        $result = $this->engine->evaluateFormula(
            '(WET_WEIGHT - DRY_WEIGHT) / DRY_WEIGHT * 100',
            ['WET_WEIGHT' => 1000, 'DRY_WEIGHT' => 960]
        );
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(4.166, $result, 0.01);
    }

    public function test_evaluates_aggregate_impact_value_formula(): void
    {
        // 75g passing / 350g total * 100 = 21.428%
        $result = $this->engine->evaluateFormula(
            'MASS_PASSING_B / MASS_MOULD_A * 100',
            ['MASS_PASSING_B' => 75, 'MASS_MOULD_A' => 350]
        );
        $this->assertNotNull($result);
        $this->assertEqualsWithDelta(21.428, $result, 0.01);
    }

    public function test_evaluates_acceptance_rule_range(): void
    {
        $rule = new QcTestParameter([
            'rule_type' => 'RANGE',
            'min_value' => 100,
            'max_value' => 150,
        ]);

        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 125));
        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 100));
        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 150));
        $this->assertEquals('FAIL', $this->engine->evaluateRule($rule, 95));
        $this->assertEquals('FAIL', $this->engine->evaluateRule($rule, 160));
    }

    public function test_evaluates_acceptance_rule_greater_than_or_equal(): void
    {
        $rule = new QcTestParameter([
            'rule_type' => 'GREATER_THAN_OR_EQUAL',
            'min_value' => 25.0,
        ]);

        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 25.0));
        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 34.5));
        $this->assertEquals('FAIL', $this->engine->evaluateRule($rule, 24.9));
    }

    public function test_evaluates_acceptance_rule_target_tolerance(): void
    {
        $rule = new QcTestParameter([
            'rule_type' => 'TARGET_TOLERANCE',
            'target_value' => 1.15,
            'tolerance' => 0.05,
        ]);

        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 1.15));
        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 1.12));
        $this->assertEquals('PASS', $this->engine->evaluateRule($rule, 1.20));
        $this->assertEquals('FAIL', $this->engine->evaluateRule($rule, 1.09));
        $this->assertEquals('FAIL', $this->engine->evaluateRule($rule, 1.21));
    }
}
