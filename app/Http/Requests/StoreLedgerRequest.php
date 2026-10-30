<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreLedgerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $plantId = session('active_plant_id');

        return [
            'account_type_id' => ['required', 'integer', 'exists:mm_account_types,id'],
            'title'           => ['required', 'string', 'max:255'],
            'code'            => [
                'nullable', 
                'string', 
                'max:50',
                Rule::unique('mm_ledgers')->where(fn ($q) => 
                    $q->where('plant_id', $plantId)->whereNull('deleted_at')
                )
            ],
            'is_pnl'          => ['required', 'boolean'],
            'description'     => ['nullable', 'string'],
            'notes'           => ['nullable', 'string'],
            'slug'            => ['nullable', 'string', 'max:255'],
        ];
    }
}
