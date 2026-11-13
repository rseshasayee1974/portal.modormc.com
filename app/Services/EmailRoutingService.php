<?php

/*
Author: Cipher
Created: 2026-11-13 16:22:00 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

class EmailRoutingService
{
    public const CURIE_EMAILS = [
        'curieadmin@onemodo.com',
        'curiesales@onemodo.com',
        'prabakaran@onemodo.com',
    ];

    public const CURIE_URL = 'https://curie.modormc.com';
    public const DEFAULT_URL = 'https://modormc.com';

    public static function shouldRoute(): bool
    {
        if (config('services.email_routing.enabled') !== null) {
            return (bool) config('services.email_routing.enabled');
        }

        return app()->isProduction();
    }

    public static function isCurieEmail(?string $email): bool
    {
        if (!$email) {
            return false;
        }

        return in_array(strtolower(trim($email)), self::CURIE_EMAILS, true);
    }

    public static function resolveUrl(?string $email, ?string $fallback = '/dashboard'): string
    {
        if (!self::shouldRoute()) {
            return $fallback ?? '/dashboard';
        }

        return self::isCurieEmail($email) ? self::CURIE_URL : self::DEFAULT_URL;
    }
}

