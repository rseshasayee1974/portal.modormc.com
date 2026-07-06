<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation()
    {
        $user = auth()->user();
        $isPrivileged = $user && ($user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Administrator'));

        $mergeData = [];

        if (!$this->filled('plant_id') && session()->has('active_plant_id')) {
            $mergeData['plant_id'] = session('active_plant_id');
        }

        if (!$isPrivileged) {
            $mergeData['type'] = 'unloading';
            $mergeData['plant_id'] = session('active_plant_id');
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    public function rules(): array
    {
        return [
            'plant_id' => 'required|exists:mm_plants,id',
            'patron_id' => 'nullable|array',
            'patron_id.*' => 'exists:mm_patrons,id',
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('mm_sites')->where(fn ($query) => 
                    $query->where('plant_id', $this->input('plant_id'))
                          ->where('type', $this->input('type'))
                          ->whereNull('deleted_at')
                ),
            ],
            'site_address_1' => 'nullable|string|max:500',
            'zipcode' => 'nullable|string|max:20',
            'code' => 'nullable|string|max:255',
            'type' => 'required|in:loading,unloading',
            'is_restricted' => 'boolean',
            'status' => 'nullable|in:Active,InActive',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
