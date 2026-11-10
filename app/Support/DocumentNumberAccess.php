<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 15:20:30 Asia/Calcutta (UTC+05:30)
*/

namespace App\Support;

use App\Models\EntityUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class DocumentNumberAccess
{
    public const PERMISSIONS = [
        'PURCHASE_ORDER' => 'PURCHASE_ORDER.EDIT_REFERENCE_NUMBER',
        'INWARD' => 'INWARD.EDIT_REFERENCE_NUMBER',
        'SALES_ORDER' => 'SALES_ORDER.EDIT_REFERENCE_NUMBER',
        'CUSTOMER_PO' => 'CUSTOMER_PO.EDIT_REFERENCE_NUMBER',
        'QUOTATION' => 'QUOTATION.EDIT_REFERENCE_NUMBER',
        'BATCH' => 'BATCH.EDIT_BATCH_NO',
        'INVOICE' => 'INVOICE.EDIT_REFERENCE_NUMBER',
        'BILLING' => 'BILLING.EDIT_REFERENCE_NUMBER',
        'PAYMENT' => 'PAYMENT.EDIT_REFERENCE_NUMBER',
    ];

    public static function allows(?User $user, string $module): bool
    {
        if (!$user || !isset(self::PERMISSIONS[$module])) return false;

        $entityId = session('active_entity_id');
        $role = $entityId
            ? EntityUser::forContext($user->id, (int) $entityId, session('active_plant_id'))?->role
            : null;
        if ($entityId && !$role) return false;

        // Number overrides require an explicit grant, including administrator roles.
        $permissions = $role
            ? $role->permissions->merge($user->getDirectPermissions())
            : $user->getAllPermissions();

        return $permissions->contains(fn ($permission) => strtoupper($permission->name) === self::PERMISSIONS[$module]);
    }

    public static function forUser(?User $user): array
    {
        return array_map(fn ($permission) => self::allows($user, explode('.', $permission)[0]), self::PERMISSIONS);
    }

    public static function validate(Request $request, string $module, string $field, ?Model $record = null): void
    {
        // Reference-number grants control form editing only, not backend submissions.
        if (str_ends_with(self::PERMISSIONS[$module] ?? '', '.EDIT_REFERENCE_NUMBER')) return;

        if (self::allows($request->user(), $module)) return;

        $fields = $record && in_array($module, ['SALES_ORDER', 'INVOICE', 'BILLING'], true)
            ? [$field, 'prefix'] : [$field];
        foreach ($fields as $key) {
            if (!$request->exists($key)) continue;
            $incoming = (string) $request->input($key);
            $original = $record ? (string) $record->getAttribute($key) : '';
            if ($incoming !== $original) {
                throw ValidationException::withMessages([
                    $key => 'You need '.self::PERMISSIONS[$module].' permission to set or change this number.',
                ]);
            }
        }
    }
}
