<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\PlantScoping;

use App\Traits\TracksModelChanges;
class Expense extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_expenses';

    protected $fillable = [
        'plant_id',
        'ref_no',
        'expense_type_id',
        'made_by',
        'paid_through',
        'amount',
        'date',
        'vendor_id',
        'customer_id',
        'machine_id',
        'note',
        'status',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'decimal:2',
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->ref_no) {
                $model->ref_no = self::generateRefNo($model->plant_id, $model->paid_through, $model->date);
            }
        });
    }

    public static function generateRefNo($plantId, $ledgerId = null, $date = null): string
    {
        $prefix = Ledger::documentSeriesPrefix($plantId, $ledgerId, $date, 'EXP');
        $highest = 0;
        foreach (self::where('plant_id', $plantId)->where('ref_no', 'like', $prefix . '%')->pluck('ref_no') as $number) {
            if (preg_match('/^' . preg_quote($prefix, '/') . '(\d+)$/', $number, $match)) {
                $highest = max($highest, (int) $match[1]);
            }
        }
        return $prefix . ($highest + 1);
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class);
    }

    public function expenseType()
    {
        return $this->belongsTo(ExpenseType::class, 'expense_type_id');
    }

    public function madeBy()
    {
        return $this->belongsTo(User::class, 'made_by');
    }

    public function ledger()
    {
        return $this->belongsTo(Ledger::class, 'paid_through');
    }

    public function machine()
    {
        return $this->belongsTo(Machine::class, 'machine_id');
    }

    public function vendor()
    {
        return $this->belongsTo(Patron::class, 'vendor_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }
}
