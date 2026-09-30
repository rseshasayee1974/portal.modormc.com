<?php

namespace App\Services;

use Illuminate\Http\Request;

class LoginDestination
{
    public function resolve(Request $request): string
    {
        foreach ([$request->session()->pull('url.intended'), $request->user()?->last_visit_page] as $url) {
            if (!is_string($url) || trim($url) === '') {
                continue;
            }

            $parts = parse_url($url);
            if ($parts === false || isset($parts['user']) || isset($parts['pass'])
                || (isset($parts['host']) && ($parts['host'] !== $request->getHost()
                    || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)))
                || str_starts_with($url, '//') || preg_match('/[\\\\\x00-\x20]/', $url)) {
                continue;
            }

            $path = '/'.ltrim($parts['path'] ?? '', '/');
            if (preg_match('#^/(?:context|login|logout|register|verifyotp|resendotp|two-factor-challenge|forgot-password|reset-password|verify-email|email)(?:/|$)#', $path)
                || $path === '/') {
                continue;
            }

            return $path.(isset($parts['query']) ? '?'.$parts['query'] : '');
        }

        return '/dashboard';
    }
}
