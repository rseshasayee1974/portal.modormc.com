<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateEntityRequest extends FormRequest
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
            'entity_type' => 'required|exists:mm_entity_types,id',
            'parent_id' => 'nullable|exists:mm_entities,id',
            'legal_name' => 'required|string|max:255',
            'alias' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'url' => 'nullable|url|max:255',
            'api_key' => 'nullable|string|max:255',
            'logo_file' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg|max:2048',
            'description' => 'nullable|string',
            'time_zone' => 'nullable|string|max:100',
            'is_active' => 'boolean',
            'is_suspended' => 'boolean',
            
            // Nested Addresses Validation
            'addresses' => 'nullable|array',
            'addresses.*.id' => 'nullable|integer',
            'addresses.*.address_type' => 'required|exists:mm_address_types,id|distinct',
            'addresses.*.line_1' => 'required|string|max:255',
            'addresses.*.line_2' => 'nullable|string|max:255',
            'addresses.*.city' => 'required|string|max:255',
            'addresses.*.zipcode' => 'nullable|string|max:50',
            'addresses.*.landmark' => 'nullable|string|max:255',
            'addresses.*.country_id' => 'nullable|exists:mm_countries,id',
            'addresses.*.state_id' => 'nullable|exists:mm_state_codes,id',
            'addresses.*.is_primary' => 'boolean',

            // Nested Contacts Validation
            'contacts' => 'nullable|array',
            'contacts.*.id' => 'nullable|integer',
            'contacts.*.contact_type' => 'required|exists:mm_contact_types,id|distinct',
            'contacts.*.contact_person' => 'required|string|max:255',
            'contacts.*.email' => 'nullable|email|max:255',
            'contacts.*.mobile' => 'nullable|string|max:50',
            'contacts.*.alt_mobile' => 'nullable|string|max:50',
            'contacts.*.alt_email' => 'nullable|email|max:255',
            'contacts.*.landline' => 'nullable|string|max:50',
            'contacts.*.is_primary' => 'boolean',

            // Nested Bank Accounts Validation
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable|integer',
            'bank_accounts.*.account_type' => 'required|exists:mm_bank_account_types,id|distinct',
            'bank_accounts.*.account_number' => 'required|string|max:50',
            'bank_accounts.*.bank_name' => 'required|string|max:255',
            'bank_accounts.*.bank_branch' => 'nullable|string|max:255',
            'bank_accounts.*.ifsc_code' => 'nullable|string|max:50',
            'bank_accounts.*.bank_address' => 'nullable|string|max:255',
            'bank_accounts.*.is_primary' => 'boolean',

            // Nested Taxes Validation
            'taxes' => 'nullable|array',
            'taxes.*.id' => 'nullable|integer',
            'taxes.*.tax_type' => 'required|string|max:100', // Or tax type id if there's a table
            'taxes.*.tax_number' => 'required|string|max:100',
            'taxes.*.country_id' => 'nullable|exists:mm_countries,id',
            'taxes.*.state_id' => 'nullable|exists:mm_state_codes,id',
            'taxes.*.is_primary' => 'boolean',
        ];
    }
}
