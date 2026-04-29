<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePatronRequest extends FormRequest
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
        $mergeData = [];
        if (!$this->has('contact_type_id') || is_null($this->contact_type_id)) {
            $mergeData['contact_type_id'] = 1;
        }
        if (!$this->has('address_type_id') || is_null($this->address_type_id)) {
            $mergeData['address_type_id'] = 1;
        }
        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }

        if ($this->has('bank_accounts') && is_array($this->bank_accounts)) {
            $bankAccounts = array_filter($this->bank_accounts, function ($account) {
                return !empty($account['account_number']) || 
                       !empty($account['account_holder_name']) || 
                       !empty($account['bank_name']) || 
                       !empty($account['ifsc_code']);
            });
            $bankAccounts = array_values($bankAccounts);

            foreach ($bankAccounts as &$account) {
                if (!isset($account['bank_account_type']) || is_null($account['bank_account_type'])) {
                    $account['bank_account_type'] = 1;
                }
            }
            $this->merge(['bank_accounts' => $bankAccounts]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $patron = $this->route('patron');

        return [
            // Patron
            'patron_type' => 'required|array',
            'patron_type.*' => 'string|max:50',
            'code' => [
                'nullable', 
                'string', 
                'max:100', 
                \Illuminate\Validation\Rule::unique('mm_patrons')->ignore($patron->id ?? null)->where(fn($q) => $q->where('plant_id', session('active_plant_id')))
            ],
            'legal_name' => [
                'required', 
                'string', 
                'max:200', 
                \Illuminate\Validation\Rule::unique('mm_patrons')->ignore($patron->id ?? null)->where(fn($q) => $q->where('plant_id', session('active_plant_id')))
            ],
            'ledger_id' => 'nullable|exists:mm_ledgers,id',
            'operational_status' => 'required|string|max:100',
            'pan_no' => 'nullable|string|max:20',
            'gstin' => 'nullable|string|max:20',
            'status' => 'required|boolean',
            'displayed' => 'required|boolean',

            // Primary Contact
            'contact_name' => 'nullable|string|max:255',
            'contact_email' => 'nullable|email|max:255',
            'contact_mobile' => 'nullable|string|regex:/^[0-9]{10}$/',
            'contact_alt_mobile' => 'nullable|string|regex:/^[0-9]{10}$/',
            'contact_type_id' => 'nullable|integer',
            'contact_status' => 'nullable|boolean',
            'contact_is_primary' => 'nullable|boolean',

            // Primary Address
            'address_line_1' => 'nullable|string|max:200',
            'address_line_2' => 'nullable|string|max:200',
            'address_city' => 'nullable|string|max:150',
            'address_state_id' => 'nullable|exists:mm_state_codes,id',
            'address_state_code' => 'nullable|string|max:50',
            'address_zipcode' => 'nullable|string|regex:/^[0-9]{6}$/',
            'address_type_id' => 'nullable|integer',
            'address_status' => 'nullable|boolean',
            'address_is_primary' => 'nullable|boolean',

            // Multiple Bank Details
            'bank_accounts' => 'nullable|array',
            'bank_accounts.*.id' => 'nullable',
            'bank_accounts.*.bank_account_type' => 'nullable|integer',
            'bank_accounts.*.account_holder_name' => 'required|string|max:255',
            'bank_accounts.*.account_number' => 'required|string|max:255',
            'bank_accounts.*.bank_name' => 'required|string|max:255',
            'bank_accounts.*.branch_name' => 'nullable|string|max:255',
            'bank_accounts.*.ifsc_code' => 'required|string|max:255',
            'bank_accounts.*.is_primary' => 'nullable|boolean',
            'bank_accounts.*.status' => 'nullable|boolean',
        ];
    }
}
