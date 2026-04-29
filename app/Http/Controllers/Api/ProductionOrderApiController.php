<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MixDesign;
use App\Models\Patron;
use App\Models\Plant;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;

class ProductionOrderApiController extends Controller
{
    public function store(Request $request)
    {
        $raw = $request->json()->all() ?: $request->all();
        $payload = $this->normalizePayload($raw);

        $validator = Validator::make($payload, [
            'order_no' => ['required', 'string', 'max:100'],
            'order_date' => ['nullable', 'date'],
            'order_status' => ['nullable', 'string', 'max:20'],
            'plant_code' => ['required', 'string', 'max:100'],
            'customer_code' => ['nullable', 'string', 'max:100'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'site_code' => ['nullable', 'string', 'max:100'],
            'site_name' => ['nullable', 'string', 'max:255'],
            'mix_design_code' => ['nullable', 'string', 'max:100'],
            'mix_design_name' => ['nullable', 'string', 'max:255'],
            'production_qty' => ['required', 'numeric', 'gt:0'],
            'materials' => ['nullable', 'array'],
            'materials.*.product_id' => ['nullable', 'string', 'max:255'],
            'materials.*.tar' => ['nullable'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payload validation failed.',
                'errors' => $validator->errors(),
            ], 422);
        }

        if (!Schema::hasTable('mm_work_orders')) {
            return response()->json([
                'status' => 'error',
                'message' => 'Work order table is not available.',
            ], 500);
        }

        $plant = Plant::query()->where('code', $payload['plant_code'])->first();
        if (!$plant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid plant code.',
            ], 422);
        }

        $customer = $this->resolveCustomer($payload, $plant->id);
        $site = $this->resolveSite($payload, $plant->id);
        $mixDesign = $this->resolveMixDesign($payload, $plant->id);

        $isNewSchema = Schema::hasColumn('mm_work_orders', 'order_no');
        $orderColumn = $isNewSchema ? 'order_no' : 'work_order_number';
        if (!Schema::hasColumn('mm_work_orders', $orderColumn)) {
            return response()->json([
                'status' => 'error',
                'message' => 'No usable work order identifier column found.',
            ], 500);
        }

        $record = $this->buildWorkOrderRecord($payload, $plant->id, $customer?->id, $site?->id, $mixDesign?->id);
        $record[$orderColumn] = $payload['order_no'];

        DB::transaction(function () use ($orderColumn, $payload, $record) {
            $existing = DB::table('mm_work_orders')
                ->where($orderColumn, $payload['order_no'])
                ->first();

            if ($existing) {
                $record['updated_at'] = now();
                if (Schema::hasColumn('mm_work_orders', 'modified')) {
                    $record['modified'] = now();
                }
                DB::table('mm_work_orders')
                    ->where('id', $existing->id)
                    ->update($record);
            } else {
                if (Schema::hasColumn('mm_work_orders', 'created_at')) {
                    $record['created_at'] = now();
                }
                if (Schema::hasColumn('mm_work_orders', 'updated_at')) {
                    $record['updated_at'] = now();
                }
                if (Schema::hasColumn('mm_work_orders', 'created')) {
                    $record['created'] = now();
                }
                DB::table('mm_work_orders')->insert($record);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Production order accepted.',
            'order_no' => $payload['order_no'],
            'plant_code' => $payload['plant_code'],
            'resolved' => [
                'plant_id' => $plant->id,
                'customer_id' => $customer?->id,
                'site_id' => $site?->id,
                'mix_design_id' => $mixDesign?->id,
            ],
        ]);
    }

