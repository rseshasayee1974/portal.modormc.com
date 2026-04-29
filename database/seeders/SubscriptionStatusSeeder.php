<?php

namespace Database\Seeders;

use App\Models\SubscriptionStatus;
use Illuminate\Database\Seeder;

class SubscriptionStatusSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['active', 'trial', 'canceled', 'expired', 'archived'] as $statusName) {
            SubscriptionStatus::query()->updateOrCreate(
                ['status_name' => $statusName],
                ['status_name' => $statusName]
            );
        }
    }
}
