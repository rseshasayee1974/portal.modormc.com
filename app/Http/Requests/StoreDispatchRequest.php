<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreDispatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'work_order_id' => ['required', 'integer', 'exists:mm_work_orders,id'],
            'batch_id' => ['required', 'integer', 'exists:mm_batches,id'],
            'truck_id' => ['required', 'integer', 'exists:mm_machines,id'],
            'driver_id' => ['nullable', 'integer', 'exists:mm_patrons,id'],
            'dispatch_time' => ['nullable', 'date'],
            'delivered_qty' => ['required', 'numeric', 'gt:0'],
        ];
    }
}
