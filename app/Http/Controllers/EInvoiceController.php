<?php

namespace App\Http\Controllers;

use App\Models\Invoice;
use App\Services\EInvoiceService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use App\Http\Controllers\Concerns\AuthorizesModule;

class EInvoiceController extends Controller
{
    use AuthorizesModule;

    protected string $module = 'invoices';
    protected EInvoiceService $eInvoiceService;

    public function __construct(EInvoiceService $eInvoiceService)
    {
        $this->eInvoiceService = $eInvoiceService;
    }

    /**
     * Generate E-Invoice & optional E-Way Bill.
     */
    public function generate(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        try {
            $transportDetails = $request->validate([
                'generate_eway' => 'boolean',
                'vehicle_no' => 'nullable|string|max:20',
                'distance_km' => 'nullable|numeric',
                'trans_mode' => 'nullable|string|in:1,2,3,4',
                'vehicle_type' => 'nullable|string|in:Regular,ODC',
                'transporter_id' => 'nullable|string|max:100',
                'transporter_name' => 'nullable|string|max:150',
            ]);

            $this->eInvoiceService->generate($invoice, $transportDetails);

            $message = 'E-Invoice IRN generated successfully!';
            if (!empty($transportDetails['generate_eway'])) {
                $message .= ' E-Way Bill was also successfully generated.';
            }

            return redirect()->back()->with('success', $message);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel E-Invoice.
     */
    public function cancel(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        try {
            $data = $request->validate([
                'cancel_reason' => 'required|string|max:50',
                'cancel_remarks' => 'required|string|max:150',
            ]);

            $this->eInvoiceService->cancel($invoice, $data['cancel_reason'], $data['cancel_remarks']);

            return redirect()->back()->with('success', 'E-Invoice IRN has been cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Generate standalone E-Way Bill for an invoice with a generated E-Invoice.
     */
    public function generateEWayBill(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        try {
            $transportDetails = $request->validate([
                'vehicle_no' => 'required|string|max:20',
                'distance_km' => 'required|numeric',
                'trans_mode' => 'required|string|in:1,2,3,4',
                'vehicle_type' => 'required|string|in:Regular,ODC',
                'transporter_id' => 'nullable|string|max:100',
                'transporter_name' => 'nullable|string|max:150',
            ]);

            $this->eInvoiceService->generateEWayBill($invoice, $transportDetails);

            return redirect()->back()->with('success', 'E-Way Bill has been generated successfully.');
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Cancel standalone E-Way Bill.
     */
    public function cancelEWayBill(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        try {
            $data = $request->validate([
                'cancel_reason' => 'required|string|max:50',
            ]);

            $this->eInvoiceService->cancelEWayBill($invoice, $data['cancel_reason']);

            return redirect()->back()->with('success', 'E-Way Bill has been cancelled successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Setup mock/demo compliance details (GSTIN and Address) for active plant and customer for testing.
     */
    public function setupDemoCompliance(Request $request, Invoice $invoice)
    {
        $this->authorizeModule('edit');

        try {
            // 1. Setup Plant (Seller) Compliance Details
            $plant = $invoice->plant;
            if ($plant) {
                $plant->update([
                    'name' => 'NIC company pvt ltd',
                    'gstin' => '37ARZPT4384Q1MT',
                    'email_address' => 'abc@gmail.com',
                    'mobile_number' => '9000000000',
                ]);

                $state = \App\Models\StateCode::where('state_code', '37')->first();
                $stateId = $state?->id ?? 1;

                // Ensure plant has a primary address
                $plantAddress = $plant->addresses()->where('is_primary', true)->first() ?? $plant->addresses()->first();
                if (!$plantAddress) {
                    $plant->addresses()->create([
                        'is_primary' => true,
                        'line_1' => '5th block, kuvempu layout',
                        'line_2' => 'kuvempu layout',
                        'city' => 'GANDHINAGAR',
                        'zipcode' => '518001',
                        'state_code' => '37',
                        'state_id' => $stateId,
                        'plant_id' => $invoice->plant_id,
                        'address_type_id' => \App\Models\AddressType::first()?->id ?? 1,
                    ]);
                } else {
                    $plantAddress->update([
                        'line_1' => '5th block, kuvempu layout',
                        'line_2' => 'kuvempu layout',
                        'city' => 'GANDHINAGAR',
                        'zipcode' => '518001',
                        'state_code' => '37',
                        'state_id' => $stateId,
                        'is_primary' => true,
                    ]);
                }
            }

            // 2. Setup Customer (Buyer) Compliance Details
            $customer = $invoice->customer;
            if ($customer) {
                $customer->update([
                    'gstin' => '33AAECB2345A1Z1', // Another valid format GSTIN (Tamil Nadu state code 33)
                ]);

                // Ensure customer has a primary address
                $customerAddress = $customer->addresses()->where('is_primary', true)->first() ?? $customer->addresses()->first();
                if (!$customerAddress) {
                    $customer->addresses()->create([
                        'is_primary' => true,
                        'line_1' => '500 Business Park, Phase II',
                        'city' => 'Chennai',
                        'zipcode' => '600002',
                        'state_code' => '33',
                        'state_id' => 1,
                        'plant_id' => $invoice->plant_id,
                        'address_type_id' => \App\Models\AddressType::first()?->id ?? 1,
                    ]);
                } else {
                    $customerAddress->update([
                        'zipcode' => '600002',
                        'state_code' => '33',
                        'is_primary' => true,
                    ]);
                }
            }

            // 3. Setup Invoice Items (ensure tax_id and quantity/rate are valid)
            foreach ($invoice->items as $item) {
                if (empty($item->hsn_code)) {
                    $item->update(['hsn_code' => '38245015']);
                }
                if (!$item->tax_id) {
                    $defaultTax = \App\Models\Tax::first();
                    if ($defaultTax) {
                        $item->update(['tax_id' => $defaultTax->id]);
                    }
                }
                if ($item->quantity <= 0) {
                    $item->update(['quantity' => 1]);
                }
                if ($item->price_unit <= 0) {
                    $item->update(['price_unit' => 1000]);
                }
            }

            // Recalculate totals
            $invoice->recalculate();

            return redirect()->back()->with('success', 'Demo compliance data successfully configured for testing!');
        } catch (\Exception $e) {
            return redirect()->back()->withErrors(['error' => $e->getMessage()]);
        }
    }

    /**
     * Display the compliance testing page.
     */
    public function testPage(Request $request)
    {
        $this->authorizeModule('view');

        $invoices = Invoice::with(['plant', 'customer'])->latest()->limit(30)->get()->map(function($inv) {
            return [
                'id' => $inv->id,
                'number' => $inv->full_number,
                'customer' => $inv->customer?->legal_name ?? 'Unknown Customer',
                'amount' => $inv->total_amount,
                'status' => $inv->einvoice_status ?? 'draft',
            ];
        });

        $plants = \App\Models\Plant::all()->map(function($p) {
            return [
                'id' => $p->id,
                'name' => $p->name,
                'gstin' => $p->gstin,
            ];
        });

        $activePlantId = session('active_plant_id');
        $activePlant = \App\Models\Plant::find($activePlantId);
        $entity = $activePlant?->entity ?? \App\Models\Entity::first();

        $defaultCredentials = [
            'einv_username' => $activePlant?->gstin ?? $entity->einv_username ?? '05AAACH6188F1ZM',
            'einv_password' => $activePlant?->einvoice_secret ?? $entity->einv_password ?? 'abc123@@',
            'api_key' => $activePlant?->einvoice_client_id ?? $entity->api_key ?? '834a123a-ee7e-49a9-b800-70eaa9574a81',
            'url' => $entity->url ?? 'modostores.local',
            'gstin' => $activePlant?->gstin ?? '05AAACH6188F1ZM',

            // Whitebooks E-Way Bill details
            'eway_client_id' => $activePlant?->ewaybill_client_id ?? env('WHITEBOOKS_CLIENT_ID', '4fc2797e-c51b-41f4-82b8-529e067f0fa9'),
            'eway_client_secret' => $activePlant?->ewaybill_secret ?? env('WHITEBOOKS_CLIENT_SECRET', '834a123a-ee7e-49a9-b800-70eaa9574a81'),
            'eway_gstin' => $activePlant?->gstin ?? env('WHITEBOOKS_GSTIN', '05AAACH6188F1ZM'),
            'eway_email' => env('WHITEBOOKS_EMAIL', 'sayee@onemodo.com'),
            'eway_ip' => env('WHITEBOOKS_IP', '192.168.0.1'),
        ];

        return \Inertia\Inertia::render('Compliance/Test', [
            'invoices' => $invoices,
            'plants' => $plants,
            'defaultCredentials' => $defaultCredentials,
        ]);
    }

    /**
     * Run a compliance test action.
     */
    public function testAction(Request $request)
    {
        $this->authorizeModule('edit');

        $action = $request->input('action');
        $invoiceId = $request->input('invoice_id');
        $credentials = $request->input('credentials', []);

        try {
            $invoice = Invoice::findOrFail($invoiceId);
            $plant = $invoice->plant;
            $entity = $plant?->entity;

            // Save overrides directly to model so they persist for convenience
            if ($plant) {
                $plantData = [];
                if (isset($credentials['gstin'])) $plantData['gstin'] = $credentials['gstin'];
                if (isset($credentials['api_key'])) $plantData['einvoice_client_id'] = $credentials['api_key'];
                if (isset($credentials['einv_password'])) $plantData['einvoice_secret'] = $credentials['einv_password'];
                if (isset($credentials['eway_client_id'])) $plantData['ewaybill_client_id'] = $credentials['eway_client_id'];
                if (isset($credentials['eway_client_secret'])) $plantData['ewaybill_secret'] = $credentials['eway_client_secret'];
                
                if (count($plantData) > 0) {
                    $plant->update($plantData);
                }
            }

            if ($entity) {
                $entityData = [];
                if (isset($credentials['einv_username'])) $entityData['einv_username'] = $credentials['einv_username'];
                if (isset($credentials['einv_password'])) $entityData['einv_password'] = $credentials['einv_password'];
                if (isset($credentials['api_key'])) $entityData['api_key'] = $credentials['api_key'];
                if (isset($credentials['url'])) $entityData['url'] = $credentials['url'];
                if (count($entityData) > 0) {
                    $entity->update($entityData);
                }
            }

            // Set runtime config overrides for Whitebooks E-Way Bill
            if (!empty($credentials['eway_client_id'])) {
                config(['services.whitebooks.client_id' => $credentials['eway_client_id']]);
            }
            if (!empty($credentials['eway_client_secret'])) {
                config(['services.whitebooks.client_secret' => $credentials['eway_client_secret']]);
            }
            if (!empty($credentials['eway_gstin'])) {
                config(['services.whitebooks.gstin' => $credentials['eway_gstin']]);
            }
            if (!empty($credentials['eway_email'])) {
                config(['services.whitebooks.email' => $credentials['eway_email']]);
            }
            if (!empty($credentials['eway_ip'])) {
                config(['services.whitebooks.ip' => $credentials['eway_ip']]);
            }

            // Execute compliance flow
            if ($action === 'einvoice_generate') {
                $this->eInvoiceService->generate($invoice, ['generate_eway' => false]);
            } elseif ($action === 'einvoice_cancel') {
                $this->eInvoiceService->cancel($invoice, '2', 'Data entry mistake');
            } elseif ($action === 'ewaybill_generate') {
                $this->eInvoiceService->generateEWayBill($invoice, [
                    'vehicle_no' => 'UP15AH1234',
                    'distance_km' => 100,
                    'trans_mode' => '1',
                    'vehicle_type' => 'Regular',
                ]);
            } else {
                throw new \InvalidArgumentException("Invalid test action requested.");
            }

            return response()->json([
                'success' => true,
                'message' => 'Action executed successfully!',
                'trace' => EInvoiceService::$debugTrace,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'trace' => EInvoiceService::$debugTrace,
            ], 422);
        }
    }
}
