<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreRoleRequest extends FormRequest
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
            'name'        => 'required|string|max:255|unique:mm_roles,name',
            'code'        => 'required|string|max:100|unique:mm_roles,code',
            'description' => 'nullable|string',
            'level'       => 'nullable|integer|min:0',
            'is_system'   => 'boolean',
            'status'      => 'nullable|string|in:active,inactive',
            'guard_name'  => 'nullable|string|max:255',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:mm_permissions,name'
        ];
    }
}
