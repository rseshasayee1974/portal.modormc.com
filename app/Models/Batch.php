<?php

namespace App\Models;
use App\Traits\PlantScoping;
use App\Traits\TracksModelChanges;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Batch extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_batches';

    protected $fillable = [
        'plant_id',
        'sales_order_id',
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
        'is_verified',
        'verified_at',
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

    protected $appends = ['sheet_url', 'original_sheet_url', 'rate', 'tax_id', 'is_tax_inclusive', 'encrypted_id'];

    protected $hidden = [
        'created_by',
        'updated_by',
        'deleted_by',
        'deleted_at',
    ];

    public function getEncryptedIdAttribute(): string
    {
        return encrypt($this->id);
    }

    public function getSheetUrlAttribute()
    {
        return $this->batch_sheet_path ? \Illuminate\Support\Facades\Storage::url($this->batch_sheet_path) : null;
    }

    public function getOriginalSheetUrlAttribute()
    {
        return $this->batch_original_sheet_path ? \Illuminate\Support\Facades\Storage::url($this->batch_original_sheet_path) : null;
    }

    public function getRateAttribute()
    {
        return $this->salesOrder ? (float)$this->salesOrder->rate : 0.0;
    }

    public function getTaxIdAttribute()
    {
        return $this->salesOrder ? $this->salesOrder->tax_id : null;
    }

    public function getIsTaxInclusiveAttribute(): bool
    {
        return $this->salesOrder ? (bool)$this->salesOrder->is_tax_inclusive : false;
    }

    protected $casts = [
        'batch_size' => 'decimal:2',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_verified' => 'boolean',
        'verified_at' => 'datetime',
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
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }
    public function salesOrder()
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
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

    public function getFormattedMaterials(string $mode = 'run')
    {
        $materials = $this->materials;

        if ($mode === 'run' || $materials->isEmpty()) {
            return $materials->map(function ($mat) {
                $target = (float) $mat->target_qty;
                $actual = (float) $mat->actual_qty;
                $devVal = (float) $mat->deviation_quantity;
                $devPercent = $target > 0 ? ($devVal / $target) * 100 : 0;

                $rawName = $mat->material_name ?: ($mat->product->title ?? 'Material');
                $cleanName = preg_replace('/\s*[-–—]?\s*Run\s*[-–—]?\s*\d+\s*$/i', '', $rawName);

                return (object) [
                    'material_name' => $cleanName,
                    'target_qty' => $target,
                    'actual_qty' => $actual,
                    'deviation_quantity' => $devVal,
                    'deviation_percent' => $devPercent,
                    'uom_code' => $mat->uom->unit_code ?? 'KGS',
                ];
            });
        }

        // Estimate run size using the first recipe item that matches
        $runSize = null;
        $mixDesign = $this->salesOrder?->mixDesign ?? $this->workOrder?->mixDesign;

        if ($mixDesign) {
            $recipeItems = $mixDesign->items;
            foreach ($materials as $mat) {
                if ($mat->product_id) {
                    $recipeItem = $recipeItems->firstWhere('product_id', $mat->product_id);
                    if ($recipeItem && (float) $recipeItem->actual_quantity > 0 && (float) $mat->target_qty > 0) {
                        $runSize = (float) $mat->target_qty / (float) $recipeItem->actual_quantity;
                        break;
                    }
                }
            }
        }

        // Fallback if we cannot estimate the run size
        if ($runSize === null || $runSize <= 0) {
            $runSize = 1.0;
        }

        $batchSize = (float) $this->batch_size;
        if ($batchSize <= 0) {
            $batchSize = 1.0;
        }

        // Define scale factor
        // For 'mix_design': target/actual should be per 1 m3, i.e., raw_qty / runSize
        // For 'batch_size': target/actual should be for total load, i.e., raw_qty * (batchSize / runSize)
        $scaleFactor = ($mode === 'mix_design') ? (1.0 / $runSize) : ($batchSize / $runSize);

        return $materials->map(function ($mat) use ($scaleFactor) {
            $target = (float) $mat->target_qty * $scaleFactor;
            $actual = (float) $mat->actual_qty * $scaleFactor;
            $devVal = $actual - $target;
            $devPercent = $target > 0 ? ($devVal / $target) * 100 : 0;

            $rawName = $mat->material_name ?: ($mat->product->title ?? 'Material');
            $cleanName = preg_replace('/\s*[-–—]?\s*Run\s*[-–—]?\s*\d+\s*$/i', '', $rawName);

            return (object) [
                'material_name' => $cleanName,
                'target_qty' => $target,
                'actual_qty' => $actual,
                'deviation_quantity' => $devVal,
                'deviation_percent' => $devPercent,
                'uom_code' => $mat->uom->unit_code ?? 'KGS',
            ];
        });
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

    public function resolveRouteBinding($value, $field = null)
    {
        try {
            $decrypted = decrypt($value);
            return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $decrypted)->first();
        } catch (\Exception $e) {
            try {
                $decrypted = \Illuminate\Support\Facades\Crypt::decryptString($value);
                return $this->withTrashed()->where($field ?? $this->getRouteKeyName(), $decrypted)->first();
            } catch (\Exception $e2) {
                if (is_numeric($value)) {
                    return parent::resolveRouteBinding($value, $field);
                }
                return null;
            }
        }
    }
}