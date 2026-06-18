<?php

namespace App\Models;

use App\Traits\AuditFields;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping ,TracksModelChanges;

    protected $table = 'mm_batches';

    protected $fillable = [
        'plant_id',
        'work_order_id',
        'batch_no',
        'batch_size',
        'start_time',
        'end_time',
        'status',
        'operator_id',
        'shift',
        'sync_status',
        'batch_sheet_path',
        'batch_original_sheet_path',
        'created_by',
        'updated_by',
        'deleted_by',
        // 'truck_id',
        // 'transport_id',
        // 'driver_id',
        // 'empty_weight_truck',
        // 'loaded_weight_truck',
        // 'net_weight',
        // 'conversion_quantity',
        // 'uom_id',
        // 'site_id',
    ];

    protected $appends = ['sheet_url', 'original_sheet_url'];

    public function getSheetUrlAttribute()
    {
        return $this->batch_sheet_path ? \Illuminate\Support\Facades\Storage::url($this->batch_sheet_path) : null;
    }

    public function getOriginalSheetUrlAttribute()
    {
        return $this->batch_original_sheet_path ? \Illuminate\Support\Facades\Storage::url($this->batch_original_sheet_path) : null;
    }

    protected $casts = [
        'batch_size' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
    ];

    public const STATUS_PLANNED = 1;
    public const STATUS_LOADING = 2;
    public const STATUS_DISPATCHED = 3;
    public const STATUS_COMPLETED = 4;
    public const STATUS_CANCELLED = 5;

    public static function statusOptions(): array
    {
        return [
            ['label' => 'Planned', 'value' => self::STATUS_PLANNED],
            ['label' => 'Loading', 'value' => self::STATUS_LOADING],
            ['label' => 'Dispatched', 'value' => self::STATUS_DISPATCHED],
            ['label' => 'Completed', 'value' => self::STATUS_COMPLETED],
            ['label' => 'Cancelled', 'value' => self::STATUS_CANCELLED],
        ];
    }

    public static function statusLabel(int $status): string
    {
        return collect(self::statusOptions())->firstWhere('value', $status)['label'] ?? 'Unknown';
    }

    public function operator()
    {
        return $this->belongsTo(Personnel::class, 'operator_id');
    }

    public function truck()
    {
        return $this->belongsTo(Machine::class, 'truck_id');
    }

    public function transport()
    {
        return $this->belongsTo(Patron::class, 'transport_id');
    }

    public function driver()
    {
        return $this->belongsTo(Personnel::class, 'driver_id');
    }

    public function uom()
    {
        return $this->belongsTo(ProductUnit::class, 'uom_id');
    }

    public function site()
    {
        return $this->belongsTo(Site::class, 'site_id');
    }

    public function workOrder()
    {
        return $this->belongsTo(WorkOrder::class, 'work_order_id');
    }



    public function materials()
    {
        return $this->hasMany(BatchMaterial::class, 'batch_id');
    }

    public function photos()
    {
        return $this->hasMany(Image::class, 'ref_no', 'id')->where('category', 'Batching');
    }

    public function dispatches()
    {
        return $this->hasMany(Dispatch::class, 'batch_id');
    }

    public function getReportData(): array
    {
        $materials = $this->materials->map(function ($material) {
            $name = (string) ($material->material_name ?: ($material->product->title ?? 'Material'));
            $categoryName = (string) ($material->product->category->name ?? '');
            
            return [
                'key' => $material->id,
                'name' => $name,
                'category_name' => $categoryName,
                'short' => strtoupper(substr(preg_replace('/\s+/', '', $name), 0, 6)),
                'target' => (float) $material->target_qty,
                'actual' => (float) $material->actual_qty,
                'diff_percent' => (float) ($material->deviation_quantity ?? 0),
            ];
        })->values();

        $groups = [
            ['name' => 'Aggregate', 'keywords' => ['SAND', 'AGG', '10MM', '12MM', '20MM', 'DUST', 'GGBS']],
            ['name' => 'Cement', 'keywords' => ['CEM', 'CEMENT', 'FLY', 'OPC', 'PPC']],
            ['name' => 'Water / Ice', 'keywords' => ['WTR', 'WATER', 'ICE']],
            ['name' => 'Admixture', 'keywords' => ['ADM', 'ADMI', 'CHEM', 'RET']],
            ['name' => 'Silica', 'keywords' => ['SIL', 'SILICA', 'FUME']],
        ];

        $grouped = [];
        foreach ($groups as $group) {
            $grouped[$group['name']] = [];
        }

        foreach ($materials as $material) {
            $upperName = strtoupper($material['name']);
            $upperCategory = strtoupper($material['category_name']);
            $matched = false;

            foreach ($groups as $group) {
                foreach ($group['keywords'] as $keyword) {
                    if (str_contains($upperName, $keyword) || str_contains($upperCategory, $keyword)) {
                        $grouped[$group['name']][] = $material;
                        $matched = true;
                        break 2;
                    }
                }
            }

            if (!$matched) {
                $grouped['Aggregate'][] = $material;
            }
        }

        $groupOrder = array_map(fn ($group) => $group['name'], $groups);
        $tableMaterials = [];
        foreach ($groupOrder as $groupName) {
            if (empty($grouped[$groupName])) {
                $placeholder = [
                    'key' => 'placeholder-' . $groupName,
                    'name' => '-',
                    'category_name' => $groupName,
                    'short' => '-',
                    'target' => 0.0,
                    'actual' => 0.0,
                    'diff_percent' => 0.0,
                    'is_placeholder' => true,
                ];
                $grouped[$groupName][] = $placeholder;
                $tableMaterials[] = $placeholder;
            } else {
                foreach ($grouped[$groupName] as $entry) {
                    $tableMaterials[] = $entry;
                }
            }
        }

        $totalSetWeight = collect($tableMaterials)->sum('target');
        $totalActualWeight = collect($tableMaterials)->sum('actual');
        $totalDifferencePercent = $totalSetWeight > 0
            ? (($totalActualWeight - $totalSetWeight) / $totalSetWeight) * 100
            : 0;

        return [
            'group_order' => $groupOrder,
            'grouped' => $grouped,
            'table_materials' => $tableMaterials,
            'total_set_weight' => round($totalSetWeight, 2),
            'total_actual_weight' => round($totalActualWeight, 2),
            'total_difference_percent' => round($totalDifferencePercent, 2),
        ];
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function modifier()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}