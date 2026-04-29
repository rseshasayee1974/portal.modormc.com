<?php

namespace App\Services\WorkOrders;

use App\Models\MixDesign;
use App\Models\Plant;
use App\Models\WorkOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WorkOrderIndexDataFactory
{
    public function build(?int $activePlantId): array
    {
        $schema = $this->schemaFlags();
        return [
            'workOrders' => $this->buildWorkOrdersQuery($activePlantId, $schema)->get()->toArray(),
            'plants' => $activePlantId ? Plant::query()->where('id', $activePlantId)->get(['id', 'name'])->toArray() : [],
            'customers' => $activePlantId ? PatronsDropdown($activePlantId, ['Customer'])->toArray() : [],
            'sites' => $activePlantId ? SitesDropdown($activePlantId)->toArray() : [],
            'mixDesigns' => $activePlantId ? $this->loadMixDesigns($activePlantId, $schema)->toArray() : [],
            'statuses' => WorkOrder::statusOptions(),
            'activePlantId' => $activePlantId,
            'nextReference' => WorkOrder::generateOrderNo('WO'),
        ];
    }

    private function schemaFlags(): array
    {
        $hasMixDesignsTable = Schema::hasTable('mm_mix_designs');

        return [
            'hasPlantIdColumn' => Schema::hasColumn('mm_work_orders', 'plant_id'),
            'hasPrefixColumn' => Schema::hasColumn('mm_work_orders', 'prefix'),
            'hasOrderNoColumn' => Schema::hasColumn('mm_work_orders', 'order_no'),
            'hasTotalQtyColumn' => Schema::hasColumn('mm_work_orders', 'total_qty'),
            'hasProducedQtyColumn' => Schema::hasColumn('mm_work_orders', 'produced_qty'),
            'hasCustomerIdColumn' => Schema::hasColumn('mm_work_orders', 'customer_id'),
            'hasSiteIdColumn' => Schema::hasColumn('mm_work_orders', 'site_id'),
            'hasMixDesignIdColumn' => Schema::hasColumn('mm_work_orders', 'mix_design_id'),
            'hasCreatedAtColumn' => Schema::hasColumn('mm_work_orders', 'created_at'),
            'hasLegacyCreatedColumn' => Schema::hasColumn('mm_work_orders', 'created'),
            'hasMixDesignsTable' => $hasMixDesignsTable,
            'hasMixDesignGradeColumn' => $hasMixDesignsTable && Schema::hasColumn('mm_mix_designs', 'grade'),
            'hasMixDesignDeletedAtColumn' => $hasMixDesignsTable && Schema::hasColumn('mm_mix_designs', 'deleted_at'),
            'hasMixDesignItemsTable' => Schema::hasTable('mm_mix_design_items'),
            'hasBatchesRelation' => Schema::hasTable('mm_batches') && Schema::hasColumn('mm_batches', 'work_order_id'),
            'hasDispatchesRelation' => Schema::hasTable('mm_dispatches') && Schema::hasColumn('mm_dispatches', 'work_order_id'),
        ];
    }

    private function buildWorkOrdersQuery(?int $activePlantId, array $schema): Builder
    {
        $query = WorkOrder::query()->select('mm_work_orders.*');

        if ($schema['hasPlantIdColumn'] && $activePlantId) {
            $query->where('plant_id', $activePlantId);
        }

        $relations = [];
        if ($schema['hasPlantIdColumn']) {
            $relations[] = 'plant:id,name';
        }
        if ($schema['hasCustomerIdColumn']) {
            $relations[] = 'customer:id,legal_name';
        }
        if ($schema['hasSiteIdColumn']) {
            $relations[] = 'site:id,name';
        }
        if ($schema['hasMixDesignIdColumn']) {
            $relations[] = $schema['hasMixDesignGradeColumn']
                ? 'mixDesign:id,design_name,design_code,grade'
                : 'mixDesign:id,design_name,design_code';
        }
        if (!empty($relations)) {
            $query->with($relations);
        }

        if (!$schema['hasPrefixColumn']) {
            $query->addSelect(DB::raw("'WO' as prefix"));
        }
        if (!$schema['hasOrderNoColumn']) {
            $query->addSelect(DB::raw("COALESCE(work_order_number, CONCAT('WO-', id)) as order_no"));
        }
        if (!$schema['hasTotalQtyColumn']) {
            $query->addSelect(DB::raw('COALESCE(quantity, 0) as total_qty'));
        }
        if (!$schema['hasProducedQtyColumn']) {
            $query->addSelect(DB::raw('0 as produced_qty'));
        }
        if (!$schema['hasCustomerIdColumn']) {
            $query->addSelect(DB::raw('NULL as customer_id'));
        }
        if (!$schema['hasSiteIdColumn']) {
            $query->addSelect(DB::raw('NULL as site_id'));
        }
        if (!$schema['hasMixDesignIdColumn']) {
            $query->addSelect(DB::raw('NULL as mix_design_id'));
        }
        if (!$schema['hasPlantIdColumn']) {
            $query->addSelect(DB::raw((int) ($activePlantId ?? 0) . ' as plant_id'));
        }

        if ($schema['hasBatchesRelation']) {
            $query->withCount('batches');
        } else {
            $query->addSelect(DB::raw('0 as batches_count'));
        }

        if ($schema['hasDispatchesRelation']) {
            $query->withCount('dispatches');
        } else {
            $query->addSelect(DB::raw('0 as dispatches_count'));
        }

        if ($schema['hasCreatedAtColumn']) {
            $query->latest();
        } elseif ($schema['hasLegacyCreatedColumn']) {
            $query->orderByDesc('created');
        } else {
            $query->orderByDesc('id');
        }

        return $query;
    }

    private function loadMixDesigns(?int $activePlantId, array $schema): Collection
    {
        if (!$schema['hasMixDesignsTable']) {
            return collect();
        }

        $query = MixDesign::query()
            ->where('plant_id', $activePlantId)
            ->orderBy('design_name');

        if ($schema['hasMixDesignItemsTable']) {
            $query->with(['items.product', 'items.uom', 'concrete_grade']);
        }

        if ($schema['hasMixDesignDeletedAtColumn']) {
            $query->whereNull('deleted_at');
        }

        $selectColumns = ['id', 'design_name', 'design_code', 'design_type'];
        if ($schema['hasMixDesignGradeColumn']) {
            $selectColumns[] = 'grade';
        }

        return $query->get($selectColumns)->map(function ($row) {
            $ingredients = collect();

            if (isset($row->items)) {
                foreach ($row->items as $item) {
                    $ingredients->push([
                        'id' => 'item-' . $item->id,
                        'name' => $item->product?->title ?? 'Unknown Material',
                        'qty' => (float) ($item->actual_quantity ?? 0),
                        'uom' => $item->uom?->unit_code ?? '',
                    ]);
                }
            }

            return [
                'id' => $row->id,
                'design_name' => $row->design_name,
                'design_code' => $row->design_code ?? 'N/A',
                'grade' => $row->concrete_grade->name ?? 'N/A',
                 'ratio' => $row->concrete_grade->concrete_ratio ?? 'N/A',
                'ingredients' => $ingredients->values(),
            ];  
        });
    }
}
