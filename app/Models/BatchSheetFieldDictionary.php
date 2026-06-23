<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class BatchSheetFieldDictionary extends Model
{
    use HasFactory;

    protected $table = 'mm_batch_sheet_field_dictionary';

    protected $fillable = [
        'canonical_name',
        'aliases',
        'category',
        'data_type',
        'db_column',
        'db_table',
        'is_system',
    ];

    protected $casts = [
        'aliases' => 'array',
        'is_system' => 'boolean',
    ];

    /**
     * Resolve a raw label to its canonical dictionary entry.
     * Uses exact alias match first, then falls back to fuzzy matching (Levenshtein distance).
     */
    public static function resolveCanonical(string $label, ?string $category = null): ?self
    {
        $cleanLabel = Str::lower(trim($label));
        if (empty($cleanLabel)) {
            return null;
        }

        $query = self::query();
        if ($category) {
            $query->where('category', $category);
        }
        $records = $query->get();

        // 1. Exact match on canonical name or aliases
        foreach ($records as $record) {
            if (Str::lower($record->canonical_name) === $cleanLabel) {
                return $record;
            }
            $aliases = array_map('trim', array_map('Str::lower', $record->aliases ?? []));
            if (in_array($cleanLabel, $aliases, true)) {
                return $record;
            }
        }

        // 2. Fuzzy match via Levenshtein distance
        $bestMatch = null;
        $lowestDistance = 999;
        $maxDistanceThreshold = 3; // Allow up to 3 character edits

        foreach ($records as $record) {
            // Check canonical name
            $canonicalLower = Str::lower($record->canonical_name);
            $dist = levenshtein($cleanLabel, $canonicalLower);
            if ($dist < $lowestDistance && $dist <= $maxDistanceThreshold) {
                $lowestDistance = $dist;
                $bestMatch = $record;
            }

            // Check aliases
            foreach ($record->aliases ?? [] as $alias) {
                $aliasLower = Str::lower(trim($alias));
                $dist = levenshtein($cleanLabel, $aliasLower);
                if ($dist < $lowestDistance && $dist <= $maxDistanceThreshold) {
                    $lowestDistance = $dist;
                    $bestMatch = $record;
                }
            }
        }

        return $bestMatch;
    }
}
