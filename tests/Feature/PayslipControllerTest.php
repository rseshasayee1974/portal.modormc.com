<?php

namespace Tests\Feature;

use App\Models\Payslip;
use App\Models\PayslipItem;
use App\Models\PayrollPeriod;
use App\Models\Personnel;
use App\Models\SalaryComponent;
use App\Models\EmployeeSalaryStructure;
use App\Models\StatutoryConfig;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipControllerTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $entity;
    protected $plant;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->user = User::factory()->create();
        $this->entity = Entity::factory()->create();
        $this->plant = Plant::factory()->create(['entity_id' => $this->entity->id]);
        
        $this->withSession([
            'active_entity_id' => $this->entity->id,
            'active_plant_id' => $this->plant->id
        ]);
        
        $this->actingAs($this->user);
    }

    public function test_can_view_payslips_index()
    {
        $response = $this->get(route('payslips.index'));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Payroll/Index'));
    }

    public function test_can_create_payslip()
    {
        $this->withoutExceptionHandling();
        
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $component = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => '₹',
            'default_value' => 15000,
        ]);

        $data = [
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-001',
            'working_days' => 31,
            'present_days' => 30,
            'absent_days' => 1,
            'paid_leave_days' => 0,
            'gross_salary' => 14516.13,
            'total_earnings' => 14516.13,
            'total_deductions' => 0,
            'net_salary' => 14516.13,
            'status' => 'draft',
            'items' => [
                [
                    'salary_component_id' => $component->id,
                    'component_name' => 'Basic Salary',
                    'type' => 'earning',
                    'amount' => 14516.13,
                ]
            ]
        ];

        $response = $this->post(route('payslips.store'), $data);
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_payslips', [
            'payslip_no' => 'PAY-2026-01-001',
            'personnel_id' => $personnel->id,
        ]);

        $this->assertDatabaseHas('mm_payslip_items', [
            'component_name' => 'Basic Salary',
            'amount' => 14516.13,
        ]);
    }

    public function test_can_view_payslip_pdf()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $payslip = Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-002-PDF',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 15000,
            'total_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'status' => 'draft',
        ]);

        $response = $this->get(route('payslips.show', ['payslip' => $payslip->id]));

        $response->assertStatus(200);
        // Typically Barryvdh\DomPDF returns application/pdf content type for download/stream
        $response->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_can_update_payslip()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $payslip = Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-002',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 15000,
            'total_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'status' => 'draft',
        ]);

        $item = $payslip->items()->create([
            'component_name' => 'Basic',
            'type' => 'earning',
            'amount' => 15000,
        ]);

        $data = [
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-002',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 16000,
            'total_earnings' => 16000,
            'total_deductions' => 500,
            'net_salary' => 15500,
            'status' => 'approved',
            'items' => [
                [
                    'id' => $item->id,
                    'component_name' => 'Basic Salary Updated',
                    'type' => 'earning',
                    'amount' => 16000,
                ],
                [
                    'component_name' => 'Tax',
                    'type' => 'deduction',
                    'amount' => 500,
                ]
            ]
        ];

        $response = $this->put(route('payslips.update', $payslip->id), $data);
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_payslips', [
            'id' => $payslip->id,
            'gross_salary' => 16000,
            'status' => 'approved',
        ]);

        $this->assertDatabaseHas('mm_payslip_items', [
            'id' => $item->id,
            'component_name' => 'Basic Salary Updated',
            'amount' => 16000,
        ]);

        $this->assertDatabaseHas('mm_payslip_items', [
            'payslip_id' => $payslip->id,
            'component_name' => 'Tax',
            'amount' => 500,
        ]);
    }

    public function test_can_delete_payslip()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $payslip = Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-003',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 15000,
            'total_earnings' => 15000,
            'total_deductions' => 0,
            'net_salary' => 15000,
            'status' => 'draft',
        ]);

        $response = $this->delete(route('payslips.destroy', $payslip->id));
        $response->assertRedirect();

        $this->assertSoftDeleted('mm_payslips', ['id' => $payslip->id]);
    }

    public function test_can_generate_payslips()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $component = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => '₹',
            'default_value' => 10000,
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $component->id,
            'amount' => 10000,
            'effective_from' => '2025-01-01',
        ]);

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_payslips', [
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'working_days' => 31,
            'gross_salary' => 10000, // No attendance data means full present days via fallback
        ]);

        $payslip = Payslip::where('payroll_period_id', $payrollPeriod->id)->first();
        $this->assertNotNull($payslip);

        $this->assertDatabaseHas('mm_payslip_items', [
            'payslip_id' => $payslip->id,
            'component_name' => 'Basic Salary',
            'amount' => 10000,
        ]);
    }

    public function test_can_export_ecr()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active',
            'uan' => '100123456789'
        ]);

        StatutoryConfig::create([
            'plant_id' => $this->plant->id,
            'statute_name' => 'Provident Fund (PF)',
            'rules' => [
                'employee_rate' => 12.0,
                'employer_rate' => 12.0,
                'wage_ceiling' => 15000.0
            ],
            'effective_from' => '2025-01-01',
        ]);

        $payslip = Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-ECR',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 15000,
            'total_earnings' => 15000,
            'total_deductions' => 1800,
            'net_salary' => 13200,
            'status' => 'approved',
        ]);

        $payslip->items()->create([
            'component_name' => 'Basic',
            'type' => 'earning',
            'amount' => 15000,
        ]);

        $payslip->items()->create([
            'component_name' => 'Provident Fund',
            'type' => 'deduction',
            'amount' => 1800,
        ]);

        $response = $this->get(route('payslips.export-ecr', ['payroll_period_id' => $payrollPeriod->id]));
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        
        $content = $response->getContent();
        $this->assertStringContainsString('100123456789', $content);
    }

    public function test_can_export_esic()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'January 2026',
            'from_date' => '2026-01-01',
            'to_date' => '2026-01-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active',
            'esi_number' => '200123456789'
        ]);

        StatutoryConfig::create([
            'plant_id' => $this->plant->id,
            'statute_name' => 'Employee State Insurance (ESIC)',
            'rules' => [
                'wage_ceiling' => 21000.0
            ],
            'effective_from' => '2025-01-01',
        ]);

        $payslip = Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-01-ESIC',
            'working_days' => 31,
            'present_days' => 31,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 15000,
            'total_earnings' => 15000,
            'total_deductions' => 112.5,
            'net_salary' => 14887.5,
            'status' => 'approved',
        ]);

        $payslip->items()->create([
            'component_name' => 'Basic',
            'type' => 'earning',
            'amount' => 15000,
        ]);

        $payslip->items()->create([
            'component_name' => 'ESI',
            'type' => 'deduction',
            'amount' => 112.5,
        ]);

        $response = $this->get(route('payslips.export-esic', ['payroll_period_id' => $payrollPeriod->id]));
        
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        
        $content = $response->getContent();
        $this->assertStringContainsString('200123456789', $content);
    }

    public function test_cannot_create_payslip_with_invalid_data()
    {
        $response = $this->post(route('payslips.store'), []);
        
        $response->assertSessionHasErrors(['payroll_period_id', 'personnel_id', 'payslip_no']);
    }

    public function test_cannot_generate_payslip_without_active_personnel()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'February 2026',
            'from_date' => '2026-02-01',
            'to_date' => '2026-02-28',
            'status' => 'draft'
        ]);

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        
        $response->assertSessionHas('error', 'No active personnel found to generate payslips.');
    }

    public function test_generate_payslips_with_attendance_and_leaves()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'March 2026',
            'from_date' => '2026-03-01',
            'to_date' => '2026-03-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $component = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => '₹',
            'default_value' => 31000,
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $component->id,
            'amount' => 31000,
            'effective_from' => '2025-01-01',
        ]);

        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-03-01',
            'status' => 'present',
        ]);
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-03-02',
            'status' => 'present',
        ]);
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-03-03',
            'status' => 'half_day',
        ]);
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-03-04',
            'status' => 'absent',
        ]);

        $leaveType = \App\Models\LeaveType::create([
            'plant_id' => $this->plant->id,
            'name' => 'Sick Leave',
            'is_paid' => true,
        ]);

        \App\Models\LeaveApplication::create([
            'personnel_id' => $personnel->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-03-05',
            'to_date' => '2026-03-06',
            'days' => 2,
            'status' => 'approved',
        ]);

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        
        $response->assertRedirect();

        $payslip = Payslip::where('payroll_period_id', $payrollPeriod->id)->first();
        $this->assertNotNull($payslip);

        $this->assertEquals(2.0, $payslip->paid_leave_days);
        $this->assertTrue($payslip->absent_days >= 1.5);
    }

    public function test_generate_payslips_skips_existing_payslips()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'April 2026',
            'from_date' => '2026-04-01',
            'to_date' => '2026-04-30',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        Payslip::create([
            'plant_id' => $this->plant->id,
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'payslip_no' => 'PAY-2026-04-EXISTING',
            'working_days' => 30,
            'present_days' => 30,
            'absent_days' => 0,
            'paid_leave_days' => 0,
            'gross_salary' => 1000,
            'total_earnings' => 1000,
            'total_deductions' => 0,
            'net_salary' => 1000,
            'status' => 'draft',
        ]);

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        
        $response->assertRedirect();
        
        $this->assertEquals(1, Payslip::where('payroll_period_id', $payrollPeriod->id)->count());
    }
}
