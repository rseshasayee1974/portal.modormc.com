<?php
/*
Author: ragul-onemodo
Created: 2026-11-13 15:40:48 Asia/Calcutta (UTC+05:30)
*/

namespace App\Models\Relations;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PersonnelDesignation extends BelongsTo
{
    public function addConstraints()
    {
        parent::addConstraints();
        if (static::$constraints) {
            $this->query->whereNotNull('plant_id')->where('plant_id', $this->child->plant_id);
        }
    }

    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        return parent::getRelationExistenceQuery($query, $parentQuery, $columns)
            ->whereColumn($query->qualifyColumn('plant_id'), $parentQuery->qualifyColumn('plant_id'));
    }

    public function match(array $models, Collection $results, $relation)
    {
        $models = parent::match($models, $results, $relation);
        foreach ($models as $personnel) {
            $designation = $personnel->getRelation($relation);
            if ($designation && (!$personnel->plant_id || (int) $designation->plant_id !== (int) $personnel->plant_id)) {
                $personnel->setRelation($relation, null);
            }
        }
        return $models;
    }
}
