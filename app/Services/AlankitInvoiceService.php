<?php
/*
Author: ragul-onemodo
Created: 2026-11-01 15:29:57 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use App\Models\EinvoiceAuth;
use App\Models\Plant;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AlankitInvoiceService
{
    public function generate(Plant $plant, EinvoiceAuth $auth, array $payload): array
    {
        $einvoice = app(EInvoiceService::class);
        [, , $gstin] = $einvoice->getPlantCredentials($plant);
        $subscriptionKey = config('services.alankit.subscription_key') ?: $plant->entity?->api_key;
        if (!$subscriptionKey || !$gstin || !$auth->user_name || !$auth->auth_token) {
            throw new RuntimeException('Alankit generation requires subscription key, GSTIN, username and auth token.');
        }

        // Authentication returns an encrypted SEK; unwrap it with the original app key.
        $appKey = base64_decode((string) $auth->app_key, true);
        $encryptedSek = base64_decode((string) $auth->sek_key, true);
        if ($appKey === false || strlen($appKey) !== 32 || $encryptedSek === false || $encryptedSek === '') {
            throw new RuntimeException('Alankit authentication keys are invalid. Please authenticate again.');
        }
        $sek = openssl_decrypt($encryptedSek, 'aes-256-ecb', $appKey, OPENSSL_RAW_DATA);
        if ($sek === false || strlen($sek) !== 32) {
            throw new RuntimeException('Unable to decrypt Alankit session key. Please authenticate again.');
        }

        $encrypted = openssl_encrypt(json_encode($payload, JSON_THROW_ON_ERROR), 'aes-256-ecb', $sek, OPENSSL_RAW_DATA);
        if ($encrypted === false) {
            throw new RuntimeException('Unable to encrypt Alankit invoice payload.');
        }

        $environment = app(AlankitAuthService::class)->isProduction($plant) ? 'production' : 'sandbox';
        $response = Http::acceptJson()->withHeaders([
            'user_name' => $auth->user_name,
            'Ocp-Apim-Subscription-Key' => $subscriptionKey,
            'gstin' => $gstin,
            'AuthToken' => $auth->auth_token,
        ])->timeout(30)->post(config("services.alankit.{$environment}_generate_url"), [
            'Data' => base64_encode($encrypted),
        ]);

        $body = $response->json();
        if (!$response->successful() || !is_array($body) || (string) ($body['Status'] ?? '') !== '1') {
            $fallback = trim(preg_replace('/\s+/', ' ', strip_tags($response->body()))) ?: ('HTTP ' . $response->status());
            throw new RuntimeException('Alankit E-Invoice Generation Failed: ' . $einvoice->extractGatewayErrorMessage($body, $fallback));
        }

        $encryptedData = is_string($body['Data'] ?? null) ? base64_decode($body['Data'], true) : false;
        if ($encryptedData === false || $encryptedData === '') {
            throw new RuntimeException('Alankit response is missing encrypted invoice data.');
        }
        $plaintext = openssl_decrypt($encryptedData, 'aes-256-ecb', $sek, OPENSSL_RAW_DATA);
        if ($plaintext === false) {
            throw new RuntimeException('Unable to decrypt Alankit invoice response.');
        }

        $data = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);

        return $einvoice->validateIrnResponse($data, 'Alankit');
    }
}
