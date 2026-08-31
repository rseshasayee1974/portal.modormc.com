<?php

namespace App\Services\BatchSheet\Drivers;

use App\Services\BatchSheet\Contracts\PlantDriverInterface;
use Illuminate\Support\Facades\Log;

class PlantDriverRegistry
{
    /**
     * @var PlantDriverInterface[]
     */
    protected array $drivers = [];

    public function __construct()
    {
        $this->autoDiscoverDrivers();
    }

    /**
     * Automatically discovers and registers all Plant Driver classes in the Drivers folder.
     * Scales cleanly for 1,000+ distinct plant profile files.
     */
    public function autoDiscoverDrivers(): void
    {
        $driverFiles = glob(__DIR__ . '/*Driver.php');

        foreach ($driverFiles as $file) {
            $classBase = basename($file, '.php');
            $className = 'App\\Services\\BatchSheet\\Drivers\\' . $classBase;

            if (class_exists($className)) {
                $reflection = new \ReflectionClass($className);
                if (!$reflection->isAbstract() && $reflection->implementsInterface(PlantDriverInterface::class)) {
                    $driver = new $className();
                    $this->register($driver);
                }
            }
        }

        Log::info("PlantDriverRegistry: Auto-discovered " . count($this->drivers) . " plant drivers.");
    }

    public function register(PlantDriverInterface $driver): void
    {
        $this->drivers[$driver->getDriverCode()] = $driver;
    }

    /**
     * Automatically resolves the best matching plant driver for the given raw text and context.
     */
    public function resolve(string $rawText, array $context = []): ?PlantDriverInterface
    {
        // 1. Check direct serial match if plant_serial or plant_id is provided
        $targetSerial = $context['plant_serial'] ?? null;
        if ($targetSerial) {
            foreach ($this->drivers as $driver) {
                if ($driver->getPlantSerial() === (string)$targetSerial) {
                    Log::info("PlantDriverRegistry: Resolved direct serial driver: {$driver->getDriverName()}");
                    return $driver;
                }
            }
        }

        // 2. Scan each driver's signature against raw document text
        // (Evaluate specific plant drivers before the generic dynamic fallback)
        $fallbackDriver = null;
        foreach ($this->drivers as $driver) {
            if ($driver instanceof DynamicDbPlantDriver) {
                $fallbackDriver = $driver;
                continue;
            }

            if ($driver->canHandle($rawText, $context)) {
                Log::info("PlantDriverRegistry: Matched plant driver signature: [{$driver->getDriverCode()}] {$driver->getDriverName()}");
                return $driver;
            }
        }

        // 3. Check if dynamic DB template driver can handle it
        if ($fallbackDriver && $fallbackDriver->canHandle($rawText, $context)) {
            Log::info("PlantDriverRegistry: Matched dynamic DB template driver: {$fallbackDriver->getDriverName()}");
            return $fallbackDriver;
        }

        Log::info("PlantDriverRegistry: No specific plant driver matched; falling back to generic parser.");
        return null;
    }

    /**
     * @return PlantDriverInterface[]
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }
}
