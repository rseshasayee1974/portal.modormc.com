<?php

namespace App\Http\Requests;

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
        $workOrderId = (int) ($this->input('work_order_id') ?? $this->route('batch')?->work_order_id);

        return [
            'work_order_id' => ['required', 'integer', 'exists:mm_work_orders,id'],
            'batch_no' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('mm_batches', 'batch_no')
                    ->where(fn ($q) => $q->where('work_order_id', $workOrderId))
                    ->ignore($batchId),
            ],
            'batch_size' => ['required', 'numeric', 'gt:0'],
            'start_time' => ['nullable', 'date'],
            'end_time' => ['nullable', 'date', 'after_or_equal:start_time'],
            'empty_time' => ['nullable', 'date'],
            'load_time' => ['nullable', 'date'],
            'truck_id' => ['required', 'integer', 'exists:mm_machines,id'],
            'transport_id' => ['nullable', 'integer', 'exists:mm_patrons,id'],
            'driver_id' => ['nullable', 'integer', 'exists:mm_personnels,id'],
            'empty_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'loaded_weight_truck' => ['nullable', 'numeric', 'min:0'],
            'empty_weight_photo' => ['nullable', 'string'],
            'loaded_weight_photo' => ['nullable', 'string'],
            'net_weight' => ['nullable', 'numeric'],
            'uom_id' => ['nullable', 'integer', 'exists:mm_product_units,id'],
            'site_id' => ['nullable', 'integer', 'exists:mm_sites,id'],
            'status' => ['required', 'integer', 'in:1,2,3,4,5'],
            'materials' => ['nullable', 'array'],
            'materials.*.id' => ['nullable', 'integer', 'exists:mm_batch_materials,id'],
            'materials.*.product_id' => ['required', 'integer', 'exists:mm_products,id'],
            'materials.*.material_name' => ['nullable', 'string', 'max:255'],
            'materials.*.target_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.actual_qty' => ['required', 'numeric', 'gte:0'],
            'materials.*.deviation_quantity' => ['nullable', 'numeric'],
            'materials.*.uom_id' => ['required', 'integer', 'exists:mm_product_units,id'],
        ];
    }
}
