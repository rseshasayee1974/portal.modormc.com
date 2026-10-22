<?php

/*
Author: Cipher
Created: 2026-10-21 15:50:31 Asia/Calcutta (UTC+05:30)
*/

namespace Tests\Feature;

use App\Models\Attendance;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MobileAttendanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['database.default' => 'attendance_test', 'database.connections.attendance_test' => [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ], 'cache.default' => 'array', 'session.driver' => 'array']);
        foreach ([
            'mm_users' => ['username', 'email', 'password', 'is_active', 'is_otp_enabled', 'lockout_until', 'default_plant_id', 'default_entity_id', 'mobile_access_revision'],
            'mm_entities' => ['name', 'is_suspended', 'time_zone'],
            'mm_plants' => ['name', 'entity_id', 'is_active', 'gstin'],
            'mm_roles' => ['name', 'code', 'guard_name'],
            'mm_permissions' => ['name', 'guard_name', 'module'],
            'mm_entity_users' => ['user_id', 'entity_id', 'plant_id', 'role_id'],
            'mm_personnels' => ['plant_id', 'entity_id', 'user_id', 'first_name', 'employee_code', 'status', 'joining_date', 'exit_date', 'shift_start_time', 'shift_end_time'],
            'mm_payroll_periods' => ['plant_id', 'from_date', 'to_date', 'status'],
            'mm_payslips' => ['plant_id', 'personnel_id', 'payroll_period_id', 'status'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
        foreach (['mm_role_has_permissions' => ['role_id', 'permission_id'], 'mm_model_has_roles' => ['role_id', 'model_id', 'model_type'], 'mm_model_has_permissions' => ['permission_id', 'model_id', 'model_type']] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                foreach ($columns as $column) {
                    $table->text($column);
                }
            });
        }
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();
        });
        (require database_path('migrations/2026_05_27_130300_create_mm_shifts_table.php'))->up();
        (require database_path('migrations/2026_05_27_130400_create_mm_attendances_table.php'))->up();
        (require database_path('migrations/2026_09_29_144938_add_notes_to_mm_attendances_table.php'))->up();
        DB::table('mm_entities')->insert(['id' => 1, 'time_zone' => 'Asia/Kolkata', 'is_suspended' => 0]);
        DB::table('mm_plants')->insert([
            ['id' => 1, 'entity_id' => 1, 'name' => 'Plant', 'is_active' => 1],
            ['id' => 2, 'entity_id' => 1, 'name' => 'Other plant', 'is_active' => 1],
        ]);
        DB::table('mm_roles')->insert(['id' => 1, 'name' => 'Employee', 'code' => 'EMPLOYEE', 'guard_name' => 'web']);
        foreach ([1, 2] as $id) {
            DB::table('mm_users')->insert(['id' => $id, 'username' => 'Employee '.$id, 'email' => 'employee'.$id.'@example.test', 'is_active' => 1, 'is_otp_enabled' => 1]);
            DB::table('mm_entity_users')->insert(['user_id' => $id, 'entity_id' => 1, 'plant_id' => 1, 'role_id' => 1]);
            DB::table('mm_personnels')->insert(['id' => $id, 'user_id' => $id, 'plant_id' => 1, 'status' => 'active', 'shift_start_time' => '09:00:00', 'shift_end_time' => '17:00:00']);
        }
        $token = User::findOrFail(1)->createToken('sales-mobile:test', ['sales-mobile'])->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token, 'X-Plant-Id' => '1']);
        $this->at('2026-10-21 09:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(string $time): void
    {
        Carbon::setTestNow(Carbon::parse($time, 'Asia/Kolkata'));
    }

    public function test_check_in_out_and_retries_preserve_server_timestamps_and_calculations(): void
    {
        $this->getJson('/api/mobile/v1/attendances/current')->assertOk()->assertJsonPath('data.attendance', null);
        $response = $this->postJson('/api/mobile/v1/attendances/check-in', ['notes' => 'Starting work'])->assertCreated()
            ->assertJsonPath('data.check_in', '2026-10-21 09:00:00')->assertJsonPath('data.worked_hours', '0.00');
        $id = $response->json('data.id');
        $this->at('2026-10-21 10:00:00');
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertOk()->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.check_in', '2026-10-21 09:00:00');
        $this->at('2026-10-21 18:30:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.worked_hours', '9.50')->assertJsonPath('data.overtime_hours', '1.50')->assertJsonPath('data.is_open', false);
        $this->at('2026-10-21 19:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()->assertJsonPath('data.check_out', '2026-10-21 18:30:00');
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertOk()->assertJsonPath('data.id', $id);
        $this->assertSame(1, Attendance::count());
        $this->getJson('/api/mobile/v1/attendances?from_date=2026-10-21&to_date=2026-10-21')->assertOk()->assertJsonPath('data.total', 1);
        $this->getJson('/api/mobile/v1/attendances?to_date=2026-10-20')->assertOk()->assertJsonPath('data.total', 0);
    }

    public function test_shift_assignment_grace_and_existing_shift_settings_are_used(): void
    {
        $shift = DB::table('mm_shifts')->insertGetId(['shift_name' => 'Day', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'grace_time' => '00:10:00', 'working_hours' => 8]);
        DB::table('mm_employee_shifts')->insert(['personnel_id' => 1, 'shift_id' => $shift, 'effective_from' => '2026-10-01', 'effective_to' => '2026-10-31']);
        $this->at('2026-10-21 09:10:00');
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->assertJsonPath('data.is_late', false)->json('data.id');
        DB::table('mm_shifts')->where('id', $shift)->update(['start_time' => '10:00:00', 'end_time' => '19:00:00', 'working_hours' => 9]);
        $this->at('2026-10-21 16:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.is_early_departure', true)->assertJsonPath('data.worked_hours', '6.83')->assertJsonPath('data.scheduled_hours', '9.00');
    }

    public function test_overnight_and_after_midnight_arrival_use_shift_start_date(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['shift_start_time' => '22:00:00', 'shift_end_time' => '06:00:00']);
        $this->at('2026-10-22 00:30:00');
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()
            ->assertJsonPath('data.attendance_date', '2026-10-21')->assertJsonPath('data.late_hours', '2.50')->json('data.id');
        $this->at('2026-10-22 05:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.worked_hours', '4.50')->assertJsonPath('data.is_early_departure', true);
    }

    public function test_forged_fields_foreign_records_and_invalid_requests_are_rejected(): void
    {
        foreach (['personnel_id' => 2, 'plant_id' => 2, 'check_in' => '2020-01-01', 'worked_hours' => 99, 'status' => 'holiday'] as $field => $value) {
            $this->postJson('/api/mobile/v1/attendances/check-in', [$field => $value])->assertUnprocessable()->assertJsonValidationErrors($field);
        }
        $foreignId = DB::table('mm_attendances')->insertGetId(['plant_id' => 1, 'personnel_id' => 2, 'attendance_date' => '2026-10-21', 'status' => 'present', 'check_in' => '2026-10-21 09:00:00', 'source' => 'mobile']);
        $this->postJson('/api/mobile/v1/attendances/'.$foreignId.'/check-out')->assertNotFound();
        $this->getJson('/api/mobile/v1/attendances/'.$foreignId)->assertNotFound();
        $this->getJson('/api/mobile/v1/attendances')->assertOk()->assertJsonPath('data.total', 0);
        $this->postJson('/api/mobile/v1/attendances/99999/check-out')->assertNotFound();
        $this->getJson('/api/mobile/v1/attendances?from_date=2026-10-22&to_date=2026-10-21')->assertUnprocessable();
        $this->getJson('/api/mobile/v1/attendances?per_page=101')->assertUnprocessable();
        $this->withHeaders(['X-Plant-Id' => '2'])->postJson('/api/mobile/v1/attendances/check-in')->assertForbidden();
    }

    public function test_mobile_access_allows_attendance_without_hr_status_or_employment_date_restrictions(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['status' => 'inactive', 'joining_date' => '2026-10-22', 'exit_date' => '2026-10-20']);
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->assertJsonPath('data.personnel_id', 1)->json('data.id');
        $this->at('2026-10-21 17:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk();
        $this->assertSame('inactive', DB::table('mm_personnels')->where('id', 1)->value('status'));
        $this->assertSame('2026-10-22', DB::table('mm_personnels')->where('id', 1)->value('joining_date'));
    }

    public function test_user_without_personnel_can_read_empty_history_then_check_in_and_out(): void
    {
        DB::table('mm_personnels')->where('id', 1)->delete();
        $this->getJson('/api/mobile/v1/attendances/current')->assertOk()->assertJsonPath('data.attendance', null);
        $this->getJson('/api/mobile/v1/attendances')->assertOk()->assertJsonPath('data.total', 0);
        $this->getJson('/api/mobile/v1/attendances/999')->assertNotFound();
        $this->postJson('/api/mobile/v1/attendances/999/check-out')->assertNotFound();
        $this->assertSame(0, DB::table('mm_personnels')->where('user_id', 1)->count());
        $response = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->assertJsonPath('data.is_late', false);
        $personnelId = $response->json('data.personnel_id');
        $attendanceId = $response->json('data.id');
        $profile = DB::table('mm_personnels')->where('id', $personnelId)->first();
        $this->assertEquals(1, $profile->user_id);
        $this->assertEquals(1, $profile->plant_id);
        $this->assertSame('Employee 1', $profile->first_name);
        $this->assertNotEmpty($profile->employee_code);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertOk()->assertJsonPath('data.personnel_id', $personnelId);
        $this->assertSame(1, DB::table('mm_personnels')->where('user_id', 1)->count());
        $this->at('2026-10-21 10:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$attendanceId.'/check-out')->assertOk()->assertJsonPath('data.worked_hours', '1.00');
    }

    public function test_deleted_personnel_is_not_restored_and_legacy_duplicates_use_stable_mapping(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['deleted_at' => now()]);
        DB::table('mm_personnels')->insert(['id' => 3, 'user_id' => 1, 'plant_id' => 1, 'status' => 'active']);
        DB::table('mm_personnels')->insert(['id' => 4, 'user_id' => 1, 'plant_id' => 1, 'status' => 'active']);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->assertJsonPath('data.personnel_id', 3);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertOk()->assertJsonPath('data.personnel_id', 3);
        $this->assertNotNull(DB::table('mm_personnels')->where('id', 1)->value('deleted_at'));
        $this->assertSame(3, DB::table('mm_personnels')->where('user_id', 1)->count());
    }

    public function test_provisioning_is_plant_scoped_and_rolls_back_when_payroll_blocks_check_in(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['plant_id' => 2]);
        $this->getJson('/api/mobile/v1/attendances/current')->assertOk()->assertJsonPath('data.attendance', null);
        DB::table('mm_payroll_periods')->insert(['plant_id' => 1, 'from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'status' => 'locked']);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertConflict();
        $this->assertSame(0, DB::table('mm_personnels')->where('user_id', 1)->where('plant_id', 1)->count());
        $this->assertEquals(2, DB::table('mm_personnels')->where('id', 1)->value('plant_id'));
    }

    public function test_granted_user_without_role_or_personnel_can_punch_and_deleted_profiles_are_preserved(): void
    {
        DB::table('mm_entity_users')->where('user_id', 1)->update(['role_id' => null]);
        DB::table('mm_personnels')->where('id', 1)->update(['deleted_at' => now()]);
        $this->app['auth']->forgetGuards();
        $response = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated();
        $personnelId = $response->json('data.personnel_id');
        $this->assertNotEquals(1, $personnelId);
        $this->assertNotNull(DB::table('mm_personnels')->where('id', 1)->value('deleted_at'));
        $this->assertNull(DB::table('mm_entity_users')->where('user_id', 1)->value('role_id'));
        $this->at('2026-10-21 10:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$response->json('data.id').'/check-out')->assertOk()->assertJsonPath('data.worked_hours', '1.00');
    }

    public function test_revoked_mobile_user_without_personnel_cannot_provision_a_profile(): void
    {
        DB::table('mm_personnels')->where('id', 1)->delete();
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertForbidden();
        $this->assertSame(0, DB::table('mm_personnels')->where('user_id', 1)->count());
    }

    public function test_manual_and_soft_deleted_records_are_not_overwritten(): void
    {
        $id = DB::table('mm_attendances')->insertGetId(['plant_id' => 1, 'personnel_id' => 1, 'attendance_date' => '2026-10-21', 'status' => 'absent', 'source' => 'manual']);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertConflict();
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertConflict();
        DB::table('mm_attendances')->where('id', $id)->update(['deleted_at' => now()]);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertConflict();
        $this->assertSame('absent', DB::table('mm_attendances')->where('id', $id)->value('status'));
    }

    public function test_stale_open_session_and_backward_clock_require_correction(): void
    {
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->at('2026-10-21 08:59:59');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertUnprocessable();
        $this->at('2026-10-22 09:00:01');
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertOk()->assertJsonPath('data.id', $id);
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertUnprocessable();
        $this->assertNull(Attendance::findOrFail($id)->check_out);
    }

    public function test_no_shift_and_zero_duration_are_supported(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['shift_start_time' => null, 'shift_end_time' => null]);
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.worked_hours', '0.00')->assertJsonPath('data.is_late', false)->assertJsonPath('data.overtime_hours', '0.00');
    }

    public function test_locked_payroll_blocks_new_and_open_attendance_but_not_reading(): void
    {
        $periodId = DB::table('mm_payroll_periods')->insertGetId(['plant_id' => 1, 'from_date' => '2026-10-01', 'to_date' => '2026-10-31', 'status' => 'locked']);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertConflict();
        DB::table('mm_payroll_periods')->where('id', $periodId)->update(['status' => 'draft']);
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        DB::table('mm_payslips')->insert(['plant_id' => 1, 'personnel_id' => 1, 'payroll_period_id' => $periodId, 'status' => 'draft']);
        $this->at('2026-10-21 17:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertConflict();
        $this->getJson('/api/mobile/v1/attendances/'.$id)->assertOk();
    }

    public function test_existing_database_schema_is_sufficient(): void
    {
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated();
        $this->assertFalse(Schema::hasColumn('mm_attendances', 'scheduled_start'));
        $this->assertSame(1, DB::table('mm_attendances')->count());
    }

    public function test_late_arrival_beyond_grace_counts_full_lateness_and_rounds_only_final_hours(): void
    {
        $shift = DB::table('mm_shifts')->insertGetId(['shift_name' => 'Day', 'start_time' => '09:00:00', 'end_time' => '17:00:00', 'grace_time' => '00:10:00', 'working_hours' => 8]);
        DB::table('mm_employee_shifts')->insert(['personnel_id' => 1, 'shift_id' => $shift, 'effective_from' => '2026-10-01']);
        $this->at('2026-10-21 09:10:01');
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()
            ->assertJsonPath('data.is_late', true)->assertJsonPath('data.late_hours', '0.17')->json('data.id');
        $this->at('2026-10-21 17:10:19');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.worked_hours', '8.01')->assertJsonPath('data.overtime_hours', '0.01');
    }

    public function test_night_shift_early_checkout_before_midnight_matches_web_calculation(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['shift_start_time' => '22:00:00', 'shift_end_time' => '06:00:00']);
        $this->at('2026-10-21 22:00:00');
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->at('2026-10-21 23:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.worked_hours', '1.00')->assertJsonPath('data.is_early_departure', true);
        session(['active_plant_id' => 1]);
        $controller = app(\App\Http\Controllers\AttendanceController::class);
        $method = new \ReflectionMethod($controller, 'calculateAttendanceMetrics');
        $metrics = $method->invoke($controller, 1, '2026-10-21', '2026-10-21 22:00:00', '2026-10-21 23:00:00', 'present');
        $this->assertTrue($metrics['is_early_departure']);
        $this->assertSame(1.0, $metrics['worked_hours']);
        $normalize = new \ReflectionMethod($controller, 'normalizePunchTime');
        $this->assertSame('2026-10-21 09:00:00', $normalize->invoke($controller, '2026-10-21T03:30:00Z', 'present'));
        $this->assertSame('2026-10-21 09:00:00', $normalize->invoke($controller, '2026-10-21 09:00:00', 'present'));
        $this->assertNull($normalize->invoke($controller, '2026-10-21T03:30:00Z', 'absent'));
    }

    public function test_chennai_time_is_stored_and_returned_even_when_entity_and_server_use_other_timezones(): void
    {
        DB::table('mm_entities')->where('id', 1)->update(['time_zone' => 'America/New_York']);
        config(['app.timezone' => 'UTC']);
        Carbon::setTestNow(Carbon::parse('2026-10-20 20:00:00', 'UTC'));
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()
            ->assertJsonPath('data.attendance_date', '2026-10-21')->assertJsonPath('data.timezone', 'Asia/Kolkata')
            ->assertJsonPath('data.check_in', '2026-10-21 01:30:00')->json('data.id');
        $this->assertSame('2026-10-21 01:30:00', DB::table('mm_attendances')->where('id', $id)->value('check_in'));
        $this->getJson('/api/mobile/v1/attendances/current')->assertOk()->assertJsonPath('data.server_time', '2026-10-21 01:30:00');
        Carbon::setTestNow(Carbon::parse('2026-10-20 21:00:00', 'UTC'));
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.check_out', '2026-10-21 02:30:00')->assertJsonPath('data.worked_hours', '1.00');
        $this->assertSame('2026-10-21 02:30:00', DB::table('mm_attendances')->where('id', $id)->value('check_out'));
        $this->assertSame('2026-10-21 01:30:00', Attendance::findOrFail($id)->toArray()['check_in']);
    }

    public function test_partial_shift_configuration_and_hr_status_change_are_rejected(): void
    {
        DB::table('mm_personnels')->where('id', 1)->update(['shift_end_time' => null]);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertUnprocessable()->assertJsonValidationErrors('shift');
        DB::table('mm_personnels')->where('id', 1)->update(['shift_end_time' => '17:00:00']);
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        DB::table('mm_attendances')->where('id', $id)->update(['status' => 'leave']);
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertConflict();
    }

    public function test_expired_or_soft_deleted_shift_assignment_uses_personnel_fallback(): void
    {
        $shift = DB::table('mm_shifts')->insertGetId(['shift_name' => 'Old shift', 'start_time' => '05:00:00', 'end_time' => '13:00:00', 'working_hours' => 8]);
        DB::table('mm_employee_shifts')->insert([
            ['personnel_id' => 1, 'shift_id' => $shift, 'effective_from' => '2026-10-01', 'effective_to' => '2026-10-20', 'deleted_at' => null],
            ['personnel_id' => 1, 'shift_id' => $shift, 'effective_from' => '2026-10-21', 'effective_to' => null, 'deleted_at' => now()],
        ]);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()
            ->assertJsonPath('data.shift_id', null)->assertJsonPath('data.is_late', false)->assertJsonPath('data.scheduled_start', '2026-10-21 09:00:00');
    }

    public function test_device_access_revocation_and_wrong_token_ability_are_enforced(): void
    {
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 0]);
        $this->app['auth']->forgetGuards();
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertForbidden();
        DB::table('mm_users')->where('id', 1)->update(['is_otp_enabled' => 1]);
        $this->app['auth']->forgetGuards();
        $token = User::findOrFail(1)->createToken('other', ['erp-mcp'])->plainTextToken;
        $this->withHeaders(['Authorization' => 'Bearer '.$token])->postJson('/api/mobile/v1/attendances/check-in')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->withHeaders(['Authorization' => ''])->getJson('/api/mobile/v1/attendances')->assertUnauthorized();
    }

    public function test_payroll_rejects_open_mobile_attendance_instead_of_counting_a_full_day(): void
    {
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated();
        $periodId = DB::table('mm_payroll_periods')->insertGetId(['plant_id' => 1, 'from_date' => '2026-10-21', 'to_date' => '2026-10-21', 'status' => 'draft']);
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Close or correct open mobile attendance');
        app(\App\Services\PayrollGenerationService::class)->generateForPeriod($periodId, 1, [1]);
    }

    public function test_twenty_four_hour_boundary_and_old_checkout_retry_do_not_close_new_session(): void
    {
        $firstId = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->at('2026-10-22 09:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$firstId.'/check-out')->assertOk()->assertJsonPath('data.worked_hours', '24.00');
        $secondId = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/attendances/'.$firstId.'/check-out')->assertOk()->assertJsonPath('data.worked_hours', '24.00');
        $this->assertNotEquals($firstId, $secondId);
        $this->assertNull(Attendance::findOrFail($secondId)->check_out);
    }

    public function test_multiple_open_records_require_hr_review_and_checkout_cannot_forge_time(): void
    {
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out', ['check_out' => '2026-10-21 17:00:00'])
            ->assertUnprocessable()->assertJsonValidationErrors('check_out');
        DB::table('mm_attendances')->insert(['plant_id' => 1, 'personnel_id' => 1, 'attendance_date' => '2026-10-20', 'status' => 'present', 'check_in' => '2026-10-20 09:00:00', 'source' => 'mobile']);
        $this->postJson('/api/mobile/v1/attendances/check-in')->assertConflict();
    }

    public function test_payroll_half_day_status_and_web_absence_are_preserved(): void
    {
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        DB::table('mm_attendances')->where('id', $id)->update(['status' => 'half_day']);
        $this->at('2026-10-21 13:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk()
            ->assertJsonPath('data.status', 'half_day')->assertJsonPath('data.worked_hours', '4.00');
        DB::table('mm_personnels')->where('id', 1)->update(['shift_end_time' => null]);
        session(['active_plant_id' => 1]);
        $controller = app(\App\Http\Controllers\AttendanceController::class);
        $method = new \ReflectionMethod($controller, 'calculateAttendanceMetrics');
        $metrics = $method->invoke($controller, 1, '2026-10-21', null, null, 'absent');
        $this->assertSame(0.0, $metrics['worked_hours']);
        $this->assertFalse($metrics['is_late']);
    }

    public function test_closed_mobile_attendance_on_first_day_is_included_in_generated_payroll(): void
    {
        foreach ([
            'mm_employee_salary_structures' => ['personnel_id', 'salary_component_id', 'amount', 'effective_from', 'effective_to'],
            'mm_salary_components' => ['plant_id', 'name', 'type', 'calculation_type', 'default_value'],
            'mm_leave_applications' => ['personnel_id', 'status', 'from_date', 'to_date'],
            'mm_statutory_configs' => ['plant_id', 'code', 'statute_name', 'rules'],
            'mm_payslip_items' => ['payslip_id', 'salary_component_id', 'component_name', 'type', 'amount', 'calculation_source'],
        ] as $name => $columns) {
            Schema::create($name, function (Blueprint $table) use ($columns) {
                $table->id();
                foreach ($columns as $column) {
                    $table->text($column)->nullable();
                }
                $table->timestamps();
                $table->softDeletes();
            });
        }
        Schema::table('mm_payslips', function (Blueprint $table) {
            foreach (['payslip_no', 'working_days', 'present_days', 'absent_days', 'paid_leave_days', 'gross_salary', 'total_earnings', 'total_deductions', 'net_salary'] as $column) {
                $table->text($column)->nullable();
            }
        });
        DB::table('mm_salary_components')->insert(['id' => 1, 'plant_id' => 1, 'name' => 'Basic', 'type' => 'earning', 'calculation_type' => 'fixed', 'default_value' => 1000]);
        DB::table('mm_employee_salary_structures')->insert(['personnel_id' => 1, 'salary_component_id' => 1, 'amount' => 1000, 'effective_from' => '2026-01-01']);
        $id = $this->postJson('/api/mobile/v1/attendances/check-in')->assertCreated()->json('data.id');
        $this->at('2026-10-21 17:00:00');
        $this->postJson('/api/mobile/v1/attendances/'.$id.'/check-out')->assertOk();
        $periodId = DB::table('mm_payroll_periods')->insertGetId(['plant_id' => 1, 'from_date' => '2026-10-21', 'to_date' => '2026-10-22', 'status' => 'draft']);
        $this->assertSame(1, app(\App\Services\PayrollGenerationService::class)->generateForPeriod($periodId, 1, [1]));
        $slip = \App\Models\Payslip::withoutGlobalScope('plant_id')->firstOrFail();
        $this->assertSame('1.00', $slip->present_days);
        $this->assertSame('1.00', $slip->absent_days);
        $this->assertSame('500.00', $slip->gross_salary);
    }
}
