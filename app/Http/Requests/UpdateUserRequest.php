<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use App\Models\Entity;

class UpdateUserRequest extends FormRequest
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
        /** @var \App\Models\User $user */
        $user = $this->user();

        return [
            'username' => 'required|string|max:191',
            'email' => [
                'required',
                'string',
                'email',
                'max:191',
                Rule::unique('mm_users', 'email')->ignore($this->route('user'))->whereNull('deleted_at'),
            ],
            'mobile' => 'nullable|string|max:20',
            'password' => 'nullable|string|min:8',
            'is_active' => 'nullable|boolean',
            'is_otp_enabled' => 'nullable|boolean',
            'role_id' => 'nullable|integer',
            'profile_photo_path' => 'sometimes|nullable|file|image|mimes:jpeg,png,jpg,gif,svg|max:4096',
            'entity_users' => 'nullable|array',
            'entity_users.*.entity_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    if ($user->hasRole('SAAS_OWNER')) {
                        if (!Entity::where('id', $value)->exists()) {
                            $fail('The selected entity is invalid.');
                        }
                    } else {
                        $validEntities = $user->entityUsers()->pluck('entity_id')->toArray();
                        if (!in_array((int)$value, $validEntities)) {
                            $fail('You do not have permission to manage this entity.');
                        }
                    }
                }
            ],
            'entity_users.*.plant_id' => 'nullable|integer|exists:mm_plants,id',
            'entity_users.*.role_id' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($user) {
                    $assignedRole = Role::find($value);
                    if (!$assignedRole) {
                        return $fail('The selected role is invalid.');
                    }
                    $spatieLevel = $user->roles->min('level');
                    $entityLevel = $user->entityUsers()->with('role')->get()->min('role.level');
                    $levels = array_filter([$spatieLevel, $entityLevel], fn($v) => !is_null($v));
                    $userLevel = empty($levels) ? 999 : min($levels);
                    if ($assignedRole->level > $userLevel) {
                        $fail('You cannot assign a role equal to or higher than your own.');
                    }
                }
            ],
        ];
    }
}
