<?php

namespace App\Services;

use App\Models\Accounts;
use App\Models\AccountsType;
use App\Models\Ledger;
use App\Models\Plant;
use App\Models\Tax;
use App\Models\AccountDefaultSetting;
use App\Models\Module;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductUnit;
use App\Models\MachineType;
use App\Models\ConcreteGrade;
use App\Models\ConcreteGradeItem;
use App\Models\MixDesign;
use App\Models\MixDesignItem;
use App\Models\Patron;
use App\Models\Site;
use App\Models\Department;
use App\Models\Designation;
use App\Models\LeaveType;
use App\Models\Shift;
use App\Models\SalaryComponent;
use App\Models\StatutoryConfig;
use App\Models\PrintTemplate;
use App\Models\Contact;
use App\Models\EntityUser;
use Spatie\Permission\Models\Role;
use App\Models\User;
use App\Models\VoucherType;
use App\Models\PaymentMethod;
use App\Models\CustomSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

use Illuminate\Support\Facades\Mail;

class PlantInitializationService
{
    public function initialize(Plant $plant)
    {
        if ($plant->is_initialized) {
            return false;
        }

        return DB::transaction(function () use ($plant) {
            $this->seedAccounting($plant);
            $this->seedTaxes($plant);
            $this->seedAccountDefaultSettings($plant);
            $this->seedProductsAndCategories($plant);
            $this->seedMachineTypes($plant);
            $this->seedConcreteGradesAndMixDesign($plant);
            $this->seedPatron($plant);
            $this->seedSite($plant);
            $this->seedTemplates($plant);
            // $this->seedModules();
            // $this->seedVoucherTypes($plant);
            // $this->seedPaymentMethods($plant);
            $this->seedCustomSettings($plant);
            $this->seedDepartments($plant);
            $this->seedDesignations($plant);
            $this->seedLeaveTypes($plant);
            $this->seedShifts($plant);
            $this->seedSalaryComponents($plant);
            $this->seedStatutoryConfigs($plant);
            $this->createUserForPlant($plant);

            $plant->update(['is_initialized' => true]);
            return true;
        });
    }

    private function createUserForPlant(Plant $plant)
    {
        if (empty($plant->email_address)) {
            return;
        }

        $password = Str::random(8);
        
        $user = User::updateOrCreate(
            ['email' => $plant->email_address],
            [
                'username' => $plant->name . ' Admin',
                'password' => Hash::make($password),
                'is_active'=>1,
                'is_otp_enabled'=>0,
                'created_at'=> now(),
                'created_by'=> Auth::id() ?? 1,
                'email_verified_at' => null,
            ]
        );

        $role = Role::where('name', 'Super Admin')->first();
        if ($role) {
            $user->assignRole($role);
        }

        EntityUser::updateOrCreate([
            'user_id' => $user->id,
            'entity_id' => $plant->entity_id,
            'plant_id' => $plant->id,
        ], [
            'role_id' => $role ? $role->id : 3, // Defaulting to 3 if role not found
            'created_by' => Auth::id() ?? 1,
        ]);

        // Email sending is now handled by a separate method
        // to allow manual triggering from the UI.
    }

    public function sendPlantCredentials(Plant $plant, string $password)
    {
        if (empty($plant->email_address)) {
            return false;
        }

        try {
            Mail::raw("Welcome to the portal! Your account for plant {$plant->name} has been created.\n\nEmail: {$plant->email_address}\nPassword: {$password}\n\nPlease login at: " . url('/'), function ($message) use ($plant) {
                $message->to($plant->email_address)
                    ->subject("Plant Access Created: {$plant->name}");
            });
            return true;
        } catch (\Exception $e) {
            \Log::error("Failed to send plant initialization email: " . $e->getMessage());
            return false;
        }
    }

