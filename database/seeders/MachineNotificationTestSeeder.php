<?php

/*
Author: Cipher
Created: 2026-10-30 12:55:00 Asia/Calcutta (UTC+05:30)
*/

namespace Database\Seeders;

use App\Helpers\DateTimeHelper;
use App\Models\Machine;
use App\Models\MachineDocument;
use App\Models\NotificationEmail;
use App\Models\Plant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class MachineNotificationTestSeeder extends Seeder
{
    /**
     * Seeds dummy machines, documents, and notification recipients
     * covering every threshold of config/machine_notifications.php:
     * - Reminders before expiry: 30 days, 15 days, 7 days, 1 day
     * - Due today: 0 days
     * - Overdue: 7 days ago (week 1 overdue), 14 days ago (week 2 overdue)
     * - Not yet due: 45 days (should be ignored by reminder engine)
     */
    public function run(): void
    {
        $plant = Plant::where('is_active', 1)->first() ?? Plant::first();
        if (!$plant) {
            $this->command->error('No active plant found to seed test payloads.');
            return;
        }

        $now = CarbonImmutable::now(DateTimeHelper::timezoneForPlant($plant->id))->startOfDay();

        // 1. Ensure a recipient is configured for "Machine Document Expiry" alerts
        $notificationType = config('machine_notifications.type', 'Machine Document Expiry');
        $recipient = NotificationEmail::updateOrCreate(
            [
                'plant_id' => $plant->id,
                'type' => $notificationType,
                'email' => 'fleet-alerts@example.com',
            ],
            [
                'role_name' => 'Fleet Manager',
                'status' => 1,
            ]
        );

        $this->command->info("Configured recipient: {$recipient->email} for '{$notificationType}' at Plant [{$plant->name}].");

        // 2. Create or update test machines with dummy document payloads matching reminder intervals
        $testMachines = [
            [
                'registration' => 'TN 01 DEMO 1001',
                'vehicle_model' => 'Tata Prima 2830.K',
                'vehicle_type' => 'Transit Mixer',
                'documents' => [
                    [
                        'type' => 'insurance',
                        'offset_days' => 30, // threshold 30 days
                        'amount' => 18500.00,
                    ],
                    [
                        'type' => 'fc',
                        'offset_days' => 15, // threshold 15 days
                        'amount' => 6000.00,
                    ],
                ],
            ],
            [
                'registration' => 'TN 01 DEMO 1002',
                'vehicle_model' => 'BharatBenz 2828C',
                'vehicle_type' => 'Transit Mixer',
                'documents' => [
                    [
                        'type' => 'road_tax',
                        'offset_days' => 7, // threshold 7 days
                        'amount' => 12000.00,
                    ],
                    [
                        'type' => 'puc',
                        'offset_days' => 1, // threshold 1 day (tomorrow)
                        'amount' => 800.00,
                    ],
                ],
            ],
            [
                'registration' => 'TN 01 DEMO 1003',
                'vehicle_model' => 'Schwing Stetter S36X',
                'vehicle_type' => 'Boom Pump',
                'documents' => [
                    [
                        'type' => 'permit',
                        'offset_days' => 0, // due today
                        'amount' => 4500.00,
                    ],
                    [
                        'type' => 'national_permit',
                        'offset_days' => -7, // overdue 7 days (Week 1 overdue)
                        'amount' => 9000.00,
                    ],
                    [
                        'type' => 'fitness',
                        'offset_days' => -14, // overdue 14 days (Week 2 overdue)
                        'amount' => 5500.00,
                    ],
                    [
                        'type' => 'insurance',
                        'offset_days' => 45, // not due (outside 30-day window)
                        'amount' => 22000.00,
                    ],
                ],
            ],
        ];

        foreach ($testMachines as $data) {
            $machine = Machine::withoutGlobalScope('plant_id')->updateOrCreate(
                [
                    'plant_id' => $plant->id,
                    'registration' => $data['registration'],
                ],
                [
                    'vehicle_model' => $data['vehicle_model'],
                    'vehicle_type' => $data['vehicle_type'],
                    'is_active' => true,
                ]
            );

            foreach ($data['documents'] as $docData) {
                $expiryDate = $now->addDays($docData['offset_days'])->toDateString();
                $issueDate = $now->addDays($docData['offset_days'])->subYear()->toDateString();

                MachineDocument::updateOrCreate(
                    [
                        'machine_id' => $machine->id,
                        'type' => $docData['type'],
                    ],
                    [
                        'issue_date' => $issueDate,
                        'expiry_date' => $expiryDate,
                        'amount' => $docData['amount'],
                    ]
                );
            }
        }

        $this->command->info("Dummy test payloads successfully seeded for Plant #{$plant->id} ({$plant->name}).");
    }
}

