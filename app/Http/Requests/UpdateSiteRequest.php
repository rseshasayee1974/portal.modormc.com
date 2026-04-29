<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSiteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $siteId = $this->route('site')->id ?? $this->site->id; // Handles both model binding and manual ID if needed
        
        return [
            'plant_id' => 'required|exists:mm_plants,id',
            'name' => [
                'required',
                'string',
                'max:255',
                \Illuminate\Validation\Rule::unique('mm_sites')->where(fn ($query) => 
                    $query->where('plant_id', $this->plant_id)
                          ->where('type', $this->type)
                          ->whereNull('deleted_at')
                )->ignore($siteId),
            ],
            'site_address_1' => 'nullable|string|max:500',
            'zipcode' => 'nullable|string|max:20',
            'code' => 'nullable|string|max:255',
            'type' => 'required|in:loading,unloading',
            'is_restricted' => 'boolean',
            'status' => 'required|in:Active,InActive',
            'latitude' => 'nullable|numeric',
            'longitude' => 'nullable|numeric',
        ];
    }
}
