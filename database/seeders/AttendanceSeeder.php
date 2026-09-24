<?php

namespace Database\Seeders;

use App\Models\Plant;
use App\Models\Personnel;
use App\Models\Attendance;
use App\Models\PayrollPeriod;
use App\Models\SalaryComponent;
use App\Models\EmployeeSalaryStructure;
use App\Models\Shift;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $plants = Plant::all();
        if ($plants->isEmpty()) {
            $this->command->warn('No plants found. Skipping attendance seeding.');
            return;
        }

        $from_date = Carbon::create(2026, 9, 1);
        $to_date = Carbon::create(2026, 9, 30);

        foreach ($plants as $plant) {
            $this->command->info("Seeding attendance & payroll period for September 2026 in plant: [{$plant->id}] {$plant->name}");

            // 1. Ensure PayrollPeriod exists for September 2026
            $period = PayrollPeriod::updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'name' => 'September 2026',
                ],
                [
                    'from_date' => $from_date->toDateString(),
                    'to_date' => $to_date->toDateString(),
                    'status' => 'draft',
                    'created_by' => Auth::id() ?? 1,
                ]
            );

            // 2. Fetch or seed Personnel for the plant
            $personnelList = Personnel::where('plant_id', $plant->id)
                ->where('status', 'active')
                ->get();

            if ($personnelList->isEmpty()) {
                Personnel::factory()
                    ->count(5)
                    ->create([
                        'plant_id' => $plant->id,
                        'entity_id' => $plant->entity_id,
                        'status' => 'active',
                        'created_by' => Auth::id() ?? 1,
                    ]);

                $personnelList = Personnel::where('plant_id', $plant->id)
                    ->where('status', 'active')
                    ->get();
            }

            // Fetch default shift
            $shift = Shift::where('shift_name', 'General Shift')->first() ?? Shift::first();

            // 3. Ensure salary components and salary structures exist
            $components = SalaryComponent::where('plant_id', $plant->id)->get();
            if ($components->isEmpty()) {
                $seeder = new HrmsMasterSeeder();
                $seeder->run();
                $components = SalaryComponent::where('plant_id', $plant->id)->get();
            }

            foreach ($personnelList as $personnel) {
                // Attach salary structure if not present
                if ($personnel->salaryStructures()->count() === 0) {
                    foreach ($components as $comp) {
                        $amount = 0;
                        if (str_contains(strtolower($comp->name), 'basic')) {
                            $amount = 25000;
                        } elseif (str_contains(strtolower($comp->name), 'house rent') || str_contains(strtolower($comp->name), 'hra')) {
                            $amount = 10000;
                        } elseif (str_contains(strtolower($comp->name), 'conveyance')) {
                            $amount = 1600;
                        } elseif (str_contains(strtolower($comp->name), 'special')) {
                            $amount = 5000;
                        } elseif (str_contains(strtolower($comp->name), 'provident') || str_contains(strtolower($comp->name), 'pf')) {
                            $amount = 12; // 12%
                        } elseif (str_contains(strtolower($comp->name), 'esi')) {
                            $amount = 0.75; // 0.75%
                        } elseif (str_contains(strtolower($comp->name), 'professional')) {
                            $amount = 200;
                        }

                        if ($amount > 0) {
                            EmployeeSalaryStructure::create([
                                'personnel_id' => $personnel->id,
                                'salary_component_id' => $comp->id,
                                'amount' => $amount,
                                'effective_from' => '2026-01-01',
                                'created_by' => Auth::id() ?? 1,
                            ]);
                        }
                    }
                }

                // 4. Seed daily attendance for September 2026
                $currentDate = $from_date->copy();
                while ($currentDate->lte($to_date)) {
                    $dateStr = $currentDate->toDateString();

                    $existing = Attendance::where('plant_id', $plant->id)
                        ->where('personnel_id', $personnel->id)
                        ->where('attendance_date', $dateStr)
                        ->first();

                    if (!$existing) {
                        if ($currentDate->isSunday()) {
                            $status = 'weekoff';
                            $check_in = null;
                            $check_out = null;
                            $worked_hours = 0;
                        } else {
                            // Seed varied attendance: 85% present, 5% half_day, 5% on_duty, 5% absent
                            $rand = rand(1, 100);
                            if ($rand <= 85) {
                                $status = 'present';
                                $check_in = $dateStr . ' 09:00:00';
                                $check_out = $dateStr . ' 18:00:00';
                                $worked_hours = 9.0;
                            } elseif ($rand <= 90) {
                                $status = 'half_day';
                                $check_in = $dateStr . ' 09:00:00';
                                $check_out = $dateStr . ' 13:30:00';
                                $worked_hours = 4.5;
                            } elseif ($rand <= 95) {
                                $status = 'on_duty';
                                $check_in = $dateStr . ' 09:00:00';
                                $check_out = $dateStr . ' 18:00:00';
                                $worked_hours = 9.0;
                            } else {
                                $status = 'absent';
                                $check_in = null;
                                $check_out = null;
                                $worked_hours = 0;
                            }
                        }

                        Attendance::create([
                            'plant_id' => $plant->id,
                            'personnel_id' => $personnel->id,
                            'shift_id' => $shift?->id,
                            'attendance_date' => $dateStr,
                            'check_in' => $check_in,
                            'check_out' => $check_out,
                            'worked_hours' => $worked_hours,
                            'overtime_hours' => 0,
                            'late_hours' => 0,
                            'status' => $status,
                            'is_late' => false,
                            'is_early_departure' => false,
                            'source' => 'manual',
                            'created_by' => Auth::id() ?? 1,
                        ]);
                    }

                    $currentDate->addDay();
                }
            }
        }

        $this->command->info('Attendance seeding for September 2026 completed successfully.');
    }
}
