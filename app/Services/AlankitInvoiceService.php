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
    private const PAYLOAD_FORMAT_VERSION = 'alankit-format-v2';

    public function encodePayload(array $payload): string
    {
        foreach (['SellerDtls', 'BuyerDtls', 'DispDtls', 'ShipDtls'] as $section) {
            foreach (['Addr2' => 3, 'Em' => 6] as $field => $minimum) {
                if (!array_key_exists($field, $payload[$section] ?? [])) continue;
                $value = trim((string) $payload[$section][$field]);
                if ($value === '') {
                    unset($payload[$section][$field]);
                } elseif (mb_strlen($value) < $minimum || mb_strlen($value) > 100) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        "$section.$field" => "$section $field must contain between $minimum and 100 characters, or be left blank.",
                    ]);
                } else {
                    $payload[$section][$field] = $value;
                }
            }
        }

        $amountFields = ['TotAmt', 'Discount', 'PreTaxVal', 'AssAmt', 'CgstAmt', 'SgstAmt', 'IgstAmt',
            'CesAmt', 'CesNonAdvlAmt', 'StateCesAmt', 'StateCesNonAdvlAmt', 'OthChrg', 'TotItemVal'];
        foreach ($payload['ItemList'] ?? [] as $index => $item) {
            foreach (array_merge($amountFields, ['UnitPrice', 'Qty', 'FreeQty', 'GstRt', 'CesRt', 'StateCesRt']) as $field) {
                if (!isset($item[$field])) continue;
                $payload['ItemList'][$index][$field] = round((float) $item[$field], in_array($field, $amountFields, true) ? 2 : 3);
            }
        }
        foreach (['AssVal', 'CgstVal', 'SgstVal', 'IgstVal', 'CesVal', 'StCesVal', 'Discount',
            'OthChrg', 'RndOffAmt', 'TotInvVal', 'TotInvValFc'] as $field) {
            if (isset($payload['ValDtls'][$field])) {
                $payload['ValDtls'][$field] = round((float) $payload['ValDtls'][$field], 2);
            }
        }

        // Older PHP configurations serialize rounded floats with extra binary precision.
        $precision = ini_get('serialize_precision');
        try {
            ini_set('serialize_precision', '-1');
            if (ini_get('serialize_precision') !== '-1') {
                throw new RuntimeException('Alankit payload formatting requires PHP serialize_precision=-1. Update the web server PHP configuration.');
            }
            return json_encode($payload, JSON_THROW_ON_ERROR);
        } finally {
            ini_set('serialize_precision', $precision);
        }
    }

    public function generate(Plant $plant, EinvoiceAuth $auth, array $payload): array
    {
        $einvoice = app(EInvoiceService::class);
        [, , $gstin] = $einvoice->getPlantCredentials($plant);
        $subscriptionKey = config('services.alankit.subscription_key') ?: 'AL6U3z6f85e4y7U1l';
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

        $encodedPayload = $this->encodePayload($payload);
        $encrypted = openssl_encrypt($encodedPayload, 'aes-256-ecb', $sek, OPENSSL_RAW_DATA);
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
            $sentPayload = json_decode($encodedPayload, true, 512, JSON_THROW_ON_ERROR);
            $optionalFields = [];
            foreach (['SellerDtls', 'BuyerDtls', 'DispDtls', 'ShipDtls'] as $section) {
                foreach (['Addr2', 'Em'] as $field) {
                    $optionalFields["$section.$field"] = isset($sentPayload[$section][$field])
                        ? ['present' => true, 'length' => mb_strlen($sentPayload[$section][$field])]
                        : ['present' => false];
                }
            }
            \Illuminate\Support\Facades\Log::warning('Alankit rejected formatted invoice payload', [
                'payload_format' => self::PAYLOAD_FORMAT_VERSION,
                'plant_id' => $plant->id,
                'environment' => $environment,
                'optional_fields' => $optionalFields,
            ]);
            throw new RuntimeException('Alankit E-Invoice Generation Failed [' . self::PAYLOAD_FORMAT_VERSION . ']: ' . $einvoice->extractGatewayErrorMessage($body, $fallback));
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
