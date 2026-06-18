<?php
define('LARAVEL_START', microtime(true));

require __DIR__.'/../vendor/autoload.php';
$app = require_once __DIR__.'/../bootstrap/app.php';

// Bootstrap the console kernel to initialize Facades
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use App\Models\User;
use App\Models\Entity;
use App\Models\Plant;
use App\Models\Role;
use App\Models\EntityUser;

try {
    echo "Running standard database seeders...\n";
    Artisan::call('db:seed', ['--force' => true]);
    echo Artisan::output();
    
    // Check if we have an entity
    $entity = Entity::first();
    if (!$entity) {
        echo "Creating default entity...\n";
        $entity = Entity::create([
            'entity_type' => 1,
            'legal_name' => 'Demo Mining Corp',
            'alias' => 'DMC',
            'email' => 'admin@dmc.com',
            'is_active' => 1,
        ]);
    }
    
    // Check if we have a plant
    $plant = Plant::first();
    if (!$plant) {
        echo "Creating default plant...\n";
        $plant = Plant::create([
            'entity_id' => $entity->id,
            'code' => 'DP01',
            'name' => 'Demo Plant',
            'is_main' => true,
            'is_active' => 1,
            'is_initialized' => true,
        ]);
    }
    
    // Create/update the demo user
    echo "Creating/updating demo user...\n";
    $user = User::updateOrCreate(
        ['email' => 'demo@modomines.com'],
        [
            'username' => 'Demo User',
            'mobile' => '9876543210',
            'password' => Hash::make('password'),
            'default_entity_id' => $entity->id,
            'default_plant_id' => $plant->id,
            'is_active' => true,
            'email_verified_at' => now(),
        ]
    );
    
    // Get SAAS_OWNER role
    $role = Role::where('code', 'SAAS_OWNER')->first();
    if ($role) {
        $user->assignRole($role);
    }
    
    // Ensure the EntityUser relation is correctly populated
    EntityUser::updateOrCreate(
        [
            'user_id' => $user->id,
            'entity_id' => $entity->id,
            'plant_id' => $plant->id,
        ],
        [
            'role_id' => $role ? $role->id : 1,
            'created_by' => $user->id,
        ]
    );
    
    echo "Demo user demo@modomines.com successfully created and seeded!\n";
} catch (\Exception $e) {
    echo "Seeding failed: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}