    private function normalizePayload(array $raw): array
    {
        return [
            'plant_code' => data_get($raw, 'plants.code') ?? ($raw['plants.code'] ?? null),
            'order_no' => data_get($raw, 'order_no'),
            'order_date' => data_get($raw, 'order_date'),
            'order_status' => (string) (data_get($raw, 'order_status') ?? '1'),
            'customer_code' => data_get($raw, 'mm_patrons.code') ?? data_get($raw, 'patrons.code') ?? ($raw['mm_patrons.code'] ?? null),
            'customer_name' => data_get($raw, 'mm_patrons.legal_name') ?? ($raw['mm_patrons.legal_name'] ?? null),
            'site_code' => data_get($raw, 'mm_site.code') ?? ($raw['mm_site.code'] ?? null),
            'site_name' => data_get($raw, 'mm_site.name') ?? ($raw['mm_site.name'] ?? null),
            'mix_design_code' => data_get($raw, 'mix_design_id'),
            'mix_design_name' => data_get($raw, 'design_name'),
            'production_qty' => data_get($raw, 'production_qty'),
            'materials' => data_get($raw, 'mat', []),
        ];
    }

    private function resolveCustomer(array $payload, int $plantId): ?Patron
    {
        if (!empty($payload['customer_code'])) {
            $row = Patron::query()->where('plant_id', $plantId)->where('code', $payload['customer_code'])->first();
            if ($row) {
                return $row;
            }
        }

        if (!empty($payload['customer_name'])) {
            return Patron::query()
                ->where('plant_id', $plantId)
                ->where('legal_name', $payload['customer_name'])
                ->first();
        }

        return null;
    }

    private function resolveSite(array $payload, int $plantId): ?Site
    {
        if (!empty($payload['site_code'])) {
            $row = Site::query()->where('plant_id', $plantId)->where('code', $payload['site_code'])->first();
            if ($row) {
                return $row;
            }
        }

        if (!empty($payload['site_name'])) {
            return Site::query()
                ->where('plant_id', $plantId)
                ->where('name', $payload['site_name'])
                ->first();
        }

        return null;
    }

    private function resolveMixDesign(array $payload, int $plantId): ?MixDesign
    {
        if (!empty($payload['mix_design_code'])) {
            $row = MixDesign::query()->where('plant_id', $plantId)->where('design_code', $payload['mix_design_code'])->first();
            if ($row) {
                return $row;
            }
        }

        if (!empty($payload['mix_design_name'])) {
            return MixDesign::query()
                ->where('plant_id', $plantId)
                ->where('design_name', $payload['mix_design_name'])
                ->first();
        }

        return null;
    }

    private function buildWorkOrderRecord(array $payload, int $plantId, ?int $customerId, ?int $siteId, ?int $mixDesignId): array
    {
        $record = [];

        if (Schema::hasColumn('mm_work_orders', 'prefix')) {
            $record['prefix'] = 'WO';
        }
        if (Schema::hasColumn('mm_work_orders', 'plant_id')) {
            $record['plant_id'] = $plantId;
        }
        if (Schema::hasColumn('mm_work_orders', 'customer_id') && $customerId) {
            $record['customer_id'] = $customerId;
        }
        if (Schema::hasColumn('mm_work_orders', 'site_id') && $siteId) {
            $record['site_id'] = $siteId;
        }
        if (Schema::hasColumn('mm_work_orders', 'mix_design_id') && $mixDesignId) {
            $record['mix_design_id'] = $mixDesignId;
        }
        if (Schema::hasColumn('mm_work_orders', 'total_qty')) {
            $record['total_qty'] = (float) $payload['production_qty'];
        }
        if (Schema::hasColumn('mm_work_orders', 'quantity')) {
            $record['quantity'] = (float) $payload['production_qty'];
        }
        if (Schema::hasColumn('mm_work_orders', 'produced_qty')) {
            $record['produced_qty'] = 0;
        }
        if (Schema::hasColumn('mm_work_orders', 'status')) {
            $record['status'] = $this->mapStatus($payload['order_status']);
        }
        if (Schema::hasColumn('mm_work_orders', 'scheduled_start') && !empty($payload['order_date'])) {
            $record['scheduled_start'] = $payload['order_date'];
        }
        if (Schema::hasColumn('mm_work_orders', 'scheduled_end') && !empty($payload['order_date'])) {
            $record['scheduled_end'] = $payload['order_date'];
        }

        return $record;
    }

    private function mapStatus(string $status): int
    {
        return match (trim($status)) {
            '2' => 2,
            '3' => 3,
            '4' => 4,
            default => 1,
        };
    }
}
