<?php

namespace App\Models;
use App\Traits\TracksModelChanges;
use App\Traits\PlantScoping;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class BatchSheetUpload extends Model
{
        use HasFactory, SoftDeletes, PlantScoping, TracksModelChanges;

    protected $table = 'mm_batch_sheet_uploads';

    protected $fillable = [
        'plant_id',
        'user_id',
        'customer_id',
        'batch_id',
        'original_filename',
        'stored_filename',
        'stored_path',
        'mime_type',
        'file_size',
        'sha256_hash',
        'file_extension',
        'status',
        'ocr_required',
        'parser_used',
        'raw_text',
        'parsed_json',
        'normalized_json',
        'confidence_score',
        'field_scores',
        'template_id',
        'processing_log',
        'processing_started_at',
        'processing_completed_at',
        'error_message',
        'reviewed_by',
        'reviewed_at',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'ocr_required' => 'boolean',
        'parsed_json' => 'array',
        'normalized_json' => 'array',
        'confidence_score' => 'decimal:2',
        'field_scores' => 'array',
        'processing_log' => 'array',
        'processing_started_at' => 'datetime',
        'processing_completed_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    protected $appends = ['file_url'];

    public const STATUS_UPLOADED = 'uploaded';
    public const STATUS_VALIDATING = 'validating';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_OCR_RUNNING = 'ocr_running';
    public const STATUS_EXTRACTING = 'extracting';
    public const STATUS_REVIEW = 'review';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    public function getFileUrlAttribute(): ?string
    {
        if (!$this->stored_path) {
            return null;
        }

        $disk = config('batchsheet.storage_disk', 'public');
        if ($disk === 'public') {
            return '/storage/' . ltrim($this->stored_path, '/');
        }

        return Storage::disk($disk)->url($this->stored_path);
    }

    /**
     * Appends a log entry to the processing_log json array
     */
    public function appendLog(string $message, string $level = 'info'): void
    {
        $logs = $this->processing_log ?? [];
        $logs[] = [
            'time' => now()->toIso8601String(),
            'level' => $level,
            'message' => $message,
        ];
        $this->processing_log = $logs;
        $this->saveQuietly();
    }

    /**
     * Helper to change status and write a processing log entry
     */
    public function transitionTo(string $status, ?string $logMessage = null): void
    {
        $this->status = $status;
        if ($status === self::STATUS_PROCESSING && !$this->processing_started_at) {
            $this->processing_started_at = now();
        }
        if (in_array($status, [self::STATUS_COMPLETED, self::STATUS_FAILED, self::STATUS_REVIEW])) {
            $this->processing_completed_at = now();
        }
        $this->save();

        if ($logMessage) {
            $this->appendLog($logMessage, $status === self::STATUS_FAILED ? 'error' : 'info');
        }
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function customer()
    {
        return $this->belongsTo(Patron::class, 'customer_id');
    }

    public function batch()
    {
        return $this->belongsTo(Batch::class, 'batch_id');
    }

    public function template()
    {
        return $this->belongsTo(BatchSheetTemplate::class, 'template_id');
    }

    public function reviewedByUser()
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
