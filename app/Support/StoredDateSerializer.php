<?php
/*
Author: Cipher
Created: 2026-10-23 18:28:48 Asia/Calcutta (UTC+05:30)
*/

namespace App\Support;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StoredDateSerializer
{
    public static function model(Model $model): array
    {
        return self::restoreDates($model, $model->toArray());
    }

    private static function restoreDates(Model $model, array $data): array
    {
        $dates = array_fill_keys($model->getDates(), true);
        foreach ($model->getCasts() as $field => $cast) {
            if (in_array(explode(':', $cast, 2)[0], ['date', 'datetime', 'immutable_date', 'immutable_datetime'], true)) {
                $dates[$field] = true;
            }
        }
        $attributes = $model->getAttributes();
        foreach ($dates as $field => $_) {
            if (array_key_exists($field, $data) && array_key_exists($field, $attributes)) {
                $data[$field] = $attributes[$field];
            }
        }
        // Preserve selected/hidden fields and loaded relations without loading more data.
        foreach ($model->getRelations() as $name => $relation) {
            $key = $model::$snakeAttributes ? Str::snake($name) : $name;
            if (!isset($data[$key]) || !is_array($data[$key])) continue;
            if ($relation instanceof Model) {
                $data[$key] = self::restoreDates($relation, $data[$key]);
            } elseif ($relation instanceof Collection) {
                foreach ($relation as $index => $related) {
                    if (isset($data[$key][$index])) {
                        $data[$key][$index] = self::restoreDates($related, $data[$key][$index]);
                    }
                }
            }
        }
        return $data;
    }
}
