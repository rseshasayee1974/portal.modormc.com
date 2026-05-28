<?php

namespace Database\Seeders;

use App\Models\RagDocument;
use App\Services\EmbeddingService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Log;

class RagDocumentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $embeddingService = app(EmbeddingService::class);

        $documents = [
            // 1. FAQ (Frequently Asked Questions)
            [
                'source_type' => 'faq',
                'title' => 'Weighbridge & Serial Scale Connection Issues',
                'content' => "Question: Why is the weighbridge scale showing 'NaN' or timing out during gross/tare weight acquisition?\n\n" .
                             "Answer: This typically occurs due to serial port communication locks or settings mismatch. Ensure that:\n" .
                             "1. No other scale application (e.g. legacy weighbridge software) is running and locking the COM port.\n" .
                             "2. The Web Serial API is running in a secure context (HTTPS or localhost).\n" .
                             "3. Port settings are configured as: Baud rate 9600, Data bits 8, Parity None, Stop bits 1.\n" .
                             "4. If Chrome fails to recognize the device, clear site permissions from the address bar (lock icon) and replug the RS232-to-USB adapter.",
                'source_id' => 'faq-wb-01',
            ],
            [
                'source_type' => 'faq',
                'title' => 'GPS Geofence Entry/Exit Notification Delays',
                'content' => "Question: Why are vehicle entry and exit logs from plant geofences delayed or missing?\n\n" .
                             "Answer: Delays are usually caused by cellular dead zones or overly restrictive geofence boundaries. Guidelines:\n" .
                             "1. Check if the tracker is actively transmitting telemetry in the GpsLatestPosition log.\n" .
                             "2. Expand the plant geofence radius by at least 15-20 meters to account for GPS drift (configured under Settings -> Geofences).\n" .
                             "3. Verify that the vehicle's unique IMEI is mapped correctly to the license plate in the Machines table.",
                'source_id' => 'faq-gps-02',
            ],

            // 2. SOP (Standard Operating Procedure)
            [
                'source_type' => 'sop',
                'title' => 'Monthly Client Reconciliation & Billing SOP',
                'content' => "Procedure for Monthly Client Account Reconciliation:\n" .
                             "1. On the 1st of every month, extract the 'Uninvoiced Dispatches' report for the client.\n" .
                             "2. Cross-reference completed dispatch tickets against physical Delivery Challans signed by the client site engineer.\n" .
                             "3. Verify CGST (9%) and SGST (9%) splits on concrete grades match local taxation structures.\n" .
                             "4. Draft and send the consolidated Sales Invoice via the invoicing screen.\n" .
                             "5. If a discrepancy is reported, do not delete the invoice. Issue a Credit Note to balance the customer ledger account and preserve the audit trail.",
                'source_id' => 'sop-recon-01',
            ],
            [
                'source_type' => 'sop',
                'title' => 'Concrete Quality Testing & Moisture Correction SOP',
                'content' => "Standard Operating Procedure for Concrete Slump & Cube Tests:\n" .
                             "1. Take batch samples directly from the transit mixer chute at the 1/3 and 2/3 points of discharge.\n" .
                             "2. Measure concrete temperature and slump immediately. Target slump for M30 grade is 120mm to 150mm.\n" .
                             "3. Cast three cubes (150mm x 150mm) for each 50 cubic meters poured. Store under wet gunny bags for 24 hours.\n" .
                             "4. Perform curing in standard curing water tanks maintained at 27±2°C.\n" .
                             "5. Conduct compression testing at 7 days (target > 70% strength) and 28 days (target > 100% strength).\n" .
                             "6. Adjust batch moisture parameters in the control panel daily depending on sand wetness index to maintain water-cement ratio.",
                'source_id' => 'sop-qc-02',
            ],

            // 3. PRODUCT (Product Specifications)
            [
                'source_type' => 'product',
                'title' => 'M30 Concrete Mix Design Specifications',
                'content' => "Product Specifications for Grade M30 Concrete (Standard Durability):\n" .
                             "- Strength Class: 30 MPa at 28 days.\n" .
                             "- Water-Cement Ratio: 0.45 maximum.\n" .
                             "- Cement Content: 340 kg/m³ (OPC 53 Grade).\n" .
                             "- Fine Aggregates (Zone II Sand): 680 kg/m³.\n" .
                             "- Coarse Aggregates (20mm down): 710 kg/m³; (10mm down): 470 kg/m³.\n" .
                             "- Admixture (Superplasticizer): 0.8% by weight of cement.\n" .
                             "- Application: Ideal for reinforced concrete slabs, columns, beams, foundation footings, and moderately loaded pavements.",
                'source_id' => 'prod-m30-spec',
            ],
            [
                'source_type' => 'product',
                'title' => 'M40 Concrete Mix Design Specifications',
                'content' => "Product Specifications for Grade M40 High-Performance Concrete:\n" .
                             "- Strength Class: 40 MPa at 28 days.\n" .
                             "- Water-Cement Ratio: 0.40 maximum.\n" .
                             "- Cement Content: 380 kg/m³ (OPC 53 Grade + 10% Silica Fume replacement).\n" .
                             "- Fine Aggregates: 640 kg/m³.\n" .
                             "- Coarse Aggregates (20mm down): 740 kg/m³; (10mm down): 490 kg/m³.\n" .
                             "- Admixture (High-Range Water Reducer): 1.2% by weight of cement.\n" .
                             "- Application: High-rise building columns, prestressed concrete structural elements, bridge girders, and heavy industrial floors.",
                'source_id' => 'prod-m40-spec',
            ],

            // 4. POLICY (Company Policy)
            [
                'source_type' => 'policy',
                'title' => 'Customer Credit Term & Late Payment Policy',
                'content' => "Modor RMC Corporate Credit and Billing Policy:\n" .
                             "1. Standard payment terms are Net 30 days from the invoice date unless otherwise specified in the client agreement.\n" .
                             "2. Maximum credit limits are established by the Financial Controller based on bank rating and historical payment records.\n" .
                             "3. Late payments past 30 days will accrue a late fee of 2.0% per month (24% per annum) calculated on the outstanding balance.\n" .
                             "4. Deliveries to accounts with balances overdue by more than 45 days will be suspended automatically. Re-activation requires full clearance of the overdue amount plus a security deposit.",
                'source_id' => 'pol-credit-01',
            ],
            [
                'source_type' => 'policy',
                'title' => 'Driver Transit Mixer Speed Limits & Geofence Safety Policy',
                'content' => "Safety Policy for Driver Logistics and Transit Mixer Transit:\n" .
                             "1. The speed limit inside plant premises and delivery job sites is restricted to 15 km/h.\n" .
                             "2. The speed limit on public highways is capped at 50 km/h for fully loaded mixers and 60 km/h when empty.\n" .
                             "3. GPS alerts are triggered instantly for any speed exceedances over 5 seconds.\n" .
                             "4. Drivers must not deviate from designated GPS transit corridors. Unauthorized stops longer than 15 minutes will result in immediate inquiry.\n" .
                             "5. Safety gear (Hard hat, safety shoes, high-visibility vest) must be worn at all times when outside the vehicle cabin at job sites.",
                'source_id' => 'pol-driver-safety',
            ],

            // 5. EMAIL (Email Templates)
            [
                'source_type' => 'email',
                'title' => 'Overdue Payment Reminder Notification Template',
                'content' => "Subject: Urgent: Overdue Balance Statement - Modor RMC [Client Name]\n\n" .
                             "Dear [Client Representative],\n\n" .
                             "This is a friendly reminder that your account balance of INR [Overdue Amount] is currently overdue by [Days] days. The outstanding invoice details are attached below.\n\n" .
                             "Please note that according to company credit policy, balances overdue by more than 30 days accrue late interest at 2% monthly. We request you to execute the wire transfer at the earliest to prevent shipping holds on your active work orders.\n\n" .
                             "If you have already processed the payment, please share the transaction reference copy. Thank you for your continued cooperation.\n\n" .
                             "Sincerely,\n" .
                             "Finance Operations Team\n" .
                             "Modor RMC",
                'source_id' => 'tmpl-email-overdue',
            ],
            [
                'source_type' => 'email',
                'title' => 'Dispatch Delay & Site Inconvenience Notification Template',
                'content' => "Subject: Notice of Dispatch Delay: Work Order #[Order No] - Modor RMC\n\n" .
                             "Dear [Client Representative / Site Engineer],\n\n" .
                             "We regret to inform you that transit mixer dispatches scheduled for today, [Date], for your site [Site Location] are experiencing a delay of approximately [Delay Hours] hours.\n\n" .
                             "This delay is due to [Reason - e.g. temporary plant mechanical maintenance / heavy traffic transit conditions]. We are prioritizing your concrete grade [Grade] pours and have mobilized standby trucks to resume batching. We apologize for any inconvenience caused to your schedules.\n\n" .
                             "Our logistics coordinator [Name] will keep you updated with live vehicle GPS links. Thank you for your patience.\n\n" .
                             "Best regards,\n" .
                             "Plant Operations Manager\n" .
                             "Modor RMC",
                'source_id' => 'tmpl-email-delay',
            ],

            // 6. NOTES (Notes / Memo)
            [
                'source_type' => 'notes',
                'title' => 'Batching Plant Scale Calibration Guidelines Memo',
                'content' => "Memo: Plant Scale Calibration Guidelines (Internal Distribution Only)\n" .
                             "Date: April 10, 2026\n" .
                             "To: Batching Operators, Maintenance Engineers\n\n" .
                             "Please conduct monthly calibration checks on the cement, aggregate, water, and admixture load cells every second Saturday. Standard weights (at least 2 tons) must be suspended manually to verify linearity.\n" .
                             "Reported deviations exceeding 0.5% require recalibrating the indicator firmware. Document calibration certificates and upload them under the Settings -> Templates folder.",
                'source_id' => 'memo-calibration-scale',
            ],
            [
                'source_type' => 'notes',
                'title' => 'Accounting Split Ledger Guidelines (CGST & SGST)',
                'content' => "Internal Note: Splitting CGST and SGST Ledger Bookings\n" .
                             "Author: Lead Accountant\n\n" .
                             "Every concrete sales transaction within the home state must be split 50/50 between CGST (Central Goods and Services Tax) and SGST (State Goods and Services Tax) accounts under the Duties & Taxes liability group.\n" .
                             "Do not group taxes under a single 'GST' entry, as it violates compliance requirements and breaks automatic tax reconciliations in the ledger report screen. Ensure default account mapping is checked in Settings -> Default Accounts.",
                'source_id' => 'memo-ledger-tax',
            ]
        ];

        $this->command->info("Seeding RAG Knowledge Documents...");

        foreach ($documents as $doc) {
            $hash = hash('sha256', $doc['content']);

            // Avoid duplicating records
            if (RagDocument::where('content_hash', $hash)->exists()) {
                $this->command->info("Document \"{$doc['title']}\" already exists. Skipping.");
                continue;
            }

            // Try to generate embedding
            $this->command->info("Generating embedding for: \"{$doc['title']}\"...");
            $embedding = $embeddingService->embed($doc['content']);

            if (empty($embedding)) {
                $this->command->warn("⚠️ Failed to generate embedding for \"{$doc['title']}\" (API key missing or request failed). Indexing without vector.");
            }

            RagDocument::create([
                'entity_id'    => null, // Global
                'source_type'  => $doc['source_type'],
                'source_id'    => $doc['source_id'],
                'title'        => $doc['title'],
                'content'      => $doc['content'],
                'embedding'    => empty($embedding) ? null : json_encode($embedding),
                'content_hash' => $hash,
                'token_count'  => $embeddingService->estimateTokens($doc['content']),
                'is_active'    => true,
            ]);
        }

        $this->command->info("✅ RAG Knowledge Documents seeded successfully.");
    }
}
