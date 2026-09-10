<?php

namespace App\Services\QC;

use App\Models\QC\QcTestParameter;
use Exception;

class FormulaEngine
{
    /**
     * Evaluate a mathematical formula string with parameter code variables.
     *
     * Example formula: "(WET_WEIGHT - DRY_WEIGHT) / DRY_WEIGHT * 100"
     * Variables map: ['WET_WEIGHT' => 1000, 'DRY_WEIGHT' => 960]
     */
    public function evaluateFormula(string $formula, array $variables): ?float
    {
        if (empty(trim($formula))) {
            return null;
        }

        // Replace variable codes with numeric values (case-insensitive key mapping)
        $processedFormula = $formula;
        
        // Normalize variables array to uppercase keys
        $upperVariables = [];
        foreach ($variables as $code => $val) {
            $upperVariables[strtoupper($code)] = $val;
        }

        // Sort keys by length descending to avoid partial variable replacements
        uksort($upperVariables, fn($a, $b) => strlen($b) <=> strlen($a));

        // Evaluate TIMEDIFF_MINUTES function: TIMEDIFF_MINUTES(start, end)
        $processedFormula = preg_replace_callback('/\b(timediff_minutes|timediff)\s*\(([^,]+),([^)]+)\)/i', function ($matches) use ($upperVariables) {
            $rawStart = trim($matches[2]);
            $rawEnd = trim($matches[3]);

            // If tokens are variable names, fetch their original string/time value
            $startVal = $upperVariables[strtoupper($rawStart)] ?? $rawStart;
            $endVal = $upperVariables[strtoupper($rawEnd)] ?? $rawEnd;

            $toMinutes = function($v) {
                if (is_numeric($v)) return (float)$v;
                $cleaned = trim((string)$v, " '\"");
                if (preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $cleaned, $m)) {
                    return ((int)$m[1] * 60) + (int)$m[2];
                }
                $ts = strtotime($cleaned);
                if ($ts !== false) {
                    return (int)($ts / 60);
                }
                return 0;
            };

            $startMin = $toMinutes($startVal);
            $endMin = $toMinutes($endVal);
            $diff = $endMin - $startMin;
            if ($diff < 0) $diff += (24 * 60); // handle day turnover e.g. 23:30 to 01:00

            return (string)$diff;
        }, $processedFormula);

        foreach ($upperVariables as $code => $val) {
            if ($val === null || $val === '') {
                // If the variable was already consumed by timediff, don't fail
                if (preg_match('/\b' . preg_quote($code, '/') . '\b/i', $processedFormula)) {
                    return null;
                }
                continue;
            }
            $numericVal = is_numeric($val) ? (float) $val : 0;
            // Case-insensitive regex replacement for variable tokens
            $processedFormula = preg_replace('/\b' . preg_quote($code, '/') . '\b/i', (string) $numericVal, $processedFormula);
        }

        // Evaluate statistical functions: mean(...), median(...), mode(...), min(...), max(...), round(...), abs(...), sqrt(...)
        $processedFormula = preg_replace_callback('/\b(mean|avg|median|mode|min|max|round|abs|sqrt)\s*\(([^()]+)\)/i', function ($matches) {
            $func = strtolower($matches[1]);
            $argsStr = $matches[2];
            $rawArgs = explode(',', $argsStr);
            $args = array_map(fn($a) => (float)trim($a), $rawArgs);

            if (empty($args)) return '0';

            switch ($func) {
                case 'mean':
                case 'avg':
                    return (string)(array_sum($args) / count($args));
                case 'median':
                    sort($args);
                    $count = count($args);
                    $mid = (int) floor($count / 2);
                    if ($count % 2 === 0) {
                        return (string)(($args[$mid - 1] + $args[$mid]) / 2);
                    }
                    return (string)$args[$mid];
                case 'mode':
                    $values = array_map('strval', $args);
                    $counts = array_count_values($values);
                    arsort($counts);
                    $keys = array_keys($counts);
                    return (string)($keys[0] ?? 0);
                case 'min':
                    return (string)min($args);
                case 'max':
                    return (string)max($args);
                case 'round':
                    $val = $args[0] ?? 0;
                    $prec = isset($args[1]) ? (int)$args[1] : 2;
                    return (string)round($val, $prec);
                case 'abs':
                    return (string)abs($args[0] ?? 0);
                case 'sqrt':
                    return (string)sqrt(max(0, $args[0] ?? 0));
                default:
                    return '0';
            }
        }, $processedFormula);

        // Check if all variables were replaced (no unresolved words left)
        if (preg_match('/[A-Za-z_]+/', $processedFormula)) {
            return null; // Unresolved variable in formula
        }

