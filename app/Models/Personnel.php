<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\AuditFields;
use App\Traits\PlantScoping;

class Personnel extends Model
{
    use HasFactory, SoftDeletes, AuditFields, PlantScoping;

    protected $table = 'mm_personnels';

    // ──────────────────────────────────────────────────────────────
    // Dropdown / Lookup Scopes
    // ──────────────────────────────────────────────────────────────

    /**
     * Scope: active personnel for a plant (entity_id + plant_id + not deleted).
     * SoftDeletes handles deleted_at IS NULL automatically.
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int|array  $plantId
     * @param  int|null   $entityId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForPlant($query, $plantId, int $entityId = null)
    {
        $query = is_array($plantId)
            ? $query->whereIn('plant_id', $plantId)
            : $query->where('plant_id', $plantId);

        if ($entityId !== null) {
            $query->where('entity_id', $entityId);
        }

        return $query;
    }

    /**
     * Scope: exclude a specific person by id (edit/update scenarios).
     *
     * @param  \Illuminate\Database\Eloquent\Builder $query
     * @param  int  $id
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeExcludeId($query, int $id)
    {
        return $query->where('mm_personnels.id', '!=', $id);
    }

    protected $fillable = [
        'entity_id',
        'plant_id',
        'user_id',
        'contact_id',
        'department_id',
        'designation_id',
        'reporting_manager_id',
        'employee_code',
        'first_name',
        'last_name',
        'email',
        'mobile',
        'date_of_birth',
        'joining_date',
        'exit_date',
        'gender',
        'employment_type',
        'status',
        'pan',
        'aadhaar',
        'uan',
        'esi_number',
        'bank_account_no',
        'bank_ifsc',
        'bank_name',
        'photo',
        'meta',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'meta' => 'json',
        'date_of_birth' => 'date',
        'joining_date' => 'date',
        'exit_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function driver()
    {
        return $this->hasOne(Driver::class, 'personnel_id');
    }

    public function entity()
    {
        return $this->belongsTo(Entity::class, 'entity_id');
    }

    public function plant()
    {
        return $this->belongsTo(Plant::class, 'plant_id');
    }

    public function contacts()
    {
        return $this->hasMany(PersonnelContact::class, 'employee_id');
    }

    public function patrons()
    {
        return $this->belongsToMany(Patron::class, 'mm_personnel_patron_rels', 'employee_id', 'patron_id');
    }

    public function department()
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function designation()
    {
        return $this->belongsTo(Designation::class, 'designation_id');
    }

    public function reportingManager()
    {
        return $this->belongsTo(Personnel::class, 'reporting_manager_id');
    }

    public function subordinates()
    {
        return $this->hasMany(Personnel::class, 'reporting_manager_id');
    }

    public function shifts()
    {
        return $this->belongsToMany(Shift::class, 'mm_employee_shifts', 'personnel_id', 'shift_id')
            ->withPivot('effective_from', 'effective_to')
            ->withTimestamps();
    }

    public function attendances()
    {
        return $this->hasMany(Attendance::class, 'personnel_id');
    }

    public function leaveBalances()
    {
        return $this->hasMany(EmployeeLeaveBalance::class, 'personnel_id');
    }

    public function leaveApplications()
    {
        return $this->hasMany(LeaveApplication::class, 'personnel_id');
    }

    public function salaryStructures()
    {
        return $this->hasMany(EmployeeSalaryStructure::class, 'personnel_id');
    }

    public function payslips()
    {
        return $this->hasMany(Payslip::class, 'personnel_id');
    }
}
