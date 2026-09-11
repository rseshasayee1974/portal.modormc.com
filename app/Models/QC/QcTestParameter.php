<?php

namespace App\Models\QC;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\User;

class QcTestParameter extends Model
{
    use HasFactory, SoftDeletes;

    public const RULE_TYPE_RANGE = 'RANGE';
    public const RULE_TYPE_GREATER_THAN = 'GREATER_THAN';
    public const RULE_TYPE_GREATER_THAN_OR_EQUAL = 'GREATER_THAN_OR_EQUAL';
    public const RULE_TYPE_LESS_THAN = 'LESS_THAN';
    public const RULE_TYPE_LESS_THAN_OR_EQUAL = 'LESS_THAN_OR_EQUAL';
    public const RULE_TYPE_EQUAL = 'EQUAL';
    public const RULE_TYPE_TARGET_TOLERANCE = 'TARGET_TOLERANCE';

    public const RULE_TYPES = [
        self::RULE_TYPE_RANGE,
        self::RULE_TYPE_GREATER_THAN,
        self::RULE_TYPE_GREATER_THAN_OR_EQUAL,
        self::RULE_TYPE_LESS_THAN,
        self::RULE_TYPE_LESS_THAN_OR_EQUAL,
        self::RULE_TYPE_EQUAL,
        self::RULE_TYPE_TARGET_TOLERANCE,
    ];

    /**
     * Get all supported rule types.
     *
     * @return array<int, string>
     */
    public static function ruleTypes(): array
    {
        return self::RULE_TYPES;
    }

    /**
     * Get human-readable labels for each rule type.
     *
     * @return array<string, string>
     */
    public static function ruleTypeLabels(): array
    {
        return [
            self::RULE_TYPE_RANGE => 'Range (Min – Max)',
            self::RULE_TYPE_GREATER_THAN => 'Greater Than (>)',
            self::RULE_TYPE_GREATER_THAN_OR_EQUAL => 'Greater Than or Equal (>=)',
            self::RULE_TYPE_LESS_THAN => 'Less Than (<)',
            self::RULE_TYPE_LESS_THAN_OR_EQUAL => 'Less Than or Equal (<=)',
            self::RULE_TYPE_EQUAL => 'Equal (=)',
            self::RULE_TYPE_TARGET_TOLERANCE => 'Target ± Tolerance',
        ];
    }

    public const SCOPE_TEST = 'test';
    public const SCOPE_SET = 'set';
    public const SCOPE_SPECIMEN = 'specimen';
    public const SCOPE_SUMMARY = 'summary';

    protected $table = 'mm_qc_test_parameters';

    protected $fillable = [
        'test_type_id',
        'code',
        'name',
        'data_type',
        'unit',
        'qc_unit_id',
        'scope',
        'is_required',
        'is_calculated',
        'is_summary',
        'formula',
        'calculation_scope',
        'formula_expression',
        'default_value',
        'options',
        'display_order',
        'rule_type',
        'min_value',
        'max_value',
        'target_value',
        'tolerance',
        'standard_reference',
        'is_active',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'is_required' => 'boolean',
        'is_calculated' => 'boolean',
        'is_summary' => 'boolean',
        'is_active' => 'boolean',
        'options' => 'array',
        'display_order' => 'integer',
        'min_value' => 'decimal:4',
        'max_value' => 'decimal:4',
        'target_value' => 'decimal:4',
        'tolerance' => 'decimal:4',
    ];

    public function testType()
    {
        return $this->belongsTo(QcTestType::class, 'test_type_id');
    }

    public function unitRef()
    {
        return $this->belongsTo(QcUnit::class, 'qc_unit_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function hasAcceptanceRule(): bool
    {
        return !empty($this->rule_type);
    }

    public function formattedRuleDescription(): ?string
    {
        if (!$this->hasAcceptanceRule()) {
            return null;
        }

        $unit = $this->unit ? " {$this->unit}" : '';

        return match ($this->rule_type) {
            self::RULE_TYPE_RANGE => ($this->min_value !== null && $this->max_value !== null)
                ? (float)$this->min_value . ' – ' . (float)$this->max_value . $unit
                : ($this->min_value !== null ? '≥ ' . (float)$this->min_value . $unit : '≤ ' . (float)$this->max_value . $unit),
            self::RULE_TYPE_GREATER_THAN => '> ' . (float)$this->min_value . $unit,
            self::RULE_TYPE_GREATER_THAN_OR_EQUAL => '≥ ' . (float)$this->min_value . $unit,
            self::RULE_TYPE_LESS_THAN => '< ' . (float)$this->max_value . $unit,
            self::RULE_TYPE_LESS_THAN_OR_EQUAL => '≤ ' . (float)$this->max_value . $unit,
            self::RULE_TYPE_EQUAL => '= ' . (float)$this->target_value . $unit,
            self::RULE_TYPE_TARGET_TOLERANCE => (float)$this->target_value . ' ± ' . (float)$this->tolerance . $unit,
            default => $this->rule_type,
        };
    }
}
