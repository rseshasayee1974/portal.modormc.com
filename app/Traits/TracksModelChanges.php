<?php

namespace App\Traits;

use App\Observers\ModelAuditObserver;
use Illuminate\Database\Eloquent\Model;

/**
 * Trait TracksModelChanges
 * 
 * Provides reusable model change tracking for Laravel Eloquent models.
 * Automatically detects changes before saving, normalizes data types,
 * and outputs structured changes or human-readable audit remark strings.
 * 
 * @method static void observe(object|string $class)
 * @mixin \Illuminate\Database\Eloquent\Model
 */
trait TracksModelChanges
{
    /**
     * Get the structured audit changes for the model.
     * Detects changed attributes using getDirty() and compares them
     * with their original values, ignoring specified system/default fields.
     *
     * @param array<int, string> $ignoredFields Additional fields to ignore for this specific call.
     * @return array<int, array{field: string, old: string, new: string}> Array of structured changes.
     */

     
      public static function bootTracksModelChanges(): void
    {
        /** @var \Illuminate\Database\Eloquent\Model|string $class */
        $class = static::class;
        if (method_exists($class, 'observe')) {
            $class::observe(ModelAuditObserver::class);  // Register observer for all the models by default and will be used for that model if the model uses TracksModelChanges trait
        }
    }
    public function getAuditChanges(array $ignoredFields = []): array
    {
        // Default system fields to ignore
        $defaultIgnored = [
            'created_at',
            'updated_at',
            'deleted_at',
            'plant_id',
        ];

        // Retrieve model-defined ignored fields if they exist
        $modelIgnored = property_exists($this, 'auditIgnoredFields') ? $this->auditIgnoredFields : [];

        // Merge all ignored fields
        $allIgnored = array_merge($defaultIgnored, $modelIgnored, $ignoredFields);

        $changes = [];

        // Loop through only the dirty (modified) attributes
        foreach ($this->getDirty() as $key => $newValueRaw) {
            if (in_array($key, $allIgnored, true)) {
                continue;
            }

            // Retrieve casted/mutated values for accurate comparison and normalization
            $oldValue = $this->getOriginal($key);
            $newValue = $this->getAttribute($key);

            $oldNormalized = $this->normalizeAuditValue($oldValue);
            $newNormalized = $this->normalizeAuditValue($newValue);

            // Only register a change if the normalized strings differ
            if ($oldNormalized !== $newNormalized) {
                $changes[] = [
                    'field' => $key,
                    'old'   => $oldNormalized,
                    'new'   => $newNormalized,
                ];
            }
        }

        return $changes;
    }

    /**
     * Get a human-readable, comma-joined string representation of the model changes.
     * Example: title: 'M Sand' => 'M Sand Premium', sales_price: '100' => '120'
     *
     * @return string
     */
    public function getAuditRemarkString(): string
    {
        $changes = $this->getAuditChanges();

        $remarks = [];
        foreach ($changes as $change) {
            $remarks[] = "{$change['field']}: '{$change['old']}' => '{$change['new']}'";
        }

        return implode(', ', $remarks);
    }

    /**
     * Normalize a value to a consistent, human-readable string representation.
     *
     * @param mixed $value The raw or casted attribute value.
     * @return string The normalized string representation.
     */
    protected function normalizeAuditValue(mixed $value): string
    {
        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('d M Y, h:i A');
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        if (is_string($value)) {
            return trim($value);
        }

        return (string) $value;
    }
}
