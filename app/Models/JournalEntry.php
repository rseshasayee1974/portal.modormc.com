<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\TracksModelChanges;
class JournalEntry extends Model
{
        use HasFactory, SoftDeletes, TracksModelChanges;

    protected $table = 'mm_journal_entries';

    protected $fillable = [
        'entity_id',
        'plant_id',
        'voucher_type',
        'voucher_number',
        'ref_module',
        'ref_name',
        'ref_id',
        'voucher_date',
        'posting_date',
        'narration',
        'narration_label',
        'total_debit',
        'total_credit',
        'is_status',
        'reversal_of_id',
        'created_by',
        'updated_by',
        'deleted_by',
        'is_deleted',
    ];

    protected $casts = [
        'voucher_date' => 'date:Y-m-d',
        'posting_date' => 'date:Y-m-d',
        'total_debit'  => 'decimal:4',
        'total_credit' => 'decimal:4',
        'is_deleted'   => 'boolean',
    ];

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($entry) {
            if (!$entry->isForceDeleting()) {
                // Mass update lines to ensure is_deleted, deleted_by, and deleted_at are set
                $entry->lines()->update([
                    'is_deleted' => 1,
                    'deleted_by' => auth()->id(),
                    'deleted_at' => now(),
                ]);

                $entry->updateQuietly([
                    'is_deleted' => 1,
                    'deleted_by' => auth()->id()
                ]);
            }
        });
    }

    public function lines()
    {
        return $this->hasMany(JournalEntryLine::class, 'journal_entry_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function isBalanced()
    {
        return $this->total_debit === $this->total_credit;
    }

    public static function resolveNarrationLabel(?string $refModule, ?string $voucherType = null): string
    {
        $module = strtolower($refModule ?? '');
        $vType = strtoupper($voucherType ?? '');

        return match ($module) {
            'invoice', 'sales'           => 'Sales',
            'purchase', 'purchase_order' => 'Purchase',
            'bill'                       => 'Purchase Bill',
            'payment'                    => $vType === 'RECEIPT' ? 'Receipt' : 'Payment',
            'expense'                    => 'Expense',
            'dispatch'                   => 'Dispatch',
            'stockin'                    => 'StockIn',
            'stockout'                   => 'StockOut',
            'bank_reconciliation', 'brs' => 'Bank Reconciliation',
            default                      => match ($vType) {
                'SALES'         => 'Sales',
                'PURCHASE'      => 'Purchase',
                'PAYMENT'       => 'Payment',
                'RECEIPT'       => 'Receipt',
                'JOURNAL', 'JV' => 'Manual JV',
                default         => !empty($refModule) ? ucfirst($refModule) : 'Manual JV',
            },
        };
    }

    /**
     * Generate the next auto-incremented voucher number for a given voucher type.
     * Re-uses soft-deleted sequence numbers (gap-filling).
     */
    public static function generateNextVoucherNumber(string $voucherType, ?int $plantId = null): string
    {
        $vType = VoucherType::where('short_code', $voucherType)->first();
        $prefix = $vType && !empty($vType->prefix) ? $vType->prefix : (strtoupper($voucherType) . '-');

        // Query active (non-soft-deleted) entries for this voucher type
        $query = static::where('voucher_type', $voucherType)
            ->whereNull('deleted_at')
            ->where('is_deleted', 0);

        if ($plantId) {
            $query->where('plant_id', $plantId);
        }

        $existingNumbers = $query->pluck('voucher_number')->toArray();

        $usedInts = [];
        foreach ($existingNumbers as $numStr) {
            if (!empty($prefix) && str_starts_with($numStr, $prefix)) {
                $numPart = substr($numStr, strlen($prefix));
                if (is_numeric($numPart)) {
                    $usedInts[(int) $numPart] = true;
                }
            } else {
                $digits = preg_replace('/\D/', '', $numStr);
                if (!empty($digits)) {
                    $usedInts[(int) $digits] = true;
                }
            }
        }

        // Find smallest unused positive integer (gap-filling, allows re-use of deleted references)
        $nextSeq = 1;
        while (isset($usedInts[$nextSeq])) {
            $nextSeq++;
        }

        return $prefix . str_pad((string) $nextSeq, 5, '0', STR_PAD_LEFT);
    }
}
