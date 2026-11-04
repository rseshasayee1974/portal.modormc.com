<?php
/*
Author: Cipher
Created: 2026-10-15 11:55:02 Asia/Calcutta (UTC+05:30)
*/

namespace App\Http\Middleware;

use App\Services\Mobile\MobileAccess;
use App\Models\Personnel;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Initialize visibility once, after MobilePlantContext has verified the plant. */
class MobileSalesScope
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless($request->user() && MobileAccess::plantId() > 0, 403, 'A mobile plant context is required.');

        $request->attributes->set('mobile_sales_executive', MobileAccess::isSalesExecutive());
        MobileAccess::personnelId();

        $response = $next($request);
        if (! $response instanceof JsonResponse || ! $response->isSuccessful()) {
            return $response;
        }

        $data = $response->getData(true);
        $ids = [];
        $collect = function ($value) use (&$collect, &$ids): void {
            if (! is_array($value)) return;
            if (isset($value['sales_executive_id'])) {
                $ids[] = $value['sales_executive_id'];
            }
            foreach ($value as $child) $collect($child);
        };
        $collect($data);

        $executives = empty($ids) ? collect() : Personnel::withoutGlobalScope('plant_id')
            ->where('plant_id', MobileAccess::plantId())
            ->whereIn('id', array_unique($ids))
            ->get(['id', 'first_name', 'last_name', 'employee_code', 'plant_id'])
            ->keyBy('id');

        $enrich = function ($value) use (&$enrich, $executives) {
            if (! is_array($value)) return $value;
            foreach ($value as $key => $child) $value[$key] = $enrich($child);
            if (array_key_exists('sales_executive_id', $value)) {
                $value['sales_executive'] = $executives->get($value['sales_executive_id'])?->toArray();
            }
            return $value;
        };
        $response->setData($enrich($data));

        return $response;
    }
}
