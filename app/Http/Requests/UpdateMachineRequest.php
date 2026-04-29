<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMachineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; 
    }

    public function rules(): array
    {
        $machineId = $this->route('machine')->id;

        return [
            'registration' => [
                'required', 
                'string', 
                'max:20', 
                Rule::unique('mm_machines')->ignore($machineId)->where(fn($q) => $q->whereNull('deleted_at'))
            ],
            'vehicle_model' => 'nullable|string|max:100',
            'make_year' => 'nullable|integer|min:1900|max:'.date('Y'),
            'engine_no' => 'nullable|string|max:100',
            'chassis_no' => 'nullable|string|max:100',
            'vehicle_type' => 'nullable|string|max:50',
            'capacity' => 'nullable|integer',
            'owner_id' => 'nullable|exists:mm_patrons,id',
            
            'documents' => 'nullable|array|max:10',
            'documents.*.id' => 'nullable|integer',
            'documents.*.type' => [
                'required', 
                'string',
                'distinct',
            ],
            'documents.*.issue_date' => 'nullable|date',
            'documents.*.expiry_date' => 'nullable|date',
            'documents.*.amount' => 'nullable|numeric',
            
            'loans' => 'nullable|array',
            'loans.*.id' => 'nullable|integer',
            'loans.*.loan_amount' => 'sometimes|numeric',
            'loans.*.emi_amount' => 'sometimes|numeric',
            'loans.*.tenure_months' => 'sometimes|integer',
            'loans.*.start_date' => 'sometimes|date',
            'loans.*.end_date' => 'nullable|date',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('documents')) {
                $machine = $this->route('machine');
                $existingTypes = $machine->documents()
                    ->whereNotIn('id', collect($this->documents)->pluck('id')->filter())
                    ->pluck('type')
                    ->toArray();

                foreach ($this->documents as $index => $doc) {
                    if (in_array($doc['type'], $existingTypes)) {
                        $validator->errors()->add("documents.{$index}.type", "The machine already has a {$doc['type']} record.");
                    }
                }
            }
        });
    }
}
