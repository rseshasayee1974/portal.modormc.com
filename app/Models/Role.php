<?php

/**
 * Created by Reliese Model.
 */

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;

use Carbon\Carbon;
use Spatie\Permission\Models\Role as SpatieRole;
use Illuminate\Support\Facades\DB;

/**
 * Class Role
 * 
 * @property int $id
 * @property string $name
 * @property string $guard_name
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @package App\Models
 */
class Role extends SpatieRole
{
	use HasFactory;
	protected $table = 'mm_roles';
    protected $fillable = [
        'name',
        'code',
        'guard_name',
        'description',
        'level',
        'is_system',
        'status',
    ];

    // Fixed Roles
    const SAAS_OWNER          = 'SAAS_OWNER';
    const PLATFORM_ADMIN      = 'PLATFORM_ADMIN';
    const SUPER_ADMIN         = 'SUPER_ADMIN';
    const ADMINISTRATOR       = 'ADMINISTRATOR';
    const OPERATIONS_MANAGER  = 'OPERATIONS_MANAGER';
    const FINANCE_MANAGER     = 'FINANCE_MANAGER';
    const ACCOUNTANT          = 'ACCOUNTANT';
    const INVENTORY_MANAGER   = 'INVENTORY_MANAGER';
    const WAREHOUSE_OPERATOR  = 'WAREHOUSE_OPERATOR';
    const FLEET_MANAGER       = 'FLEET_MANAGER';
    const TRANSPORT_OPERATOR  = 'TRANSPORT_OPERATOR';
    const SALES_MANAGER       = 'SALES_MANAGER';
    const SALES_EXECUTIVE     = 'SALES_EXECUTIVE';
    const MARKETING_EXECUTIVE = 'MARKETING_EXECUTIVE';
    const HR_MANAGER          = 'HR_MANAGER';
    const CRM_EXECUTIVE       = 'CRM_EXECUTIVE';
    const REPORT_ANALYST      = 'REPORT_ANALYST';
    const OFFICE_ADMIN        = 'OFFICE_ADMIN';
    const OPERATOR            = 'OPERATOR';

    protected static function boot()
    {
        parent::boot();

        static::deleting(function ($role) {
            if ($role->is_system) {
                throw new \Exception("System roles cannot be deleted.");
            }
        });
    }

    /**
     * Store Role and Synchronize Permissions in a single Transaction.
     */
    public static function saveWithRelations(array $data)
    {
        return DB::transaction(function () use ($data) {
            $role = self::create([
                'name'         => $data['name'],
                'code'         => $data['code'] ?? null,
                'guard_name'   => $data['guard_name'] ?? 'web',
                'description'  => $data['description'] ?? null,
                'level'        => $data['level'] ?? 0,
                'is_system'    => $data['is_system'] ?? false,
                'status'       => $data['status'] ?? 'active',
            ]);

            if (isset($data['permissions'])) {
                $role->syncPermissions($data['permissions']);
            }

            return $role;
        });
    }

    /**
     * Update Role and Synchronize Permissions in a single Transaction.
     */
    public function updateWithRelations(array $data)
    {
        return DB::transaction(function () use ($data) {
            $this->update([
                'name'         => $data['name'],
                'code'         => $data['code'] ?? $this->code,
                'description'  => $data['description'] ?? $this->description,
                'level'        => $data['level'] ?? $this->level,
                'is_system'    => $data['is_system'] ?? $this->is_system,
                'status'       => $data['status'] ?? $this->status,
                'guard_name'   => $data['guard_name'] ?? $this->guard_name,
            ]);

            if (isset($data['permissions'])) {
                $this->syncPermissions($data['permissions']);
            }

            return tap($this)->refresh();
        });
    }
}
