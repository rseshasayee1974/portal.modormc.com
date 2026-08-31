<?php

namespace App\Services\BatchSheet\Contracts;

interface PlantDriverInterface
{
    /**
     * Unique driver identifier (e.g., 'plant_121', 'plant_782', 'plant_322', 'plant_m1t').
     */
    public function getDriverCode(): string;

    /**
     * Human-readable name of the plant & control system.
     */
    public function getDriverName(): string;

    /**
     * Target plant serial number (if tied to a specific plant).
     */
    public function getPlantSerial(): ?string;

    /**
     * Check if this driver can handle the given raw PDF text or upload metadata.
     */
    public function canHandle(string $rawText, array $context = []): bool;

    /**
     * Extract structured header scalars and material matrix from the text.
     *
     * @return array{headerFields: array, materialRows: array, confidence: float}
     */
    public function parse(string $rawText, array $context = []): array;
}
