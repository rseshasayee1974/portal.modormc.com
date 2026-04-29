<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        return [
            'registration' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('mm_machines')->where(fn($q) => $q->whereNull('deleted_at'))
            ],
            'vehicle_model' => 'nullable|string|max:100',
            'make_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer',
            'owner_id' => 'nullable|exists:mm_patrons,id',
            
            'documents' => 'nullable|array|max:10',
            'documents.*.type' => [
                'required', 
                'string',
                'distinct',
            ],
            'documents.*.issue_date' => 'nullable|date',
            'documents.*.expiry_date' => 'nullable|date',
            'documents.*.amount' => 'nullable|numeric',
            
            'loans' => 'nullable|array',
            'loans.*.loan_amount' => 'required|numeric',
            'loans.*.emi_amount' => 'required|numeric',
            'loans.*.tenure_months' => 'required|integer',
            'loans.*.start_date' => 'required|date',
            'loans.*.end_date' => 'nullable|date',
        ];
    }
}
