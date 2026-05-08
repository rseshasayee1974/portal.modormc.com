<?php

namespace App\Traits;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

trait ProtectsSystemItems
{
    public static function bootProtectsSystemItems()
    {
        static::updating(function ($model) {
            if ($model->is_system && $model->isDirty(['name', 'title', 'legal_name', 'site_name', 'template_name'])) {
                if (!self::isAuthorizedToModifySystemItems()) {
                    throw ValidationException::withMessages([
                        'name' => ['This is a system-generated item and cannot be renamed.'],
                    ]);
                }
            }
        });

        static::deleting(function ($model) {
            if ($model->is_system) {
                if (!self::isAuthorizedToModifySystemItems()) {
                    throw ValidationException::withMessages([
                        'id' => ['This is a system-generated item and cannot be deleted.'],
                    ]);
                }
            }
        });
    }

    protected static function isAuthorizedToModifySystemItems()
    {
        $user = Auth::user();
        if (!$user) return false;

        return $user->hasRole('Saas Owner') || $user->hasRole('Platform Admin') || $user->hasRole('Super Admin');
    }
}
