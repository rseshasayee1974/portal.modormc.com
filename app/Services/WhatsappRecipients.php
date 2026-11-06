<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 14:43:29 Asia/Calcutta (UTC+05:30)
*/
namespace App\Services;

use App\Models\{NotificationWhatsapp, Plant};
use Illuminate\Support\Collection;

class WhatsappRecipients
{
    public static function normalize(?string $mobile): ?string
    {
        $mobile = preg_replace('/[\s()+.-]/', '', trim($mobile ?? ''));
        if (strlen($mobile) === 10 && ctype_digit($mobile)) $mobile = '91'.$mobile;
        return preg_match('/^[1-9][0-9]{9,14}$/', $mobile) ? $mobile : null;
    }

    public function forPlant(int $plantId, string $type): Collection
    {
        $entityId = Plant::whereKey($plantId)->value('entity_id');
        return NotificationWhatsapp::where('plant_id', $plantId)->where('type', $type)->where('status', true)
            ->with('user.entityUsers')->get()->map(function ($setting) use ($plantId, $entityId) {
                if (!$setting->user_id) return self::normalize($setting->mobile);
                $user = $setting->user;
                if (!$user?->is_active || !$user->entityUsers->contains(fn ($membership) =>
                    (int) $membership->plant_id === $plantId && (int) $membership->entity_id === (int) $entityId
                    && (int) $membership->role_id === (int) $setting->role_id)) return null;
                return self::normalize($user->mobile);
            })->filter()->unique()->values();
    }
}
