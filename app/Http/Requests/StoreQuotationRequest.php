<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreQuotationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Controller handles authorization via middleware/traits
    }

    protected function prepareForValidation()
    {
        if ($this->filled('reference') && is_numeric($this->reference)) {
            $now = now();
            $startYear = $now->month >= 4 ? $now->year : $now->year - 1;
            $fyString = substr($startYear, -2) . substr($startYear + 1, -2);
            
            $this->merge([
                'reference' => sprintf('QT-%s-%04d', $fyString, (int)$this->reference)
            ]);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reference' => 'nullable|string|max:100|unique:mm_quotations,reference',
            'patron_id' => 'required|exists:mm_patrons,id',
            'site_id' => 'nullable|required_without:new_site_name|exists:mm_sites,id',
            'new_site_name' => 'nullable|string|max:255',
            'quote_date' => 'required|date',
            'validity_date' => 'nullable|date',
            'amount_untaxed' => 'nullable|numeric',
            'tax_amount' => 'nullable|numeric',
            'amount_tax' => 'nullable|numeric',
            'amount_total' => 'nullable|numeric',
            'adjustment' => 'nullable|numeric',
            'status' => 'integer',
            'items' => 'required|array|min:1',
            'items.*.mix_design_id' => 'required|exists:mm_mix_designs,id',
            'items.*.uom_id' => 'nullable|integer',
            'items.*.tax_id' => 'nullable|integer',
            'items.*.quantity' => 'required|numeric|min:0.01',
            'items.*.rate' => 'required|numeric|min:0',
            'items.*.untaxed_amount' => 'nullable|numeric',
            'items.*.tax_amount' => 'nullable|numeric',
            'items.*.amount_total' => 'nullable|numeric',
        ];
    }
}
