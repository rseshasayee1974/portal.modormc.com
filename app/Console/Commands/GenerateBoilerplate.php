<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

class GenerateBoilerplate extends Command
{
    protected $signature = 'generate:boilerplate';
    protected $description = 'Generate Factories, Seeders, and Feature Tests for all Models';

    public function handle()
    {
        $modelsPath = app_path('Models');
        $files = File::files($modelsPath);

        $excludeModels = ['Cache', 'CacheLock', 'Job', 'JobBatch', 'FailedJob', 'Session', 'TelescopeEntry', 'TelescopeEntriesTag', 'TelescopeMonitoring', 'HealthCheckResultHistoryItem', 'ModelHasPermission', 'ModelHasRole', 'RoleHasPermission'];

        $allModels = [];

        foreach ($files as $file) {
            $class = 'App\\Models\\' . $file->getFilenameWithoutExtension();

            if (in_array($file->getFilenameWithoutExtension(), $excludeModels)) {
                $this->info("Skipping {$class}");
                continue;
            }

            if (class_exists($class) && is_subclass_of($class, 'Illuminate\Database\Eloquent\Model')) {
                $allModels[] = $file->getFilenameWithoutExtension();
                $this->generateFactory($class);
                $this->generateSeeder($class);
                $this->generateTest($class);
            }
        }

        $this->generateDatabaseSeeder($allModels);

        $this->info('Boilerplate generation complete!');
    }

    protected function generateFactory($class)
    {
        $modelName = class_basename($class);
        $factoryPath = database_path("factories/{$modelName}Factory.php");

        if (File::exists($factoryPath)) {
            return;
        }

        try {
            $model = new $class();
            $table = $model->getTable();
            $columns = \Illuminate\Support\Facades\Schema::getColumnListing($table);
            
            $fields = [];
            foreach ($columns as $column) {
                if (in_array($column, ['id', 'created_at', 'updated_at', 'deleted_at'])) continue;

                $type = \Illuminate\Support\Facades\Schema::getColumnType($table, $column);
                $fake = $this->getFakeForType($column, $type);
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
            File::put($factoryPath, $stub);
            $this->info("Created Factory: {$modelName}Factory");
        } catch (\Exception \$e) {
            $this->error("Failed to generate factory for {$modelName}: " . \$e->getMessage());
        }
    }

    protected function generateSeeder($class)
    {
        $modelName = class_basename($class);
        $seederPath = database_path("seeders/{$modelName}Seeder.php");

        if (File::exists($seederPath)) {
            return;
        }

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
        $this->info("Created Seeder: {$modelName}Seeder");
    }

    protected function generateTest($class)
    {
        $modelName = class_basename($class);
        $testPath = base_path("tests/Feature/{$modelName}Test.php");

        if (File::exists($testPath)) {
            return;
        }

        $routeBase = Str::kebab(Str::plural($modelName));

        $stub = <<<PHP
<?php

namespace Tests\Feature;

use App\Models\\{$modelName};
use App\Models\User;
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

    public function test_can_create_{$routeBase}()
    {
        \$data = {$modelName}::factory()->make()->toArray();

        // Assuming there's a store route
        // \$response = \$this->post(route('{$routeBase}.store'), \$data);
        // \$response->assertStatus(201);
        
        \$model = {$modelName}::create(\$data);
        \$this->assertDatabaseHas('{\$model->getTable()}', ['id' => \$model->id]);
    }

    public function test_can_update_{$routeBase}()
    {
        \$model = {$modelName}::factory()->create();
        \$newData = {$modelName}::factory()->make()->toArray();

        // \$response = \$this->put(route('{$routeBase}.update', \$model), \$newData);
        // \$response->assertStatus(200);

        \$model->update(\$newData);
        \$this->assertDatabaseHas('{\$model->getTable()}', ['id' => \$model->id]);
    }

    public function test_can_delete_{$routeBase}()
    {
        \$model = {$modelName}::factory()->create();

        // \$response = \$this->delete(route('{$routeBase}.destroy', \$model));
        // \$response->assertStatus(204);

        \$model->delete();
        \$this->assertDatabaseMissing('{\$model->getTable()}', ['id' => \$model->id]);
    }
}
PHP;
        File::ensureDirectoryExists(base_path('tests/Feature'));
        File::put($testPath, $stub);
        $this->info("Created Feature Test: {$modelName}Test");
    }

    protected function generateDatabaseSeeder(\$models)
    {
        $seederCalls = [];
        foreach (\$models as \$model) {
            $seederCalls[] = "        \$this->call({$model}Seeder::class);";
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
        $this->info("Updated DatabaseSeeder");
    }

    protected function getFakeForType(\$column, \$type)
    {
        \$fake = "fake()->word()";
        
        if (Str::contains(\$column, 'email')) {
            \$fake = "fake()->unique()->safeEmail()";
        } elseif (Str::contains(\$column, 'password')) {
            \$fake = "bcrypt('password')";
        } elseif (Str::contains(\$column, 'name')) {
            \$fake = "fake()->name()";
        } elseif (Str::contains(\$column, 'phone')) {
            \$fake = "fake()->phoneNumber()";
        } elseif (Str::contains(\$column, 'address')) {
            \$fake = "fake()->address()";
        } elseif (Str::contains(\$column, 'city')) {
            \$fake = "fake()->city()";
        } elseif (Str::contains(\$column, 'zip')) {
            \$fake = "fake()->postcode()";
        } elseif (\$type === 'integer' || \$type === 'bigint') {
            if (Str::endsWith(\$column, '_id')) {
                // Return a closure that creates a related model if needed, or 1 for simplicity
                \$fake = "1"; // To avoid infinite recursion or uncreated tables, we set to 1. Could be improved.
            } else {
                \$fake = "fake()->randomNumber()";
            }
        } elseif (\$type === 'boolean' || \$type === 'tinyint') {
            \$fake = "fake()->boolean()";
        } elseif (\$type === 'date' || \$type === 'datetime' || \$type === 'timestamp') {
            \$fake = "fake()->dateTime()";
        } elseif (\$type === 'decimal' || \$type === 'float' || \$type === 'double') {
            \$fake = "fake()->randomFloat(2, 0, 1000)";
        } elseif (\$type === 'text') {
            \$fake = "fake()->text()";
        }

        return \$fake;
    }
}
