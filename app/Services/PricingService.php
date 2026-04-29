<?php

namespace App\Services;

use App\Models\ApiModule;

class PricingService
{
    public function calculate(string $moduleName, int $tokensUsed): array
    {
        $module = ApiModule::query()->where('name', $moduleName)->firstOrFail();

        $tokenCost = ($tokensUsed / 1000) * (float) $module->price_per_1000_tokens;
        $requestCost = (float) $module->price_per_request;
        $total = $tokenCost + $requestCost;

        return [
            'module' => $moduleName,
            'tokens_used' => $tokensUsed,
            'token_cost' => round($tokenCost, 4),
            'request_cost' => round($requestCost, 4),
            'total_cost' => round($total, 4),
        ];
    }
}
