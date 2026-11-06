<?php
/*
Author: ragul-onemodo
Created: 2026-11-06 16:07:52 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Illuminate\Support\Facades\DB;

class InstallNotificationMenus
{
    public static function run(): void
    {
        DB::transaction(function () {
            $parent = DB::table('mm_menus')->where('menutype', 1)->whereNull('deleted_at')
                ->whereIn(DB::raw('LOWER(alias)'), ['setting', 'settings'])->value('id');
            // Fresh databases receive these entries when MenuSeeder creates Settings.
            if (!$parent) return;

            foreach ([
                ['notificationemails', 'Notification Emails', 'NOTIFICATION_EMAIL.VIEW', 'EnvelopeIcon', 7],
                ['notificationwhatsapps', 'WhatsApp Notifications', 'NOTIFICATION_WHATSAPP.VIEW', 'ChatBubbleLeftRightIcon', 8],
            ] as [$alias, $title, $permission, $icon, $order]) {
                $existing = DB::table('mm_menus')->where(function ($query) use ($alias) {
                    $query->where('alias', $alias)->orWhereIn('link', ['settings/'.$alias, '/settings/'.$alias]);
                })->first();
                $data = [
                    'menutype' => 2, 'title' => $title, 'alias' => $alias,
                    'link' => 'settings/'.$alias, 'icon' => $icon, 'published' => 1,
                    'parent_id' => $parent, 'level' => 1, 'ordering' => $order,
                    'permission_name' => $permission, 'deleted_at' => null, 'updated_at' => now(),
                ];
                if ($existing) {
                    DB::table('mm_menus')->where('id', $existing->id)->update($data);
                } else {
                    DB::table('mm_menus')->insert($data + ['created_at' => now()]);
                }
            }
        });
    }
}
