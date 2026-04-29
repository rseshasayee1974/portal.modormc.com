<?php

require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

echo "Starting Boilerplate Generation...\n";

$modelsPath = app_path('Models');
$files = File::files($modelsPath);

$excludeModels = ['Cache', 'CacheLock', 'Job', 'JobBatch', 'FailedJob', 'Session', 'TelescopeEntry', 'TelescopeEntriesTag', 'TelescopeMonitoring', 'HealthCheckResultHistoryItem', 'ModelHasPermission', 'ModelHasRole', 'RoleHasPermission', 'ActivityLog'];

$allModels = [];

foreach ($files as $file) {
    $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();

    if (in_array($file->getFilenameWithoutExtension(), $excludeModels)) {
        echo "Skipping {$class}\n";
        continue;
    }

    if (class_exists($class) && is_subclass_of($class, 'Illuminate\Database\Eloquent\Model')) {
        $allModels[] = $file->getFilenameWithoutExtension();
        generateFactory($class);
        generateSeeder($class);
        generateTest($class);
    }
}

generateDatabaseSeeder($allModels);

echo "Boilerplate generation complete!\n";

function generateFactory($class)
{
    $modelName = class_basename($class);
    $factoryPath = database_path("factories/{$modelName}Factory.php");

    // if (File::exists($factoryPath)) {
    //     return;
    // }

    try {
        $model = new $class();
        $table = $model->getTable();
        $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
        $fields = [];
        foreach ($columns as $column) {
            if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) continue;

            $type = \Illuminate\Support\Facades\Schema::getColumnType($table, $column);
            $fake = getFakeForType($column, $type);
            $fields[] = "            '{$column}' => {$fake},";
        }
        $fieldsStr = implode("\n", $fields);

        $stub = <<<PHP
<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use App\Models\\{$modelName};

class {$modelName}Factory extends Factory
{
    protected \$model = {$modelName}::class;

    public function definition(): array
    {
        return [
{$fieldsStr}
        ];
    }
}
PHP;
        File::ensureDirectoryExists(database_path('factories'));
        File::put($factoryPath, $stub);
        echo "Created Factory: {$modelName}Factory\n";
    } catch (\Exception $e) {
        echo "Failed to generate factory for {$modelName}: " . $e->getMessage() . "\n";
    }
}

function generateSeeder($class)
{
    $modelName = class_basename($class);
    $seederPath = database_path("seeders/{$modelName}Seeder.php");

    // if (File::exists($seederPath)) {
    //     return;
    // }

    $stub = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\\{$modelName};

class {$modelName}Seeder extends Seeder
{
    public function run(): void
    {
        {$modelName}::factory()->count(10)->create();
    }
}
PHP;
    File::ensureDirectoryExists(database_path('seeders'));
    File::put($seederPath, $stub);
    echo "Created Seeder: {$modelName}Seeder\n";
}

function generateTest($class)
{
    $modelName = class_basename($class);
    $testPath = base_path("tests/Feature/{$modelName}Test.php");

    // if (File::exists($testPath)) {
    //     return;
    // }

    $methodBase = Str::snake(Str::plural($modelName));
    
    $userImport = ($modelName === 'User') ? '' : "use App\Models\User;";

    $stub = <<<PHP
<?php

namespace Tests\Feature;

use App\Models\\{$modelName};
{$userImport}
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class {$modelName}Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \$this->actingAs(User::factory()->create());
    }

    public function test_can_create_{$methodBase}()
    {
        \$data = {$modelName}::factory()->make()->getAttributes();
        
        \$model = {$modelName}::create(\$data);
        \$this->assertDatabaseHas(\$model->getTable(), \$model->getOriginal());
    }

    public function test_can_update_{$methodBase}()
    {
        \$model = {$modelName}::factory()->create();
        \$newData = {$modelName}::factory()->make()->getAttributes();

        \$model->update(\$newData);
        \$this->assertDatabaseHas(\$model->getTable(), \$model->getOriginal());
    }

    public function test_can_delete_{$methodBase}()
    {
        \$model = {$modelName}::factory()->create();

        \$model->delete();
        
        if (in_array('Illuminate\Database\Eloquent\SoftDeletes', class_uses({$modelName}::class))) {
            \$this->assertSoftDeleted(\$model);
        } else {
            \$this->assertDatabaseMissing(\$model->getTable(), ['id' => \$model->id]);
        }
    }
}
PHP;
    File::ensureDirectoryExists(base_path('tests/Feature'));
    File::put($testPath, $stub);
    echo "Created Feature Test: {$modelName}Test\n";
}

function generateDatabaseSeeder($models)
{
    $seederCalls = [];
    foreach ($models as $model) {
        $seederCalls[] = "        \$this->call(\\Database\\Seeders\\{$model}Seeder::class);";
    }
    $callsStr = implode("\n", $seederCalls);

    $stub = <<<PHP
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
{$callsStr}
    }
}
PHP;
    File::put(database_path('seeders/DatabaseSeeder.php'), $stub);
    echo "Updated DatabaseSeeder\n";
}

function getFakeForType($column, $type)
{
    $fake = "fake()->word()";
    
    if ($type === 'date' || $type === 'datetime' || $type === 'timestamp') {
        $fake = "now()";
    } elseif ($type === 'boolean' || $type === 'tinyint' || $type === 'tinyint(1)') {
        $fake = "fake()->boolean()";
    } elseif ($type === 'integer' || $type === 'int' || $type === 'bigint' || $type === 'smallint') {
        if (Str::endsWith($column, '_id')) {
            $fake = "1";
        } else {
            $fake = "fake()->numberBetween(1, 100)";
        }
    } elseif ($type === 'decimal' || $type === 'float' || $type === 'double') {
        $fake = "fake()->randomFloat(2, 0, 1000)";
    } elseif ($column === 'email' || $column === 'email_address') {
        $fake = "fake()->unique()->safeEmail()";
    } elseif (Str::contains($column, 'password')) {
        $fake = "bcrypt('password')";
    } elseif (Str::contains($column, 'phone')) {
        $fake = "fake()->numerify('##########')";
    } elseif (Str::contains($column, 'zip')) {
        $fake = "fake()->postcode()";
    } elseif (Str::contains($column, 'address')) {
        $fake = "fake()->address()";
    } elseif (Str::contains($column, 'city')) {
        $fake = "fake()->city()";
    } elseif (Str::contains(strtolower($column), 'name')) {
        $fake = "fake()->name()";
    } elseif ($type === 'text') {
        $fake = "fake()->text()";
    }

    return $fake;
}
