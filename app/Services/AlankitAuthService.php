<?php
/*
Author: ragul-onemodo
Created: 2026-11-01 15:26:28 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\EinvoiceAuth;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlankitAuthService
{
    public function authenticate(Plant $plant, int $userId): EinvoiceAuth
    {
        $einvoice = app(EInvoiceService::class);
        [$username, $password, $gstin] = $einvoice->getPlantCredentials($plant);
        $subscriptionKey = config('services.alankit.subscription_key') ?: $plant->entity?->api_key;

        if (!$username || !$password || !$gstin || !$subscriptionKey) {
            throw new RuntimeException('Alankit requires E-Invoice username, password, GSTIN and subscription key.');
        }

        // Authenticate afresh so a PeriOne session is never reused for Alankit.
        $production = $this->isProduction($plant);
        $data = $this->requestAuthFromGateway($username, $password, $gstin, $subscriptionKey, $production);

        return $einvoice->storeAuthRecord(
            $plant->id, $userId, $data['username'], $data['token'], $data['sek'], $data['token_expiry'], $data['app_key']
        );
    }

    public function isProduction(?Plant $plant = null): bool
    {
        $environment = config('services.alankit.environment', 'auto');
        if (!in_array($environment, ['auto', 'sandbox', 'production'], true)) {
            throw new RuntimeException('ALANKIT_ENV must be auto, sandbox or production.');
        }
        if ($environment !== 'auto') {
            return $environment === 'production';
        }

        return request()->getHost() !== 'feynman.modomines.com' && app(EInvoiceService::class)->isProduction($plant);
    }

    public function requestAuthFromGateway(string $username, string $password, string $gstin, string $subscriptionKey, bool $production = false): array
    {
        $environment = $production ? 'production' : 'sandbox';
        $keyPath = config("services.alankit.{$environment}_public_key_path");
        
        if (!$keyPath || !is_readable($keyPath)) {
            throw new RuntimeException("Alankit {$environment} public key file is missing or unreadable.");
        }

        $publicKey = openssl_pkey_get_public(file_get_contents($keyPath));
        if ($publicKey === false) {
            throw new RuntimeException("Alankit {$environment} public key is invalid.");
        }

        $appKey = base64_encode(random_bytes(32));
        $credentials = base64_encode(json_encode([
            'UserName' => $username,
            'Password' => $password,
            'AppKey' => $appKey,
            'ForceRefreshAccessToken' => true,
        ], JSON_THROW_ON_ERROR));
        if (!openssl_public_encrypt($credentials, $encrypted, $publicKey, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Unable to encrypt Alankit authentication data.');
        }

        $response = Http::acceptJson()->withHeaders([
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            'gstin' => $gstin,
        ])->timeout(20)->post(config("services.alankit.{$environment}_auth_url"), [
            'Data' => base64_encode($encrypted),
        ]);
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || (string) ($body['Status'] ?? '') !== '1') {
            $fallback = trim(preg_replace('/\s+/', ' ', strip_tags($response->body()))) ?: ('HTTP ' . $response->status());
            $message = app(EInvoiceService::class)->extractGatewayErrorMessage($body, $fallback);
            throw new RuntimeException('Alankit authentication failed: ' . $message);
        }

        $data = $body['Data'] ?? [];
        foreach (['UserName', 'AuthToken', 'Sek', 'TokenExpiry'] as $field) {
            if (!is_string($data[$field] ?? null) || trim($data[$field]) === '') {
                throw new RuntimeException("Alankit authentication response is missing {$field}.");
            }
        }

        return [
            'username' => $data['UserName'],
            'token' => $data['AuthToken'],
            'sek' => $data['Sek'],
            'token_expiry' => Carbon::parse($data['TokenExpiry']),
            'app_key' => $appKey,
        ];
    }
}
