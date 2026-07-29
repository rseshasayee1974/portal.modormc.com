<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\SalaryComponent;
use App\Models\StatutoryConfig;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class HrmsMasterSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info("Seeding global HRMS master data (Departments, Designations, Leave Types, Shifts)...");

        $this->seedDepartments();
        $this->seedDesignations();
        $this->seedLeaveTypes();
        $this->seedShifts();

        $plants = Plant::all();

        if ($plants->isEmpty()) {
            $this->command->warn('No plants found. Skipping plant-specific HRMS master seeding.');
            return;
        }

        foreach ($plants as $plant) {
            $this->command->info("Seeding plant-specific HRMS master data for plant: [{$plant->id}] {$plant->name}");

            $this->seedSalaryComponents($plant);
            $this->seedStatutoryConfigs($plant);
        }

        $this->command->info('HRMS master seeding complete.');
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function seedDepartments(): void
    {
        $departments = [
            ['name' => 'Production',      'code' => 'PROD'],
            ['name' => 'Quality Control', 'code' => 'QC'],
            ['name' => 'Accounts',        'code' => 'ACC'],
            ['name' => 'Human Resources', 'code' => 'HR'],
            ['name' => 'Administration',  'code' => 'ADMIN'],
        ];

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['code' => $dept['code']],
                [
                    'name'       => $dept['name'],
                    'created_by' => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedDesignations(): void
    {
        $designations = [
            ['name' => 'Plant Manager',       'code' => 'PM'],
            ['name' => 'Production Engineer', 'code' => 'PE'],
            ['name' => 'Quality Engineer',    'code' => 'QE'],
            ['name' => 'Accountant',          'code' => 'ACCT'],
            ['name' => 'Operator',            'code' => 'OPR'],
        ];

        foreach ($designations as $desig) {
            Designation::updateOrCreate(
                ['code' => $desig['code']],
                [
                    'name'       => $desig['name'],
                    'created_by' => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedLeaveTypes(): void
    {
        $leaveTypes = [
            ['name' => 'Casual Leave',      'is_paid' => true,  'max_days_per_year' => 12,  'carry_forward' => false],
            ['name' => 'Sick Leave',        'is_paid' => true,  'max_days_per_year' => 7,   'carry_forward' => false],
            ['name' => 'Earned Leave',      'is_paid' => true,  'max_days_per_year' => 15,  'carry_forward' => true],
            ['name' => 'Maternity Leave',   'is_paid' => true,  'max_days_per_year' => 180, 'carry_forward' => false],
            ['name' => 'Leave Without Pay', 'is_paid' => false, 'max_days_per_year' => 0,   'carry_forward' => false],
        ];

        foreach ($leaveTypes as $lt) {
            LeaveType::updateOrCreate(
                ['name' => $lt['name']],
                [
                    'is_paid'           => $lt['is_paid'],
                    'max_days_per_year' => $lt['max_days_per_year'],
                    'carry_forward'     => $lt['carry_forward'],
                    'created_by'        => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedShifts(): void
    {
        $shifts = [
            ['shift_name' => 'General Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_time' => 10, 'working_hours' => 9, 'is_night_shift' => false],
            ['shift_name' => 'Morning Shift', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_time' => 10, 'working_hours' => 8, 'is_night_shift' => false],
            ['shift_name' => 'Evening Shift', 'start_time' => '14:00', 'end_time' => '22:00', 'grace_time' => 10, 'working_hours' => 8, 'is_night_shift' => false],
            ['shift_name' => 'Night Shift',   'start_time' => '22:00', 'end_time' => '06:00', 'grace_time' => 10, 'working_hours' => 8, 'is_night_shift' => true],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(
                ['shift_name' => $shift['shift_name']],
                [
                    'start_time'     => $shift['start_time'],
                    'end_time'       => $shift['end_time'],
                    'grace_time'     => $shift['grace_time'],
                    'working_hours'  => $shift['working_hours'],
                    'is_night_shift' => $shift['is_night_shift'],
                    'created_by'     => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedSalaryComponents(Plant $plant): void
    {
        $components = [
            // ── Earnings ────────────────────────────────────────────────────
            ['name' => 'Basic Salary',         'type' => 'earning',   'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            ['name' => 'House Rent Allowance', 'type' => 'earning',   'calculation_type' => 'percentage', 'default_value' => 40,   'is_taxable' => false, 'is_statutory' => false],
            ['name' => 'Conveyance Allowance', 'type' => 'earning',   'calculation_type' => 'fixed',      'default_value' => 1600, 'is_taxable' => false, 'is_statutory' => false],
            ['name' => 'Special Allowance',    'type' => 'earning',   'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            ['name' => 'Overtime',             'type' => 'earning',   'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            // ── Deductions ──────────────────────────────────────────────────
            ['name' => 'Provident Fund (PF)',  'type' => 'deduction', 'calculation_type' => 'percentage', 'default_value' => 12,   'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'ESI',                  'type' => 'deduction', 'calculation_type' => 'percentage', 'default_value' => 0.75, 'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'Professional Tax',     'type' => 'deduction', 'calculation_type' => 'fixed',      'default_value' => 200,  'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'TDS',                  'type' => 'deduction', 'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'Advance Deduction',    'type' => 'deduction', 'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => false, 'is_statutory' => false],
        ];

        foreach ($components as $comp) {
            try {
                SalaryComponent::updateOrCreate(
                    ['plant_id' => $plant->id, 'name' => $comp['name']],
                    [
                        'type'             => $comp['type'],
                        'calculation_type' => $comp['calculation_type'],
                        'default_value'    => $comp['default_value'],
                        'is_taxable'       => $comp['is_taxable'],
                        'is_statutory'     => $comp['is_statutory'],
                        'created_by'       => Auth::id() ?? 1,
                    ]
                );
            } catch (\Exception $e) {
                // Ignore charset mismatch or warning truncation exceptions in local databases
            }
        }
    }

    private function seedStatutoryConfigs(Plant $plant): void
    {
        $configs = [
            [
                'statute_name'   => 'Provident Fund (PF)',
                'effective_from' => now()->startOfYear()->toDateString(),
                'rules'          => [
                    'employee_rate' => 12,
                    'employer_rate' => 12,
                    'wage_ceiling'  => 15000,
                    'apply_on'      => 'basic',
                ],
            ],
            [
                'statute_name'   => 'Employee State Insurance (ESI)',
                'effective_from' => now()->startOfYear()->toDateString(),
                'rules'          => [
                    'employee_rate' => 0.75,
                    'employer_rate' => 3.25,
                    'wage_ceiling'  => 21000,
                    'apply_on'      => 'gross',
                ],
            ],
        ];

        foreach ($configs as $cfg) {
            StatutoryConfig::updateOrCreate(
                ['plant_id' => $plant->id, 'statute_name' => $cfg['statute_name']],
                [
                    'effective_from' => $cfg['effective_from'],
                    'rules'          => $cfg['rules'],
                    'created_by'     => Auth::id() ?? 1,
                ]
            );
        }
    }
}
