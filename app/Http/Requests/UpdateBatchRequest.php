<?php

namespace App\Http\Requests;

use App\Support\BatchNumberAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $batchId = $this->route('batch')?->id ?? $this->route('batch');
        $batch = \App\Models\Batch::withoutGlobalScope('plant_id')->find($batchId);
        $plantId = session('active_plant_id', $batch?->plant_id);
        $salesOrderId = (int) ($this->input('sales_order_id') ?? $batch?->sales_order_id);
        
        $settings = \App\Models\CustomSetting::getForModule($plantId, 'batching');

        return [
            'sales_order_id' => ['nullable', 'integer', 'exists:mm_sales_orders,id'],
            'batch_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mm_batches', 'batch_no')
                    ->where(fn ($q) => $q->where('plant_id', $plantId)->whereNull('deleted_at'))
                    ->ignore($batchId),
            ],
            'batch_size' => ['required', 'numeric', 'gt:0'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'operator_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'shift' => ['nullable', 'string', 'max:50'],
            'empty_time' => ['nullable', 'date'],
            'load_time' => ['required', 'date'],
            'truck_id' => ['nullable', 'integer', 'exists:mm_machines,id'],
            'transport_id' => ['nullable', 'integer', 'exists:mm_patrons,id'],
            'driver_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'sales_executive_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'empty_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'loaded_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'empty_weight_photo' => ['nullable', 'string'],
            'loaded_weight_photo' => ['nullable', 'string'],
            'net_weight' => ['nullable', 'numeric'],
            'uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
            'site_id' => ['nullable', 'integer', 'exists:mm_sites,id'],
            'status' => ['required', 'integer', 'in:1,2,3,4,5'],
            'concrete_pump' => ['nullable'],
            'materials' => ['nullable', 'array'],
            'materials.*.id' => ['nullable', 'integer', 'exists:mm_batch_materials,id'],
            'materials.*.product_id' => ['nullable', 'integer', 'exists:mm_products,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.target_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.actual_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.deviation_quantity' => ['nullable', 'numeric'],
            'materials.*.uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $batchId = $this->route('batch')?->id ?? $this->route('batch');
            $salesOrderId = $this->input('sales_order_id') ?? $this->route('batch')?->sales_order_id;
            $newBatchSize = (float) $this->input('batch_size', 0);
            
            if ($salesOrderId && $newBatchSize > 0) {
                $workOrder = \App\Models\SalesOrder::find($salesOrderId);
                $batch = \App\Models\Batch::find($batchId);
                
                if ($workOrder && $batch) {
                    $totalQty = (float) $workOrder->total_qty;
                    $producedQty = (float) $workOrder->produced_qty;
                    $oldBatchSize = (float) $batch->batch_size;
                    
                    // The produced_qty already includes $oldBatchSize
                    // So remaining without this batch is: total_qty - (produced_qty - oldBatchSize)
                   $remainingForThisBatch = $totalQty - ($producedQty - $oldBatchSize);

                    $MAX_ERROR_Margin = 0.0001;

                    if (($newBatchSize - $remainingForThisBatch) > $MAX_ERROR_Margin) {
                        $remaining = max(0, $remainingForThisBatch);

                        $validator->errors()->add(
                            'batch_size',
                            sprintf(
                                'Batch size (%.3f m³) exceeds remaining work order quantity (%.3f m³).',
                                $newBatchSize,
                                $remaining
                            )
                        );
                    }
                }
            }

            // Manual numbers require admin access or batch create permission.
            $user = $this->user() ?? auth()->user();
            $canEditBatchNo = BatchNumberAccess::allows($user);

            if (!$canEditBatchNo && $this->filled('batch_no')) {
                $currentBatch = \App\Models\Batch::withoutGlobalScope('plant_id')->find($batchId);
                if ($currentBatch && (int)$this->input('batch_no') !== (int)$currentBatch->batch_no) {
                    $validator->errors()->add('batch_no', 'Only administrators or users with Batches create permission may modify the batch number.');
                }
            }
        });
    }

    public function messages(): array
    {
        return [
            'batch_no.unique' => 'Batch number #:input already exists for this plant. Duplicate batch numbers are restricted.',
            'batch_no.min' => 'Batch number must be at least 1.',
            'batch_no.integer' => 'Batch number must be a valid integer.',
        ];
    }

    protected function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        \Illuminate\Support\Facades\Log::error('UpdateBatchRequest Validation Failed:', $validator->errors()->toArray());
        parent::failedValidation($validator);
    }
}
