<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(\Database\Seeders\ApiModuleSeeder::class);

        // ── 1. Core Setup ──────────────────────────────────────
        $this->call(\Database\Seeders\EntityTypeSeeder::class);
        $this->call(\Database\Seeders\EntitySeeder::class);
        $this->call(\Database\Seeders\RoleSeeder::class);
        $this->call(\Database\Seeders\MenuSeeder::class);
        $this->call(\Database\Seeders\PermissionSeeder::class);
        $this->call(\Database\Seeders\UserSeeder::class);
        
        // ── 2. Link User to Entity ─────────────────────────────
        $this->call(\Database\Seeders\EntityUserSeeder::class); // Linking Users to Entities

        // ── 3. Reference Data ──────────────────────────────────
        $this->call(\Database\Seeders\CountrySeeder::class);
        $this->call(\Database\Seeders\TaxSeeder::class);
        $this->call(\Database\Seeders\ProductUnitSeeder::class);
        $this->call(\Database\Seeders\ProductSeeder::class);
        $this->call(\Database\Seeders\MachineSeeder::class);
        $this->call(\Database\Seeders\PersonnelSeeder::class);
        $this->call(\Database\Seeders\SiteSeeder::class);
        
        // ── 4. Main Modules ────────────────────────────────────
        $this->call(\Database\Seeders\AccountTypeSeeder::class);
        $this->call(\Database\Seeders\LedgerSeeder::class);
        $this->call(\Database\Seeders\TripSeeder::class); // Finally Trips
        $this->call(\Database\Seeders\PurchaseOrderSeeder::class);
        $this->call(\Database\Seeders\InvoiceSeeder::class);
        $this->call(\Database\Seeders\PettyCashSeeder::class);
    }
}
