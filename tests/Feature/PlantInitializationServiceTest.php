<?php

namespace Tests\Feature;

use App\Models\Accounts;
use App\Models\AccountsType;
use App\Models\Ledger;
use App\Models\Plant;
use App\Models\Entity;
use App\Models\Tax;
use App\Models\AccountDefaultSetting;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\MachineType;
use App\Models\ConcreteGrade;
use App\Models\MixDesign;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\SalaryComponent;
use App\Models\StatutoryConfig;
use App\Models\PrintTemplateSetting;
use App\Models\CustomSetting;
use App\Models\User;
use App\Models\EntityUser;
use Spatie\Permission\Models\Role;
use App\Services\PlantInitializationService;
use Database\Seeders\ModuleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlantInitializationServiceTest extends TestCase
{
    use RefreshDatabase;

    private PlantInitializationService $service;
    private Plant $plant;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed dependencies
        $this->seed(ModuleSeeder::class);
        Role::create(['name' => 'Super Admin', 'guard_name' => 'web']);

        // 2. Create plant
        $entity = Entity::factory()->create();
        $this->plant = Plant::factory()->create([
            'entity_id'      => $entity->id,
            'name'           => 'Test Plant',
            'email_address'  => 'admin@testplant.com',
            'is_initialized' => false,
        ]);

        // 3. Instantiate service
        $this->service = app(PlantInitializationService::class);
    }

    public function test_it_initializes_plant_successfully_and_prevents_reinitialization(): void
    {
        // First run should succeed
        $result = $this->service->initialize($this->plant);
        $this->assertTrue($result);
        
        // Plant is_initialized attribute should be true
        $this->plant->refresh();
        $this->assertTrue((bool) $this->plant->is_initialized);

        // Second run should return false
        $secondResult = $this->service->initialize($this->plant);
        $this->assertFalse($secondResult);
    }

    public function test_it_seeds_accounting_groups_and_ledgers(): void
    {
        $this->service->initialize($this->plant);

        // Assert parent accounts exist
        $this->assertDatabaseHas('accounts', ['title' => 'ASSET', 'code' => '1000']);
        $this->assertDatabaseHas('accounts', ['title' => 'LIABILITY', 'code' => '2000']);
        $this->assertDatabaseHas('accounts', ['title' => 'EQUITY', 'code' => '3000']);
        $this->assertDatabaseHas('accounts', ['title' => 'REVENUE', 'code' => '4000']);
        $this->assertDatabaseHas('accounts', ['title' => 'INCOME', 'code' => '4300']);
        $this->assertDatabaseHas('accounts', ['title' => 'EXPENSE', 'code' => '5000']);

        // Assert sub groups exist
        $this->assertDatabaseHas('accounts_types', ['title' => 'Current Assets', 'code' => '1100', 'plant_id' => $this->plant->id]);
        $this->assertDatabaseHas('accounts_types', ['title' => 'Duties & Taxes', 'code' => '2400', 'plant_id' => $this->plant->id]);
        $this->assertDatabaseHas('accounts_types', ['title' => 'Direct Expenses', 'code' => '5200', 'plant_id' => $this->plant->id]);

        // Assert key system ledgers exist
        $this->assertDatabaseHas('ledgers', ['title' => 'Cash in Hand', 'code' => '1101', 'plant_id' => $this->plant->id]);
        $this->assertDatabaseHas('ledgers', ['title' => 'Sundry Debtors', 'code' => '1103', 'plant_id' => $this->plant->id]);
        $this->assertDatabaseHas('ledgers', ['title' => 'Output CGST', 'code' => '2401', 'plant_id' => $this->plant->id]);
        $this->assertDatabaseHas('ledgers', ['title' => 'Sales Account', 'code' => '4101', 'plant_id' => $this->plant->id]);
    }

    public function test_it_seeds_taxes(): void
    {
        $this->service->initialize($this->plant);

        // Exempt tax
        $this->assertDatabaseHas('taxes', [
            'plant_id' => $this->plant->id,
            'tax_name' => 'GST 0% (Exempt) (Sales)',
            'tax_rate' => 0,
            'tax_group' => 'GST'
        ]);

        // GST 18% slab and components
        $this->assertDatabaseHas('taxes', [
            'plant_id' => $this->plant->id,
            'tax_name' => 'GST 18% (Sales)',
            'tax_rate' => 18,
            'tax_group' => 'GST'
        ]);
        $this->assertDatabaseHas('taxes', [
            'plant_id' => $this->plant->id,
            'tax_name' => 'CGST 9%',
            'tax_rate' => 9,
            'tax_group' => 'CGST'
        ]);
        $this->assertDatabaseHas('taxes', [
            'plant_id' => $this->plant->id,
            'tax_name' => 'SGST 9%',
            'tax_rate' => 9,
            'tax_group' => 'SGST'
        ]);
        $this->assertDatabaseHas('taxes', [
            'plant_id' => $this->plant->id,
            'tax_name' => 'IGST 18%',
            'tax_rate' => 18,
            'tax_group' => 'IGST'
        ]);
    }

    public function test_it_seeds_account_default_settings(): void
    {
        $this->service->initialize($this->plant);

        // AccountDefaultSetting mapping assertions
        $this->assertDatabaseHas('account_default_settings', [
            'plant_id'    => $this->plant->id,
            'module_name' => 'Invoice',
            'setting_key' => 'sales_account'
        ]);
        $this->assertDatabaseHas('account_default_settings', [
            'plant_id'    => $this->plant->id,
            'module_name' => 'Purchase',
            'setting_key' => 'purchase_account'
        ]);
        $this->assertDatabaseHas('account_default_settings', [
            'plant_id'    => $this->plant->id,
            'module_name' => 'Patron',
            'setting_key' => 'debit_ledger'
        ]);
    }

    public function test_it_seeds_products_and_categories(): void
    {
        $this->service->initialize($this->plant);

        // Assert product categories seeded
        $this->assertDatabaseHas('product_categories', ['plant_id' => $this->plant->id, 'name' => 'READY MIX CONCRETE']);
        $this->assertDatabaseHas('product_categories', ['plant_id' => $this->plant->id, 'name' => 'CEMENT']);
        $this->assertDatabaseHas('product_categories', ['plant_id' => $this->plant->id, 'name' => 'SAND']);

        // Assert system products seeded
        $this->assertDatabaseHas('products', ['plant_id' => $this->plant->id, 'title' => 'Concrete', 'code' => 'CONC-001']);
        $this->assertDatabaseHas('products', ['plant_id' => $this->plant->id, 'title' => 'Cement OPC 53 Grade', 'code' => 'CMT-001']);
        $this->assertDatabaseHas('products', ['plant_id' => $this->plant->id, 'title' => 'Crushed Sand (M-Sand)', 'code' => 'SND-001']);
    }

    public function test_it_seeds_machine_types(): void
    {
        $this->service->initialize($this->plant);

        $this->assertDatabaseHas('machine_types', ['plant_id' => $this->plant->id, 'name' => 'Truck']);
        $this->assertDatabaseHas('machine_types', ['plant_id' => $this->plant->id, 'name' => 'Batching Plant']);
        $this->assertDatabaseHas('machine_types', ['plant_id' => $this->plant->id, 'name' => 'Concrete Pump']);
    }

    public function test_it_seeds_concrete_grades_and_mix_designs(): void
    {
        $this->service->initialize($this->plant);

        // Assert concrete grades seeded
        $this->assertDatabaseHas('concrete_grades', ['plant_id' => $this->plant->id, 'name' => 'M10']);
        $this->assertDatabaseHas('concrete_grades', ['plant_id' => $this->plant->id, 'name' => 'M25']);
        $this->assertDatabaseHas('concrete_grades', ['plant_id' => $this->plant->id, 'name' => 'M40']);

        // Assert default mix design seeded
        $this->assertDatabaseHas('mix_designs', [
            'plant_id'    => $this->plant->id,
            'design_name' => 'Default M25 Design',
            'design_code' => 'DEF-M25',
            'design_type' => 'M25'
        ]);
    }

    public function test_it_seeds_patrons_and_sites(): void
    {
        $this->service->initialize($this->plant);

        // Assert default patrons exist
        $this->assertDatabaseHas('patrons', ['plant_id' => $this->plant->id, 'legal_name' => 'Default Customer', 'patron_type' => 'Customer']);
        $this->assertDatabaseHas('patrons', ['plant_id' => $this->plant->id, 'legal_name' => 'Default Vendor', 'patron_type' => 'Vendor']);
        $this->assertDatabaseHas('patrons', ['plant_id' => $this->plant->id, 'legal_name' => 'Default Transporter', 'patron_type' => 'Transport']);

        // Assert default site
        $this->assertDatabaseHas('sites', ['plant_id' => $this->plant->id, 'name' => 'Plant Site', 'type' => 'loading']);
    }

    public function test_it_seeds_departments_and_designations(): void
    {
        $this->service->initialize($this->plant);

        $this->assertDatabaseHas('departments', ['plant_id' => $this->plant->id, 'code' => 'PROD', 'name' => 'Production']);
        $this->assertDatabaseHas('departments', ['plant_id' => $this->plant->id, 'code' => 'ACC', 'name' => 'Accounts']);

        $this->assertDatabaseHas('designations', ['plant_id' => $this->plant->id, 'code' => 'PM', 'name' => 'Plant Manager']);
        $this->assertDatabaseHas('designations', ['plant_id' => $this->plant->id, 'code' => 'ACCT', 'name' => 'Accountant']);
    }

    public function test_it_seeds_leave_types_and_shifts(): void
    {
        $this->service->initialize($this->plant);

        $this->assertDatabaseHas('leave_types', ['plant_id' => $this->plant->id, 'name' => 'Casual Leave', 'is_paid' => 1]);
        $this->assertDatabaseHas('leave_types', ['plant_id' => $this->plant->id, 'name' => 'Leave Without Pay', 'is_paid' => 0]);

        $this->assertDatabaseHas('shifts', ['plant_id' => $this->plant->id, 'shift_name' => 'General Shift', 'start_time' => '09:00', 'end_time' => '18:00']);
        $this->assertDatabaseHas('shifts', ['plant_id' => $this->plant->id, 'shift_name' => 'Night Shift', 'is_night_shift' => 1]);
    }

    public function test_it_seeds_salary_components_and_statutory_configs(): void
    {
        $this->service->initialize($this->plant);

        // Salary components
        $this->assertDatabaseHas('salary_components', ['plant_id' => $this->plant->id, 'name' => 'Basic Salary', 'type' => 'earning']);
        $this->assertDatabaseHas('salary_components', ['plant_id' => $this->plant->id, 'name' => 'Provident Fund (PF)', 'type' => 'deduction']);

        // Statutory configs
        $this->assertDatabaseHas('statutory_configs', ['plant_id' => $this->plant->id, 'statute_name' => 'Provident Fund (PF)']);
        $this->assertDatabaseHas('statutory_configs', ['plant_id' => $this->plant->id, 'statute_name' => 'Employee State Insurance (ESI)']);
    }

    public function test_it_seeds_custom_settings_and_templates(): void
    {
        $this->service->initialize($this->plant);

        // Custom settings
        $this->assertDatabaseHas('custom_settings', ['plant_id' => $this->plant->id, 'module_name' => 'batching']);
        $this->assertDatabaseHas('custom_settings', ['plant_id' => $this->plant->id, 'module_name' => 'invoices']);

        // Print templates settings
        $this->assertDatabaseHas('print_template_settings', ['plant_id' => $this->plant->id, 'module_key' => 'invoices']);
        $this->assertDatabaseHas('print_template_settings', ['plant_id' => $this->plant->id, 'module_key' => 'gst_invoices']);
    }

    public function test_it_creates_admin_user_and_entity_user_mapping(): void
    {
        $this->service->initialize($this->plant);

        // User should be created with plant email
        $user = User::where('email', 'admin@testplant.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('Test Plant Admin', $user->username);
        $this->assertTrue($user->hasRole('Super Admin'));

        // EntityUser mapping should exist
        $this->assertDatabaseHas('entity_users', [
            'user_id'   => $user->id,
            'entity_id' => $this->plant->entity_id,
            'plant_id'  => $this->plant->id
        ]);
    }
}
