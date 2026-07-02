<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePlantRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'entity_id' => ['required', 'exists:mm_entities,id'],
            'code' => ['required', 'string', 'max:255', \Illuminate\Validation\Rule::unique('mm_plants')->ignore($this->route('plant'))],
            'name' => ['required', 'string', 'max:255'],
            'mixer_capacity' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg', 'max:2048'],
            'logo_path' => ['nullable', 'string'],
            'email_address' => ['required', 'email:rfc,dns', 'max:255'],
            'mobile_number' => ['nullable', 'string', 'max:20'],
            'plant_type' => ['nullable', 'string', 'max:255'],
            'gstin' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'string', 'max:255'],
            'longitude' => ['nullable', 'string', 'max:255'],
            'is_main' => ['nullable', 'boolean'],
            'is_active' => ['nullable', 'boolean'],
            'shift_start_time' => ['nullable', 'date_format:H:i'],
            'shift_end_time' => ['nullable', 'date_format:H:i'],

            'scheduler_api_url' => ['nullable', 'url', 'max:255'],
            'scheduler_api_token' => ['nullable', 'string'],
            'scheduler_oauth_url' => ['nullable', 'url', 'max:255'],
            'scheduler_client_id' => ['nullable', 'string', 'max:255'],
            'scheduler_client_secret' => ['nullable', 'string'],
            'plc_ip' => ['nullable', 'string', 'max:45'],

            'einvoice_client_id' => ['nullable', 'string', 'max:255'],
            'einvoice_secret' => ['nullable', 'string', 'max:255'],
            'ewaybill_client_id' => ['nullable', 'string', 'max:255'],
            'ewaybill_secret' => ['nullable', 'string', 'max:255'],

            // Address Validation
            'address' => ['nullable', 'array'],
            'address.address_type_id' => ['required_with:mm_address.line_1', 'nullable', 'exists:mm_address_types,id'],
            'address.line_1' => ['nullable', 'string', 'max:255'],
            'address.line_2' => ['nullable', 'string', 'max:255'],
            'address.city' => ['required_with:mm_address.line_1', 'nullable', 'string', 'max:255'],
            'address.state_id' => ['required_with:mm_address.line_1', 'nullable', 'exists:mm_state_codes,id'],
            'address.zipcode' => ['required_with:mm_address.line_1', 'nullable', 'string', 'regex:/^[0-9]{6}$/'],
            'address.landmark' => ['nullable', 'string', 'max:255'],

            // Contact Validation
            'contact' => ['nullable', 'array'],
            'contact.contact_type_id' => ['required_with:mm_contact.name', 'nullable', 'exists:mm_contact_types,id'],
            'contact.name' => ['nullable', 'string', 'max:255'],
            'contact.email' => ['nullable', 'email', 'max:255'],
            'contact.mobile' => ['required_with:mm_contact.name', 'nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'contact.alt_mobile' => ['nullable', 'string', 'regex:/^[0-9]{10}$/'],
            'contact.landline' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'email_address' => 'Admin Email',
            'contact.email' => 'Contact Email',
            'contact.mobile' => 'Contact Mobile',
        ];
    }
}
