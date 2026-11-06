<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 11:55:52 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\Plant;
use App\Models\User;
use Illuminate\Support\Collection;

class PlantAlertRecipients
{
    public function forPlant(Plant $plant): Collection
    {
        // A global admin role alone does not subscribe a user to every plant.
        return User::where('is_active', true)->whereHas('entityUsers', fn ($query) => $query
            ->where('entity_id', $plant->entity_id)
            ->where('plant_id', $plant->id)
            ->whereIn('role_id', [2, 3, 4]))
            ->pluck('email')->push($plant->email_address)
            ->flatMap(fn ($email) => preg_split('/[,;]+/', (string) $email))
            ->map(fn ($email) => strtolower(trim($email)))
            ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()->values();
    }
}
