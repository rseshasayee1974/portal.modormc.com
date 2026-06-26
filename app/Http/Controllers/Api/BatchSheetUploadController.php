<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\BatchSheetTemplate;
use App\Models\BatchSheetUpload;
use App\Models\Dispatch;
use App\Models\Machine;
use App\Models\Patron;
use App\Models\Personnel;
use App\Models\Product;
use App\Models\WorkOrder;
use App\Jobs\ProcessBatchSheetJob;
use App\Models\SalesOrder;
use App\Services\BatchSheet\UploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class BatchSheetUploadController extends Controller
{
    protected UploadService $uploadService;

    public function __construct(UploadService $uploadService)
    {
        $this->uploadService = $uploadService;
    }

    /**
     * Upload a new batch sheet document and dispatch the processing job.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file',
        ]);

        $plantId = session('active_plant_id');
        if (!$plantId) {
            return response()->json(['error' => 'No active plant selected in session.'], 400);
        }

        try {
            $file = $request->file('file');
            
            // Check validation/hash
            $hash = $this->uploadService->calculateHash($file);
            $duplicate = $this->uploadService->checkDuplicate($hash, $plantId);

            if ($duplicate) {
                return response()->json([
                    'status' => 'duplicate',
                    'duplicate' => [
                        'id' => $duplicate->id,
                        'original_filename' => $duplicate->original_filename,
                        'status' => $duplicate->status,
                        'created_at' => $duplicate->created_at ? $duplicate->created_at->toIso8601String() : null,
                    ]
                ]);
            }

            // Store file and create upload record
            $upload = $this->uploadService->store($file, $plantId, auth()->id() ?? 1);
            $upload->transitionTo(BatchSheetUpload::STATUS_UPLOADED, "File successfully uploaded to local storage disk.");

            // Dispatch async job
            ProcessBatchSheetJob::dispatch($upload->id);

            return response()->json([
                'status' => 'success',
                'upload_id' => $upload->id,
                'message' => 'Batch sheet uploaded and queued for intelligent extraction.'
            ]);

        } catch (\InvalidArgumentException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error("BatchSheetUploadController@upload error: " . $e->getMessage());
            return response()->json(['error' => 'An error occurred during file upload: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get the processing progress status and logs.
     */
    public function status($id)
    {
        $upload = BatchSheetUpload::find($id);
        if (!$upload) {
            return response()->json(['error' => 'Upload record not found.'], 404);
        }

        // Map status to progress %
        $progressMap = [
            BatchSheetUpload::STATUS_UPLOADED => 10,
            BatchSheetUpload::STATUS_VALIDATING => 25,
            BatchSheetUpload::STATUS_OCR_RUNNING => 45,
            BatchSheetUpload::STATUS_PROCESSING => 60,
            BatchSheetUpload::STATUS_EXTRACTING => 80,
            BatchSheetUpload::STATUS_REVIEW => 95,
            BatchSheetUpload::STATUS_COMPLETED => 100,
            BatchSheetUpload::STATUS_FAILED => 100,
        ];

        $progress = $progressMap[$upload->status] ?? 0;

        return response()->json([
            'status' => $upload->status,
            'progress' => $progress,
            'processing_log' => $upload->processing_log ?? [],
            'error_message' => $upload->error_message,
        ]);
    }

    /**
     * Fetch extracted data and dropdown choices for verification.
     */
    public function verify($id)
    {
        $upload = BatchSheetUpload::find($id);
        if (!$upload) {
            return response()->json(['error' => 'Upload record not found.'], 404);
        }

        $plantId = $upload->plant_id;

        // Fetch dropdown options for UI mapping
        $customers = Patron::where('plant_id', $plantId)->get(['id', 'legal_name']);
        $trucks = Machine::where('plant_id', $plantId)->get(['id', 'registration']);
        $drivers = Personnel::where('plant_id', $plantId)
            ->whereHas('designation', function ($q) {
                $q->where('name', 'Driver');
            })
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->map(function ($p) {
                return ['id' => $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name)];
            });
            
        $operators = Personnel::where('plant_id', $plantId)
            ->select('id', 'first_name', 'last_name')
            ->get()
            ->map(function ($p) {
                return ['id' => $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name)];
            });
        $products = Product::where('plant_id', $plantId)->get(['id', 'title']);
        
        $workOrders = SalesOrder::query()->where('plant_id', $plantId)
            ->get(['id', 'order_no', 'produced_qty', 'total_qty']);

        return response()->json([
            'upload' => [
                'id' => $upload->id,
                'original_filename' => $upload->original_filename,
                'mime_type' => $upload->mime_type,
                'file_url' => $upload->file_url,
                'parsed_json' => $upload->parsed_json,
                'normalized_json' => $upload->normalized_json,
                'confidence_score' => $upload->confidence_score,
                'field_scores' => $upload->field_scores,
                'template_id' => $upload->template_id,
            ],
            'dropdowns' => [
                'customers' => $customers,
                'trucks' => $trucks,
                'drivers' => $drivers,
                'operators' => $operators,
                'products' => $products,
                'work_orders' => $workOrders,
            ]
        ]);
    }

    /**
     * Save the finalized batch sheet data to the mm_batches and mm_dispatches tables.
     */
    public function saveToDatabase(Request $request, $id)
    {
        $upload = BatchSheetUpload::find($id);
        if (!$upload) {
            return response()->json(['error' => 'Upload record not found.'], 404);
        }

        $request->validate([
            'header' => 'required|array',
            'materials' => 'required|array',
        ]);

        $header = $request->input('header');
        $materials = $request->input('materials');

        try {
            DB::transaction(function () use ($upload, $header, $materials) {
                // 1. Create Batch record
                $batch = Batch::create([
                    'plant_id' => $upload->plant_id,
                    'work_order_id' => $header['work_order_id'] ?? null,
                    'batch_no' => $header['batch_no'] ?? 'BS-' . time(),
                    'batch_size' => (float)($header['batch_size'] ?? 1.0),
                    'start_time' => $header['start_time'] ? date('H:i:s', strtotime($header['start_time'])) : null,
                    'end_time' => $header['end_time'] ? date('H:i:s', strtotime($header['end_time'])) : null,
                    'status' => Batch::STATUS_COMPLETED,
                    'operator_id' => $header['operator_id'] ?? null,
                    'sync_status' => 1,
                    'batch_original_sheet_path' => $upload->stored_path,
                    'batch_sheet_path' => $upload->stored_path, // Excel path fallback to original upload
                ]);

                // 2. Create Batch Materials
                foreach ($materials as $m) {
                    if (empty($m['product_id'])) continue;

                    $target = (float)($m['target_qty'] ?? 0);
                    $actual = (float)($m['actual_qty'] ?? 0);
                    
                    $batch->materials()->create([
                        'product_id' => $m['product_id'],
                        'material_name' => $m['material_name'] ?? '',
                        'target_qty' => $target,
                        'actual_qty' => $actual,
                        'deviation_quantity' => $actual - $target,
                    ]);
                }

                // 3. Create Dispatch Trip Record
                $currentDate = now();
                $startYear = $currentDate->month >= 4 ? $currentDate->year : $currentDate->year - 1;
                $endYear = $startYear + 1;
                $fyString = substr($startYear, -2) . substr($endYear, -2);
                $prefix = "DP-{$fyString}-";
                
                $maxNumber = Dispatch::where('plant_id', $upload->plant_id)
                    ->where('prefix', $prefix)
                    ->max(DB::raw('CAST(dispatch_no AS UNSIGNED)'));
                
                $newNumber = ($maxNumber ?: 0) + 1;

                Dispatch::create([
                    'plant_id' => $upload->plant_id,
                    'batch_id' => $batch->id,
                    'work_order_id' => $header['work_order_id'] ?? null,
                    'customer_id' => $header['customer_id'] ?? null,
                    'truck_id' => $header['truck_id'] ?? null,
                    'driver_id' => $header['driver_id'] ?? null,
                    'delivered_qty' => $batch->batch_size,
                    'dispatch_status' => 'Draft',
                    'prefix' => $prefix,
                    'dispatch_no' => (string)$newNumber,
                ]);

                // 4. Update Upload record status
                $upload->update([
                    'batch_id' => $batch->id,
                    'customer_id' => $header['customer_id'] ?? null,
                    'reviewed_by' => auth()->id() ?? 1,
                    'reviewed_at' => now(),
                ]);
                $upload->transitionTo(BatchSheetUpload::STATUS_COMPLETED, "Data verified and saved to system batches/trips successfully.");
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Batch sheet data saved and trip generated successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error("BatchSheetUploadController@saveToDatabase failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to save batch sheet: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Save the mapping corrections as a layout template.
     */
    public function saveTemplate(Request $request, $id)
    {
        $upload = BatchSheetUpload::find($id);
        if (!$upload) {
            return response()->json(['error' => 'Upload record not found.'], 404);
        }

        $request->validate([
            'template_name' => 'required|string|max:100',
            'corrections' => 'required|array',
        ]);

        try {
            $template = BatchSheetTemplate::create([
                'plant_id' => $upload->plant_id,
                'customer_id' => $upload->customer_id,
                'name' => $request->input('template_name'),
                'field_mapping' => $request->input('corrections'),
                'is_active' => true,
            ]);

            $upload->update([
                'template_id' => $template->id
            ]);

            return response()->json([
                'status' => 'success',
                'template_id' => $template->id,
                'message' => 'Layout mapping template saved successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error("BatchSheetUploadController@saveTemplate failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to save template: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete an upload record and its stored file.
     */
    public function destroy($id)
    {
        $upload = BatchSheetUpload::find($id);
        if (!$upload) {
            return response()->json(['error' => 'Upload record not found.'], 404);
        }

        try {
            if ($upload->stored_path) {
                Storage::disk(config('batchsheet.storage_disk', 'public'))->delete($upload->stored_path);
            }
            $upload->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Upload record and file deleted successfully.'
            ]);

        } catch (\Exception $e) {
            Log::error("BatchSheetUploadController@destroy failed: " . $e->getMessage());
            return response()->json(['error' => 'Failed to delete record: ' . $e->getMessage()], 500);
        }
    }
}