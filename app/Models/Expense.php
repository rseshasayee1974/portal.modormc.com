<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\Auth;
use App\Traits\AuditFields;

class Expense extends Model
{
    use HasFactory, SoftDeletes, AuditFields;

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
                $model->ref_no = self::generateRefNo($model->plant_id);
            }
        });
    }

    public static function generateRefNo($plantId): string
    {
        $last = self::where('plant_id', $plantId)->orderBy('id', 'desc')->first();
        $next = $last ? $last->id + 1 : 1;
        return 'EXP-' . date('Ym') . '-' . str_pad($next, 4, '0', STR_PAD_LEFT);
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
