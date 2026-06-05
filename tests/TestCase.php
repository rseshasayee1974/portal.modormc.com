<?php

namespace Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Gate;

abstract class TestCase extends BaseTestCase
{
    protected static bool $isFirstTest = true;

    protected function setUp(): void
    {
        parent::setUp();
        \Illuminate\Support\Facades\Schema::disableForeignKeyConstraints();
        Gate::before(fn () => true);

        $logFile = storage_path('logs/testing_data.log');
        if (self::$isFirstTest) {
            @unlink($logFile);
            self::$isFirstTest = false;
        }

        $testMethod = method_exists($this, 'name') ? $this->name() : (method_exists($this, 'getName') ? $this->getName() : 'test');
        $testName = get_class($this) . '::' . $testMethod;
        file_put_contents(
            $logFile,
            "\n================================================================================\n" .
            "TEST: {$testName}\n" .
            "================================================================================\n",
            FILE_APPEND
        );

        \Illuminate\Support\Facades\Event::listen('eloquent.*', function ($event, array $data) use ($logFile) {
            $model = $data[0] ?? null;
            if (!$model) {
                return;
            }

            $class = get_class($model);
            $interestingClasses = [
                \App\Models\PurchaseOrder::class,
                \App\Models\PurchaseOrderItem::class,
                \App\Models\PurchaseOrderHistory::class,
                \App\Models\Quantity::class,
                \App\Models\Invoice::class,
                \App\Models\JournalEntry::class,
            ];

            if (!in_array($class, $interestingClasses)) {
                return;
            }

            if (preg_match('/eloquent\.(created|updated|deleted)/', $event, $matches)) {
                $action = strtoupper($matches[1]);
                $logMessage = "[" . now()->toDateTimeString() . "] [{$action}] {$class} (ID: {$model->id})\n";
                if ($action === 'CREATED') {
                    $logMessage .= "  Data: " . json_encode($model->getAttributes(), JSON_PRETTY_PRINT) . "\n";
                } elseif ($action === 'UPDATED') {
                    $logMessage .= "  Changes: " . json_encode($model->getChanges(), JSON_PRETTY_PRINT) . "\n";
                    $logMessage .= "  Original: " . json_encode(array_intersect_key($model->getRawOriginal(), $model->getChanges()), JSON_PRETTY_PRINT) . "\n";
                }
                file_put_contents($logFile, $logMessage, FILE_APPEND);
            }
        });
    }

    protected function assertModelMatchesDatabase(Model $model, array $except = []): void
    {
        $volatileColumns = array_filter(array_merge([
            $model::CREATED_AT,
            $model::UPDATED_AT,
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
            'email_verified_at',
            'last_login',
            'lockout_until',
            'two_factor_confirmed_at',
        ], $except));

        $attributes = collect($model->fresh()->getAttributes())
            ->except($volatileColumns)
            ->all();

        $this->assertDatabaseHas($model->getTable(), $attributes);
    }
}