        // Safe mathematical evaluation using string parser
        try {
            return $this->calculateMath($processedFormula);
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Evaluate an acceptance rule against a given numeric or text result.
     *
     * Returns 'PASS' or 'FAIL'
     */
    public function evaluateRule(object|array $rule, mixed $val): string
    {
        if ($val === null || $val === '') {
            return 'NONE';
        }

        $ruleType = is_array($rule) ? ($rule['rule_type'] ?? null) : ($rule->rule_type ?? null);
        $minValue = is_array($rule) ? ($rule['min_value'] ?? null) : ($rule->min_value ?? null);
        $maxValue = is_array($rule) ? ($rule['max_value'] ?? null) : ($rule->max_value ?? null);
        $targetValue = is_array($rule) ? ($rule['target_value'] ?? null) : ($rule->target_value ?? null);
        $tolerance = is_array($rule) ? ($rule['tolerance'] ?? null) : ($rule->tolerance ?? null);

        $numericVal = is_numeric($val) ? (float) $val : null;

        switch ($ruleType) {
            case QcTestParameter::RULE_TYPE_RANGE:
                if ($numericVal === null) return 'FAIL';
                $minOk = $minValue === null || $numericVal >= (float) $minValue;
                $maxOk = $maxValue === null || $numericVal <= (float) $maxValue;
                return ($minOk && $maxOk) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_GREATER_THAN:
                if ($numericVal === null) return 'FAIL';
                return ($minValue !== null && $numericVal > (float) $minValue) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_GREATER_THAN_OR_EQUAL:
                if ($numericVal === null) return 'FAIL';
                return ($minValue !== null && $numericVal >= (float) $minValue) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_LESS_THAN:
                if ($numericVal === null) return 'FAIL';
                return ($maxValue !== null && $numericVal < (float) $maxValue) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_LESS_THAN_OR_EQUAL:
                if ($numericVal === null) return 'FAIL';
                return ($maxValue !== null && $numericVal <= (float) $maxValue) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_EQUAL:
                if ($numericVal !== null && $targetValue !== null) {
                    return (abs($numericVal - (float) $targetValue) < 0.0001) ? 'PASS' : 'FAIL';
                }
                return (trim((string) $val) === trim((string) $targetValue)) ? 'PASS' : 'FAIL';

            case QcTestParameter::RULE_TYPE_TARGET_TOLERANCE:
                if ($numericVal === null || $targetValue === null) return 'FAIL';
                $tol = (float) ($tolerance ?? 0);
                $target = (float) $targetValue;
                return ($numericVal >= ($target - $tol) && $numericVal <= ($target + $tol)) ? 'PASS' : 'FAIL';

            default:
                return 'NONE';
        }
    }

    /**
     * Safe recursive descent mathematical parser.
     */
    private function calculateMath(string $expression): float
    {
        // Remove whitespace
        $expr = str_replace(' ', '', $expression);

        // Basic sanity check: only allow digits, decimals, operators, parentheses
        if (!preg_match('/^[0-9\.\+\-\*\/\(\)\^]+$/', $expr)) {
            throw new Exception("Invalid math characters in expression: $expr");
        }

        return $this->parseSum($expr);
    }

    private function parseSum(string &$expr): float
    {
        $result = $this->parseProduct($expr);

        while (strlen($expr) > 0) {
            $op = $expr[0];
            if ($op !== '+' && $op !== '-') {
                break;
            }
            $expr = substr($expr, 1);
            $next = $this->parseProduct($expr);
            if ($op === '+') {
                $result += $next;
            } else {
                $result -= $next;
            }
        }

        return $result;
    }

    private function parseProduct(string &$expr): float
    {
        $result = $this->parseFactor($expr);

        while (strlen($expr) > 0) {
            $op = $expr[0];
            if ($op !== '*' && $op !== '/') {
                break;
            }
            $expr = substr($expr, 1);
            $next = $this->parseFactor($expr);
            if ($op === '*') {
                $result *= $next;
            } else {
                if ($next == 0) {
                    throw new Exception("Division by zero");
                }
                $result /= $next;
            }
        }

        return $result;
    }

    private function parseFactor(string &$expr): float
    {
        if (strlen($expr) > 0 && $expr[0] === '(') {
            $expr = substr($expr, 1); // remove '('
            $result = $this->parseSum($expr);
            if (strlen($expr) > 0 && $expr[0] === ')') {
                $expr = substr($expr, 1); // remove ')'
            }
            return $result;
        }

        preg_match('/^[-+]?[0-9]*\.?[0-9]+/', $expr, $matches);
        if (!empty($matches)) {
            $numStr = $matches[0];
            $expr = substr($expr, strlen($numStr));
            return (float) $numStr;
        }

        throw new Exception("Syntax error parsing factor near: $expr");
    }
}
