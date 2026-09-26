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

        for ($d = 1; $d <= 31; $d++) {
            \App\Models\Attendance::create([
                'plant_id' => $this->plant->id,
                'personnel_id' => $personnel->id,
                'attendance_date' => sprintf('2026-01-%02d', $d),
                'status' => 'present',
            ]);
        }

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        
        $response->assertRedirect();

        $this->assertDatabaseHas('mm_payslips', [
            'payroll_period_id' => $payrollPeriod->id,
            'personnel_id' => $personnel->id,
            'working_days' => 31,
            'gross_salary' => 10000,
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

    public function test_percentage_and_fixed_deduction_calculations_and_zero_paycheck_prevention()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'May 2026',
            'from_date' => '2026-05-01',
            'to_date' => '2026-05-31',
            'status' => 'draft'
        ]);

        $personnel = Personnel::factory()->create([
            'entity_id' => $this->entity->id,
            'plant_id' => $this->plant->id,
            'status' => 'active'
        ]);

        $basicComp = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Basic Salary',
            'type' => 'earning',
            'calculation_type' => 'fixed',
            'default_value' => 30000,
        ]);

        $pfComp = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Provident Fund',
            'type' => 'deduction',
            'calculation_type' => 'percentage',
            'default_value' => 12,
        ]);

        $loanComp = SalaryComponent::create([
            'plant_id' => $this->plant->id,
            'name' => 'Loan Repayment',
            'type' => 'deduction',
            'calculation_type' => 'fixed',
            'default_value' => 5000,
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $basicComp->id,
            'amount' => 30000,
            'effective_from' => '2025-01-01',
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $pfComp->id,
            'amount' => 12, // 12%
            'effective_from' => '2025-01-01',
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $loanComp->id,
            'amount' => 5000,
            'effective_from' => '2025-01-01',
        ]);

        // Personnel worked 1 day out of 31 days
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-05-01',
            'status' => 'present',
        ]);

        for ($d = 2; $d <= 31; $d++) {
            \App\Models\Attendance::create([
                'plant_id' => $this->plant->id,
                'personnel_id' => $personnel->id,
                'attendance_date' => sprintf('2026-05-%02d', $d),
                'status' => 'absent',
            ]);
        }

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        $response->assertRedirect();

        $payslip = Payslip::with('items')->where('payroll_period_id', $payrollPeriod->id)->first();
        $this->assertNotNull($payslip);

        // Basic salary earned = (30000 * 1) / 31 = 967.74
        $this->assertEquals(967.74, $payslip->total_earnings);

        // PF deduction = 12% of earned basic (967.74 * 0.12) = 116.13
        $pfItem = $payslip->items->firstWhere('component_name', 'Provident Fund');
        $this->assertEquals(116.13, $pfItem->amount);

        // Loan repayment fixed deduction (5000) is capped so total deductions don't exceed earnings
        // Remaining net earnings before loan = 967.74 - 116.13 = 851.61
        $loanItem = $payslip->items->firstWhere('component_name', 'Loan Repayment');
        $this->assertEquals(851.61, $loanItem->amount);

        // Net salary should be 0.00, NOT negative!
        $this->assertEquals(0.00, $payslip->net_salary);
    }

    public function test_generate_payslips_single_day_attendance_defaults_unentered_days_to_absent()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'September 2026',
            'from_date' => '2026-09-01',
            'to_date' => '2026-09-30',
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
            'calculation_type' => 'fixed',
            'default_value' => 25000,
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $component->id,
            'amount' => 25000,
            'effective_from' => '2025-01-01',
        ]);

        // Only 1 day of attendance is added (Present on Sept 1st), 29 days are un-entered
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-09-01',
            'status' => 'present',
        ]);

        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        $response->assertRedirect();

        $payslip = Payslip::where('payroll_period_id', $payrollPeriod->id)->first();
        $this->assertNotNull($payslip);

        // 1 day logged present, 29 un-entered days default to absent
        $this->assertEquals(1.0, $payslip->present_days);
        $this->assertEquals(29.0, $payslip->absent_days);
        $this->assertEquals(0.0, $payslip->paid_leave_days);
        $this->assertEquals(833.33, $payslip->gross_salary);
    }

    public function test_generate_payslips_partial_attendance_explicit_absent_and_leaves()
    {
        $payrollPeriod = PayrollPeriod::create([
            'plant_id' => $this->plant->id,
            'name' => 'October 2026',
            'from_date' => '2026-10-01',
            'to_date' => '2026-10-31',
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
            'calculation_type' => 'fixed',
            'default_value' => 31000,
        ]);

        EmployeeSalaryStructure::create([
            'personnel_id' => $personnel->id,
            'salary_component_id' => $component->id,
            'amount' => 31000,
            'effective_from' => '2025-01-01',
        ]);

        // Explicit attendance records
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-10-01',
            'status' => 'present',
        ]);
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-10-02',
            'status' => 'absent',
        ]);
        \App\Models\Attendance::create([
            'plant_id' => $this->plant->id,
            'personnel_id' => $personnel->id,
            'attendance_date' => '2026-10-03',
            'status' => 'half_day',
        ]);

        // Approved paid leave for 2 days
        $leaveType = \App\Models\LeaveType::create([
            'plant_id' => $this->plant->id,
            'name' => 'Casual Leave',
            'is_paid' => true,
        ]);

        \App\Models\LeaveApplication::create([
            'personnel_id' => $personnel->id,
            'leave_type_id' => $leaveType->id,
            'from_date' => '2026-10-04',
            'to_date' => '2026-10-05',
            'days' => 2,
            'status' => 'approved',
        ]);

        // Remaining days (Oct 6 - Oct 31 = 26 days) are un-entered (default to absent)
        $response = $this->post(route('payslips.generate'), [
            'payroll_period_id' => $payrollPeriod->id
        ]);
        $response->assertRedirect();

        $payslip = Payslip::where('payroll_period_id', $payrollPeriod->id)->first();
        $this->assertNotNull($payslip);

        // Oct 1: present (1.0)
        // Oct 2: absent (1.0)
        // Oct 3: half_day (0.5 present, 0.5 absent)
        // Oct 4-5: paid leave (2.0 paid leave)
        // Oct 6-31 (26 days): un-entered default absent (26.0)
        // Total present: 1.0 + 0.5 = 1.5
        // Total absent: 1.0 + 0.5 + 26.0 = 27.5
        // Total paid leave: 2.0
        // Total working days: 31
        $this->assertEquals(1.5, $payslip->present_days);
        $this->assertEquals(27.5, $payslip->absent_days);
        $this->assertEquals(2.0, $payslip->paid_leave_days);
        $this->assertEquals(31, $payslip->working_days);
        
        // Paid days = 1.5 + 2.0 = 3.5
        // Gross salary = 31000 * 3.5 / 31 = 3500.00
        $this->assertEquals(3500.00, $payslip->gross_salary);
    }
}