    private function seedAccounting(Plant $plant)
    {
        $schema = [
            'ASSET' => [
                'code' => '1000',
                'subgroups' => [
                    'Current Assets' => [
                        'code' => '1100',
                        'ledgers' => [
                            ['code' => '1101', 'title' => 'Cash in Hand', 'is_pnl' => false],
                            ['code' => '1102', 'title' => 'Main Bank Account', 'is_pnl' => false],
                            ['code' => '1103', 'title' => 'Sundry Debtors', 'is_pnl' => false],
                            ['code' => '1104', 'title' => 'Inventory/Stock', 'is_pnl' => false],
                        ]
                    ],
                    'Fixed Assets' => [
                        'code' => '1200',
                        'ledgers' => [
                            ['code' => '1201', 'title' => 'Plant & Machinery', 'is_pnl' => false],
                            ['code' => '1202', 'title' => 'Land & Buildings', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'LIABILITY' => [
                'code' => '2000',
                'subgroups' => [
                    'Current Liabilities' => [
                        'code' => '2100',
                        'ledgers' => [
                            ['code' => '2101', 'title' => 'Sundry Creditors', 'is_pnl' => false],
                            ['code' => '2103', 'title' => 'Outstanding Expenses', 'is_pnl' => false],
                        ]
                    ],
                    'Duties & Taxes' => [
                        'code' => '2400',
                        'ledgers' => [
                            ['code' => '2401', 'title' => 'Output CGST', 'is_pnl' => false],
                            ['code' => '2402', 'title' => 'Output SGST', 'is_pnl' => false],
                            ['code' => '2403', 'title' => 'Output IGST', 'is_pnl' => false],
                            ['code' => '2404', 'title' => 'Input CGST', 'is_pnl' => false],
                            ['code' => '2405', 'title' => 'Input SGST', 'is_pnl' => false],
                            ['code' => '2406', 'title' => 'Input IGST', 'is_pnl' => false],
                            ['code' => '2407', 'title' => 'TDS Payable', 'is_pnl' => false],
                            ['code' => '2408', 'title' => 'TDS Receivable', 'is_pnl' => false],
                        ]
                    ],
                    'Loans (Liability)' => [
                        'code' => '2200',
                        'ledgers' => [
                            ['code' => '2201', 'title' => 'Bank Loans', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'EQUITY' => [
                'code' => '3000',
                'subgroups' => [
                    'Capital Account' => [
                        'code' => '3100',
                        'ledgers' => [
                            ['code' => '3101', 'title' => 'Owner Capital A/c', 'is_pnl' => false],
                        ]
                    ],
                ]
            ],
            'REVENUE' => [
                'code' => '4000',
                'subgroups' => [
                    'Sales Accounts' => [
                        'code' => '4100',
                        'ledgers' => [
                            ['code' => '4101', 'title' => 'Sales Account', 'is_pnl' => true],
                        ]
                    ],
                ]
            ],
            'INCOME' => [
                'code' => '4300',
                'subgroups' => [
                    'Indirect Income' => [
                        'code' => '4301',
                        'ledgers' => [
                            ['code' => '4302', 'title' => 'Other Income', 'is_pnl' => true],
                        ]
                    ],
                ]
            ],
            'EXPENSE' => [
                'code' => '5000',
                'subgroups' => [
                    'Purchase Accounts' => [
                        'code' => '5100',
                        'ledgers' => [
                            ['code' => '5101', 'title' => 'Purchase Account', 'is_pnl' => true],
                        ]
                    ],
                    'Direct Expenses' => [
                        'code' => '5200',
                        'ledgers' => [
                            ['code' => '5201', 'title' => 'Freight & Forwarding', 'is_pnl' => true],
                            ['code' => '5202', 'title' => 'Round Off Account', 'is_pnl' => true],
                            ['code' => '5203', 'title' => 'Adjustment Account', 'is_pnl' => true],
                          
                        ]
                    ],
                    'Indirect Expenses' => [
                        'code' => '5300',
                        'ledgers' => [
                            ['code' => '5301', 'title' => 'Discount Allowed', 'is_pnl' => true],
                            ['code' => '5302', 'title' => 'Office Expenses', 'is_pnl' => true],
                        ]
                    ],
                ]
            ],
        ];

        foreach ($schema as $groupTitle => $groupData) {
            $account = Accounts::updateOrCreate(
                ['code' => $groupData['code'], 'is_system' => true],
                [
                    'title'     => $groupTitle,
                    'is_system' => true,
                    'status'    => 1,
                    'created_by' => Auth::id() ?? 1,
                    'created'   => now(),
                ]
            );

            foreach ($groupData['subgroups'] as $subGroupTitle => $subGroupData) {
                $accountType = AccountsType::updateOrCreate(
                    ['code' => $subGroupData['code'], 'plant_id' => $plant->id],
                    [
                        'account_id' => $account->id,
                        'title'      => $subGroupTitle,
                        'is_system'  => true,
                        'status'     => 1,
                        'created_by' => Auth::id() ?? 1,
                        'created_at' => now(),
                    ]
                );

                foreach ($subGroupData['ledgers'] as $ledgerData) {
                    Ledger::updateOrCreate(
                        ['code' => $ledgerData['code'], 'plant_id' => $plant->id],
                        [
                            'account_type_id' => $accountType->id,
                            'title'           => $ledgerData['title'],
                            'slug'            => Str::slug($ledgerData['title']),
                            'is_pnl'          => $ledgerData['is_pnl'],
                            'is_system'       => true,
                            'status'          => 1,
                            'created_by'      => Auth::id() ?? 1,
                            'created_at'      => now(),
                        ]
                    );
                }
            }
        }
    }

    private function seedTaxes(Plant $plant)
    {
        $slabs = [0, 5, 12, 18, 28];
        foreach ($slabs as $rate) {
            if ($rate == 0) {
                Tax::firstOrCreate([
                    'plant_id' => $plant->id,
                    'tax_name' => "GST $rate% (Exempt) (Sales)",
                ], [
                    'entity_id' => $plant->entity_id,
                    'tax_type' => 'sales',
                    'tax_group' => 'GST',
                    'tax_rate' => $rate,
                    'is_system' => true,
                    'status' => 1,
                ]);
                continue;
            }

            $gstRoot = Tax::updateOrCreate([
                'plant_id' => $plant->id,
                'tax_name' => "GST $rate% (Sales)",
            ], [
                'entity_id' => $plant->entity_id,
                'tax_type' => 'sales',
                'tax_group' => 'GST',
                'tax_rate' => $rate,
                'is_system' => true,
                'status' => 1,
            ]);

            Tax::updateOrCreate([
                'plant_id' => $plant->id,
                'tax_name' => "CGST " . ($rate/2) . "%",
            ], [
                'entity_id' => $plant->entity_id,
                'tax_type' => 'sales',
                'tax_group' => 'CGST',
                'tax_rate' => $rate/2,
                'parent_id' => $gstRoot->id,
                'is_system' => true,
                'status' => 1,
            ]);

            Tax::updateOrCreate([
                'plant_id' => $plant->id,
                'tax_name' => "SGST " . ($rate/2) . "%",
            ], [
                'entity_id' => $plant->entity_id,
                'tax_type' => 'sales',
                'tax_group' => 'SGST',
                'tax_rate' => $rate/2,
                'parent_id' => $gstRoot->id,
                'is_system' => true,
                'status' => 1,
            ]);

            Tax::updateOrCreate([
                'plant_id' => $plant->id,
                'tax_name' => "IGST $rate%",
            ], [
                'entity_id' => $plant->entity_id,
                'tax_type' => 'sales',
                'tax_group' => 'IGST',
                'tax_rate' => $rate,
                'is_system' => true,
                'status' => 1,
            ]);
        }
    }

    private function seedAccountDefaultSettings(Plant $plant)
    {
        $mappings = [
            'Invoice' => [
                'sales_account'     => 'Sales Account',
                'cgst_output'       => 'Output CGST',
                'sgst_output'       => 'Output SGST',
                'igst_output'       => 'Output IGST',
                'shipping_account'  => 'Freight & Forwarding',
                'round_off_account' => 'Round Off Account',
                'adjustment_account'=> 'Adjustment Account',
            ],
            'Purchase' => [
                'purchase_account'  => 'Purchase Account',
                'cgst_input'        => 'Input CGST',
                'sgst_input'        => 'Input SGST',
                'igst_input'        => 'Input IGST',
                'shipping_account'  => 'Freight & Forwarding',
                'round_off_account' => 'Round Off Account',
            ],
            'Payment' => [
                'cash_account'      => 'Cash in Hand',
                'bank_account'      => 'Main Bank Account',
                'discount_allowed'  => 'Discount Allowed',
            ],
            'Receipt' => [
                'cash_account'      => 'Cash in Hand',
                'bank_account'      => 'Main Bank Account',
                'discount_received' => 'Indirect Income',
            ],
            'Patron' => [
                'debit_ledger'      => 'Sundry Debtors',
                'credit_ledger'     => 'Sundry Creditors',
            ],
        ];

        foreach ($mappings as $moduleName => $keys) {
            $module = Module::where('module_name', $moduleName)->first();
            if (!$module) continue;

            foreach ($keys as $key => $ledgerTitle) {
                $ledger = Ledger::where('plant_id', $plant->id)
                    ->where('title', $ledgerTitle)
                    ->first();

                if ($ledger) {
                    AccountDefaultSetting::updateOrCreate(
                        [
                            'plant_id'    => $plant->id,
                            'module_id'   => $module->id,
                            'setting_key' => $key,
                        ],
                        [
                            'module_name' => $module->module_name,
                            'ledger_id'   => $ledger->id,
                            'is_system'   => true,
                            'is_active'   => true,
                        ]
                    );
                }
            }
        }
    }

    private function seedProductsAndCategories(Plant $plant)
    {
        $kgUnit = ProductUnit::where('unit_code', 'KGS')->first() ?? ProductUnit::create(['unit_name' => 'KGS', 'unit_code' => 'KGS', 'is_system' => true, 'status' => 1]);
        $m3Unit = ProductUnit::where('unit_code', 'CBM')->first() ?? ProductUnit::create(['unit_name' => 'CBM', 'unit_code' => 'CBM', 'is_system' => true, 'status' => 1]);
        $bagUnit = ProductUnit::where('unit_code', 'BAG')->first() ?? ProductUnit::create(['unit_name' => 'BAGS', 'unit_code' => 'BAG', 'is_system' => true, 'status' => 1]);
        $nosUnit = ProductUnit::where('unit_code', 'NOS')->first() ?? ProductUnit::create(['unit_name' => 'NUMBERS', 'unit_code' => 'NOS', 'is_system' => true, 'status' => 1]);

        $rmcCategory = ProductCategory::updateOrCreate([
            'plant_id' => $plant->id,
            'name' => 'READY MIX CONCRETE',
        ], [
            'entity_id' => $plant->entity_id,
            'code' => 'RMC',
            'is_system' => true,
            'status' => 1,
        ]);

        $categories = [
            'SAND' => '1',
            'AGGREGATES' => '2',
            'CEMENT' => '3',
            'FINE AGGREGATES' => '4',
            'WATER' => '5',
            'ADMIXTURE' => '6',
            'SILICA' => '7',
        ];

        $categoryModels = [];
        foreach ($categories as $name => $code) {
            $categoryModels[$name] = ProductCategory::updateOrCreate([
                'plant_id' => $plant->id,
                'name' => $name,
            ], [
                'entity_id' => $plant->entity_id,
                'code' => $code,
                'is_system' => true,
                'status' => 1,
            ]);
        }

        Product::updateOrCreate([
            'plant_id' => $plant->id,
            'title' => 'Concrete',
        ], [
            'entity_id' => $plant->entity_id,
            'category_id' => $rmcCategory->id,
            'unit_id' => $m3Unit->id,
            'product_type' =>'Purchase',
            'code' => 'CONC-001',
            'is_system' => true,
            'status' => 1,
        ]);

        $products = [
            ['title' => 'Cement OPC 53 Grade', 'cat' => 'CEMENT', 'code' => 'CMT-001'],
            ['title' => 'Crushed Sand (M-Sand)', 'cat' => 'SAND', 'code' => 'SND-001'],
            ['title' => 'Fine Sand', 'cat' => 'FINE AGGREGATES', 'code' => 'FSND-001'],
            ['title' => 'Coarse Aggregate 10mm', 'cat' => 'AGGREGATES', 'code' => 'AGG-010'],
            ['title' => 'Coarse Aggregate 20mm', 'cat' => 'AGGREGATES', 'code' => 'AGG-020'],
            ['title' => 'Water', 'cat' => 'WATER', 'code' => 'WTR-001'],
            ['title' => 'Admixture', 'cat' => 'ADMIXTURE', 'code' => 'ADM-001'],
            ['title' => 'Silica Fume', 'cat' => 'SILICA', 'code' => 'SIL-001'],
        ];

        foreach ($products as $p) {
            Product::updateOrCreate([
                'plant_id' => $plant->id,
                'title' => $p['title'],
            ], [
                'entity_id' => $plant->entity_id,
                'category_id' => $categoryModels[$p['cat']]->id,
                'unit_id' => $kgUnit->id,
                'code' => $p['code'],
                  'product_type' =>'Purchase',
                'is_system' => true,
                'status' => 1,
            ]);
        }
    }

    private function seedMachineTypes(Plant $plant)
    {
        $types = ['Truck','Batching Plant', 'Transit Mixer', 'Concrete Pump', 'Loader', 'DG Set'];
        foreach ($types as $type) {
            MachineType::updateOrCreate([
                'plant_id' => $plant->id,
                'name' => $type,
            ], [
                'is_system' => true,
            ]);
        }
    }

    private function seedConcreteGradesAndMixDesign(Plant $plant)
    {       
        $kgUnitId = ProductUnit::where('unit_code', 'KGS')->first()->id ?? 1;
        $m3UnitId = ProductUnit::where('unit_code', 'CBM')->first()->id ?? 1;
        $grades = [
            'M10' => ['ratio' => '1:3:6', 'cement' => 1, 'sand' => 3, 'aggregate' => 6],
            'M15' => ['ratio' => '1:2:4', 'cement' => 1, 'sand' => 2, 'aggregate' => 4],
            'M20' => ['ratio' => '1:1.5:3', 'cement' => 1, 'sand' => 1.5, 'aggregate' => 3],
            'M25' => ['ratio' => '1:1:2', 'cement' => 1, 'sand' => 1, 'aggregate' => 2],
            'M30' => ['ratio' => 'Design Mix', 'cement' => null, 'sand' => null, 'aggregate' => null],
            'M35' => ['ratio' => 'Design Mix', 'cement' => null, 'sand' => null, 'aggregate' => null],
            'M40' => ['ratio' => 'Design Mix', 'cement' => null, 'sand' => null, 'aggregate' => null],
        ];

        foreach ($grades as $gradeName => $data) {
            $grade = ConcreteGrade::updateOrCreate([
                'plant_id' => $plant->id,
                'name' => $gradeName,
            ], [
                'concrete_code' => "STD-$gradeName",
                'concrete_ratio' => $data['ratio'],
                'cement_ratio' => $data['cement'],
                'sand_ratio' => $data['sand'],
                'aggregate_ratio' => $data['aggregate'],
                'is_system' => true,
                'status' => 1,
                'created_by' => Auth::id() ?? 1,
            ]);

            // Seed some default items for the grade
            $cement = Product::where('plant_id', $plant->id)->where('title', 'like', '%Cement%')->first();
            if ($cement) {
                ConcreteGradeItem::updateOrCreate([
                    'concrete_grade_id' => $grade->id,
                    'product_id' => $cement->id,
                    'plant_id' => $plant->id,
                ], [
                    'plant_id' => $plant->id,
                    'quantity' => 300,
                    'is_system' => true,
                    'created_by' => Auth::id() ?? 1,
                ]);
            }
        }

        // Default Mix Design
        $patron = Patron::where('plant_id', $plant->id)->first();
        if (!$patron) {
            $this->seedPatron($plant);
            $patron = Patron::where('plant_id', $plant->id)->first();
        }

        $mix = MixDesign::updateOrCreate([
            'plant_id' => $plant->id,
            'design_name' => 'Default M25 Design',
        ], [
            'partner_id' => $patron->id,
            'design_code' => 'DEF-M25',
            'design_type' => 'M25',
            'unit_id' => $m3UnitId,
            'rate_per_qty' => 4500,
                'is_system' => true,
            'created_by' => Auth::id() ?? 1,
        ]);
    }

    private function seedPatron(Plant $plant)
    {
        Patron::updateOrCreate([
            'plant_id' => $plant->id,
            'legal_name' => 'Default Customer',
        ], [
            'entity_id' => $plant->entity_id,
            'code' => 'CUST-001',
            'patron_type' => 'Customer', // The request said 'Customer, Vendor, Transport'
            'is_system' => true,
            'is_active' => true,
            'created_by' => Auth::id() ?? 1,
        ]);

        Patron::updateOrCreate([
            'plant_id' => $plant->id,
            'legal_name' => 'Default Vendor',
        ], [
            'entity_id' => $plant->entity_id,
            'code' => 'VEND-001',
            'patron_type' => 'Vendor',
            'is_system' => true,
            'is_active' => true,
            'created_by' => Auth::id() ?? 1,
        ]);

        Patron::updateOrCreate([
            'plant_id' => $plant->id,
            'legal_name' => 'Default Transporter',
        ], [
            'entity_id' => $plant->entity_id,
            'code' => 'TRANS-001',
            'patron_type' => 'Transport',
            'is_system' => true,
            'is_active' => true,
            'created_by' => Auth::id() ?? 1,
        ]);
    }

    private function seedSite(Plant $plant)
    {
        Site::updateOrCreate([
            'plant_id' => $plant->id,
            'name' => 'Plant Site',
            'type' => 'loading',
        ], [
            'code' => 'PSITE-001',
            'is_system' => true,
            'is_active' => true,
            'created_by' => Auth::id() ?? 1,
        ]);
    }

    private function seedDepartments(Plant $plant)
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
                ['plant_id' => $plant->id, 'code' => $dept['code']],
                [
                    'name'       => $dept['name'],
                    'created_by' => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedDesignations(Plant $plant)
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
                ['plant_id' => $plant->id, 'code' => $desig['code']],
                [
                    'name'       => $desig['name'],
                    'created_by' => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedLeaveTypes(Plant $plant)
    {
        $leaveTypes = [
            ['name' => 'Casual Leave',      'is_paid' => true,  'max_days_per_year' => 12, 'carry_forward' => false],
            ['name' => 'Sick Leave',        'is_paid' => true,  'max_days_per_year' => 7,  'carry_forward' => false],
            ['name' => 'Earned Leave',      'is_paid' => true,  'max_days_per_year' => 15, 'carry_forward' => true],
            ['name' => 'Maternity Leave',   'is_paid' => true,  'max_days_per_year' => 180,'carry_forward' => false],
            ['name' => 'Leave Without Pay', 'is_paid' => false, 'max_days_per_year' => 0,  'carry_forward' => false],
        ];

        foreach ($leaveTypes as $lt) {
            LeaveType::updateOrCreate(
                ['plant_id' => $plant->id, 'name' => $lt['name']],
                [
                    'is_paid'           => $lt['is_paid'],
                    'max_days_per_year' => $lt['max_days_per_year'],
                    'carry_forward'     => $lt['carry_forward'],
                    'created_by'        => Auth::id() ?? 1,
                ]
            );
        }
    }

    private function seedShifts(Plant $plant)
    {
        $shifts = [
            ['shift_name' => 'General Shift', 'start_time' => '09:00', 'end_time' => '18:00', 'grace_time' => 10, 'working_hours' => 9,  'is_night_shift' => false],
            ['shift_name' => 'Morning Shift', 'start_time' => '06:00', 'end_time' => '14:00', 'grace_time' => 10, 'working_hours' => 8,  'is_night_shift' => false],
            ['shift_name' => 'Evening Shift', 'start_time' => '14:00', 'end_time' => '22:00', 'grace_time' => 10, 'working_hours' => 8,  'is_night_shift' => false],
            ['shift_name' => 'Night Shift',   'start_time' => '22:00', 'end_time' => '06:00', 'grace_time' => 10, 'working_hours' => 8,  'is_night_shift' => true],
        ];

        foreach ($shifts as $shift) {
            Shift::updateOrCreate(
                ['plant_id' => $plant->id, 'shift_name' => $shift['shift_name']],
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

    private function seedSalaryComponents(Plant $plant)
    {
        $components = [
            // Earnings
            ['name' => 'Basic Salary',          'type' => 'earning',   'calculation_type' => 'fixed',      'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            ['name' => 'House Rent Allowance',  'type' => 'earning',   'calculation_type' => 'percentage',  'default_value' => 40,   'is_taxable' => false, 'is_statutory' => false],
            ['name' => 'Conveyance Allowance',  'type' => 'earning',   'calculation_type' => 'fixed',       'default_value' => 1600, 'is_taxable' => false, 'is_statutory' => false],
            ['name' => 'Special Allowance',     'type' => 'earning',   'calculation_type' => 'fixed',       'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            ['name' => 'Overtime',              'type' => 'earning',   'calculation_type' => 'fixed',       'default_value' => 0,    'is_taxable' => true,  'is_statutory' => false],
            // Deductions
            ['name' => 'Provident Fund (PF)',   'type' => 'deduction', 'calculation_type' => 'percentage',  'default_value' => 12,   'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'ESI',                   'type' => 'deduction', 'calculation_type' => 'percentage',  'default_value' => 0.75, 'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'Professional Tax',      'type' => 'deduction', 'calculation_type' => 'fixed',       'default_value' => 200,  'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'TDS',                   'type' => 'deduction', 'calculation_type' => 'fixed',       'default_value' => 0,    'is_taxable' => false, 'is_statutory' => true],
            ['name' => 'Advance Deduction',     'type' => 'deduction', 'calculation_type' => 'fixed',       'default_value' => 0,    'is_taxable' => false, 'is_statutory' => false],
        ];

        foreach ($components as $comp) {
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
        }
    }

    private function seedStatutoryConfigs(Plant $plant)
    {
        $configs = [
            [
                'statute_name'   => 'Provident Fund (PF)',
                'effective_from' => now()->startOfYear()->toDateString(),
                'rules'          => [
                    'employee_rate'   => 12,
                    'employer_rate'   => 12,
                    'wage_ceiling'    => 15000,
                    'apply_on'        => 'basic',
                ],
            ],
            [
                'statute_name'   => 'Employee State Insurance (ESI)',
                'effective_from' => now()->startOfYear()->toDateString(),
                'rules'          => [
                    'employee_rate'   => 0.75,
                    'employer_rate'   => 3.25,
                    'wage_ceiling'    => 21000,
                    'apply_on'        => 'gross',
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

    private function seedTemplates(Plant $plant)
    {
        $modules = ['invoice', 'purchase_order', 'quotation', 'dispatch', 'batch', 'billings'];
        $defaultTemplate = PrintTemplate::where('key', 'standard_indigo')->first() 
            ?? PrintTemplate::where('is_system', true)->first();

        if ($defaultTemplate) {
            foreach ($modules as $moduleKey) {
                \App\Models\PrintTemplateSetting::updateOrCreate([
                    'plant_id' => $plant->id,
                    'module_key' => $moduleKey,
                ], [
                    'entity_id' => $plant->entity_id,
                    'print_template_id' => $defaultTemplate->id,
                ]);
            }
        }
    }

    // private function seedModules()
    // {
    //     $modules = [
    //         ['module_name' => 'Invoice', 'display_value' => 'Sales Invoicing'],
    //         ['module_name' => 'Purchase', 'display_value' => 'Purchase Billing'],
    //         ['module_name' => 'Billing', 'display_value' => 'Purchase Billing'],
    //         ['module_name' => 'Payment', 'display_value' => 'Payments'],
    //         ['module_name' => 'Receipt', 'display_value' => 'Receipts'],
    //         ['module_name' => 'Inventory', 'display_value' => 'Inventory Management'],
    //         ['module_name' => 'Patron', 'display_value' => 'Patron Ledgers'],
    //     ];

    //     foreach ($modules as $module) {
    //         Module::updateOrCreate(
    //             ['module_name' => $module['module_name']],
    //             ['display_value' => $module['display_value'], 'is_active' => true]
    //         );
    //     }
    // }

    // private function seedVoucherTypes(Plant $plant)
    // {
    //     $voucherTypes = [
    //         ['journal_name' => 'General Journal', 'short_code' => 'JV', 'is_system_generated' => true, 'prefix' => 'JV-', 'voucher_group' => 'Other'],
    //         ['journal_name' => 'Purchase Journal', 'short_code' => 'PUR', 'is_system_generated' => true, 'prefix' => 'PUR-', 'voucher_group' => 'Purchase'],
    //         ['journal_name' => 'Vendor Bill', 'short_code' => 'VBILL', 'is_system_generated' => true, 'prefix' => 'VBILL-', 'voucher_group' => 'Purchase'],
    //         ['journal_name' => 'Sales Journal', 'short_code' => 'SALE', 'is_system_generated' => true, 'prefix' => 'SALE-', 'voucher_group' => 'Sales'],
    //         ['journal_name' => 'Payment Journal', 'short_code' => 'PAY', 'is_system_generated' => true, 'prefix' => 'PAY-', 'voucher_group' => 'Payment'],
    //         ['journal_name' => 'Receipt Journal', 'short_code' => 'REC', 'is_system_generated' => true, 'prefix' => 'REC-', 'voucher_group' => 'Receipt'],
    //         ['journal_name' => 'Contra Journal', 'short_code' => 'CON', 'is_system_generated' => false, 'prefix' => 'CON-', 'voucher_group' => 'Other'],
    //         ['journal_name' => 'Debit Note', 'short_code' => 'DN', 'is_system_generated' => true, 'prefix' => 'DN-', 'voucher_group' => 'Debit Note'],
    //         ['journal_name' => 'Credit Note', 'short_code' => 'CN', 'is_system_generated' => true, 'prefix' => 'CN-', 'voucher_group' => 'Credit Note'],
    //         ['journal_name' => 'Tax Invoice', 'short_code' => 'TAX', 'is_system_generated' => true, 'prefix' => 'TAX-', 'voucher_group' => 'Sales'],
    //     ];

    //     foreach ($voucherTypes as $type) {
    //         VoucherType::updateOrCreate(
    //             [
    //                 'short_code' => $type['short_code'],
    //                 'plant_id'   => $plant->id
    //             ],
    //             array_merge($type, [
    //                 'entity_id' => $plant->entity_id,
    //                 'plant_id'  => $plant->id
    //             ])
    //         );
    //     }
    // }

    // private function seedPaymentMethods(Plant $plant)
    // {
    //     $methods = [
    //         ['name' => 'Cash', 'description' => 'Cash Payment'],
    //         ['name' => 'UPI', 'description' => 'Digital Wallet / UPI'],
    //         ['name' => 'Bank Transfer', 'description' => 'IMPS/NEFT/RTGS'],
    //         ['name' => 'Check', 'description' => 'Bank Check'],
    //     ];

    //     foreach ($methods as $method) {
    //         PaymentMethod::updateOrCreate(
    //             [
    //                 'name'     => $method['name'],
    //                 'plant_id' => $plant->id
    //             ],
    //             array_merge($method, [
    //                 'plant_id'  => $plant->id,
    //                 'is_active' => true
    //             ])
    //         );
    //     }
    // }

    private function seedCustomSettings(Plant $plant)
    {
        $settings = [
            [
                'module_name' => 'batching',
                'settings' => [
                    'newweight' => 1,
                    'manual_weight' => 1,
                    'camera' => 1,
                    'camera_url' => "",
                    'camera_url_1' => "",
                    'camera_url_2' => "",
                    'loader_gif' => null,
                    'InvoiceInMetricTon' => "1"
                ]
            ],
            [
                'module_name' => 'orders',
                'settings' => [
                    'manualweight' => 1
                ]
            ],
            [
                'module_name' => 'invoices',
                'settings' => [
                    'pdf' => [
                        'company_name' => false,
                        'logo' => true,
                        'address' => true,
                        'phone' => true,
                        'email' => true,
                        'gstin' => true,
                        'invoice_title' => true,
                        'invoice_number' => true,
                        'date' => true,
                        'due_date' => true,
                        'status' => false,
                        'bill_to' => false,
                        'ship_to' => false,
                        'hsn_code' => true,
                        'description' => true,
                        'unit' => true,
                        'discount' => true,
                        'tax_percent' => true,
                        'cgst' => true,
                        'sgst' => true,
                        'igst' => true,
                        'shipping' => true,
                        'round_off' => true,
                        'total_words' => true,
                        'notes' => true,
                        'terms' => true,
                        'signature' => true,
                        'labels' => [
                            'invoice_title' => "TAX INVOICE",
                            'bill_to' => "Bill To",
                            'ship_to' => "Ship To",
                            'rate' => "Rate",
                            'amount' => "Amount"
                        ]
                    ],
                    'excel' => [
                        'hsn_code' => true,
                        'discount' => true
                    ]
                ]
            ],
            [
                'module_name' => 'billing',
                'settings' => [
                    'instant_invoice_patron' => 1,
                    'prefix' => 'BILL/',
                    'next_number' => 1
                ]
            ],
            [
                'module_name' => 'quotation',
                'settings' => [
                    'instant_customer' => 1,
                    'prefix' => 'QTN/',
                    'next_number' => 1
                ]
            ],
            [
                'module_name' => 'purchase',
                'settings' => [
                    'instant_vendor' => 1,
                    'prefix' => 'PO/',
                    'next_number' => 1
                ]
            ],
        ];

        foreach ($settings as $s) {
            CustomSetting::updateOrCreate(
                ['plant_id' => $plant->id, 'module_name' => $s['module_name']],
                ['settings' => $s['settings']]
            );
        }
    }
}
