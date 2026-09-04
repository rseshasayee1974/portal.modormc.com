<?php

namespace App\Services;

use App\Models\EwaybillAuth;
use App\Models\Plant;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EwayBillService
{
    /**
     * Sandbox / Staging default credentials and endpoints.
     */
    public string $sandboxBaseUrl      = 'https://staging.perione.in';
    public string $sandboxClientId     = 'PEWAYS472417d4a8bc74b10d31d4219c6b343c';
    public string $sandboxClientSecret = 'PEWAYS5376280bd5bc93b6c6ddf334a88d7a45';
    public string $sandboxEmail        = 'sayee@onemodo.com';
    public string $sandboxUsername     = 'Bluefox';
    public string $sandboxPassword     = 'Bluefox@123';
    public string $sandboxGstin        = '29AARFB4347G000';

    /**
     * Production default credentials and endpoints.
     */
    public string $prodBaseUrl         = 'https://api.perione.in';
    public string $prodClientId        = 'PEWAYPc38f83975b650189fb86e3e5659d30fe';
    public string $prodClientSecret    = 'PEWAYP52608f1eabd22d36e310b3c341177f49';
    public string $prodEmail           = 'sayee@onemodo.com';

    /**
     * Default network IP address.
     */
    public string $defaultIp           = '192.168.1.98';

    /**
     * Determine if plant or system is in Production mode based on domain or config.
     * 
     * Sandbox: 127.0.0.1 or curie.modormc.com
     * Production: modormc.com
     */
    public function isProduction(?Plant $plant = null): bool
    {
        $host = request()?->getHost() ?? '';

        if (!empty($host)) {
            // Explicit sandbox / test hosts
            if (
                str_contains($host, '127.0.0.1') ||
                str_contains($host, 'localhost') ||
                str_contains($host, 'curie.modormc.com') ||
                str_contains($host, '.test') ||
                str_contains($host, '.local')
            ) {
                return false;
            }

            // Production domain
            if ($host === 'modormc.com' || str_ends_with($host, 'modormc.com')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the generate E-Way Bill endpoint URL.
     */
    public function getGenEwayBillUrl(?Plant $plant = null): string
    {
        $isProd  = $this->isProduction($plant);
        $baseUrl = $isProd ? $this->prodBaseUrl : $this->sandboxBaseUrl;
        $email   = $isProd ? ($plant?->email_address ?: $this->prodEmail) : $this->sandboxEmail;

        return rtrim($baseUrl, '/') . '/ewaybillapi/v1.03/ewayapi/genewaybill?email=' . urlencode($email);
    }

    /**
     * Authenticate with PeriOne E-Way Bill gateway and return active auth record.
     */
    public function authenticate(?Plant $plant = null, ?int $userId = null): EwaybillAuth
    {
        $userId  = $userId ?? Auth::id() ?? 1;
        $plantId = $plant?->id ?? 1;

        // 1. Check existing unexpired token from database
        $existingAuth = EwaybillAuth::where('plant_id', $plantId)
            ->where('user_id', $userId)
            ->latest('token_generated_at')
            ->first();

        if ($existingAuth && $existingAuth->token_expiry_at && Carbon::now()->addMinutes(120)->lt($existingAuth->token_expiry_at)) {
            return $existingAuth;
        }

        // 2. Resolve Gateway settings & plant-specific E-Way credentials directly from class properties
        $isProd       = $this->isProduction($plant);
        $baseUrl      = $isProd ? $this->prodBaseUrl : $this->sandboxBaseUrl;
        $clientId     = $isProd ? $this->prodClientId : $this->sandboxClientId;
        $clientSecret = $isProd ? $this->prodClientSecret : $this->sandboxClientSecret;
        $email        = $isProd ? ($plant?->email_address ?: $this->prodEmail) : $this->sandboxEmail;

        $username = $plant?->ewaybill_client_id ?: $this->sandboxUsername;
        $password = $plant?->ewaybill_secret ?: $this->sandboxPassword;
        $gstin    = $isProd 
            ? ($plant?->gstin ?: ($plant?->entity?->gstin ?: ''))
            : ($plant?->gstin ?: $this->sandboxGstin);

        if (empty($username) || empty($password)) {
            $plantName = $plant?->name ?? "Plant #{$plantId}";
            throw new \Exception("E-Way Bill credentials (ewaybill_client_id / ewaybill_secret) are not configured for [{$plantName}].");
        }

        // 3. Request auth token directly from PeriOne E-Way Bill endpoint
        $url       = rtrim($baseUrl, '/') . '/ewaybillapi/v1.03/authenticate?email=' . urlencode($email) . '&username=' . urlencode($username) . '&password=' . urlencode($password);
        $ipAddress = request()?->ip() ?: $this->defaultIp;

        $response = Http::withHeaders([
            'accept'        => '*/*',
            'ip_address'    => $ipAddress,
            'client_id'     => $gatewayConfig['client_id'],
            'client_secret' => $gatewayConfig['client_secret'],
            'gstin'         => $gstin,
        ])->timeout(20)->get($url);

        $body = $response->json() ?? [];
        $statusCd = (string)($body['status_cd'] ?? '');
        if (!$response->successful() || ($statusCd !== '1' && $statusCd !== 'success' && $statusCd !== 'true')) {
            $errorMsg = $this->extractGatewayErrorMessage($body, $response->body() ?: ('HTTP ' . $response->status()));
            Log::error('PeriOne E-Way Bill Auth Error: ' . $errorMsg, ['response' => $body]);
            throw new \Exception('PeriOne E-Way Bill Auth Failed: ' . $errorMsg);
        }

        $tokenExpiry = !empty($body['data']['TokenExpiry']) ? Carbon::parse($body['data']['TokenExpiry']) : Carbon::now()->addHours(6);

        // 4. Save and return session in mm_ewaybill_auth table
        $authRecord = EwaybillAuth::firstOrNew([
            'plant_id' => $plantId,
            'user_id'  => $userId,
        ]);

        if (!$authRecord->exists) {
            $authRecord->created_by = $userId;
            $authRecord->created_at = Carbon::now();
        }

        $authRecord->username           = $username;
        $authRecord->password           = $password;
        $authRecord->gstin              = $gstin;
        $authRecord->token_generated_at = Carbon::now();
        $authRecord->token_expiry_at    = $tokenExpiry;
        $authRecord->modified_by        = $userId;
        $authRecord->modified_at        = Carbon::now();
        $authRecord->save();

        return $authRecord;
    }

    /**
     * Build HTTP headers for E-Way Bill generation requests.
     */
    public function buildGatewayHeaders(EwaybillAuth $auth, string $username, string $password, string $gstin, ?Plant $plant = null): array
    {
        $isProd       = $this->isProduction($plant);
        $clientId     = $isProd ? $this->prodClientId : $this->sandboxClientId;
        $clientSecret = $isProd ? $this->prodClientSecret : $this->sandboxClientSecret;
        $ipAddress    = request()?->ip() ?: $this->defaultIp;
        
        $headers = [
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
            'username'      => $username,
            'password'      => $password,
            'ip_address'    => $ipAddress,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'gstin'         => $gstin,
        ];

       

        return $headers;
    }

    /**
     * Extract human-readable error message from Gateway JSON.
     */
    public function extractGatewayErrorMessage(array $body, string $default): string
    {
        $rawMsg = $body['error_desc'] 
            ?? $body['message'] 
            ?? ($body['error']['message'] ?? null) 
            ?? ($body['error']['error_cd'] ?? null) 
            ?? ($body['status_desc'] ?? null) 
            ?? null;

        if (!empty($rawMsg)) {
            // Check if base64 encoded JSON
            $decoded = base64_decode($rawMsg, true);
            if ($decoded && ($json = json_decode($decoded, true))) {
                if (isset($json['errorCodes'])) {
                    $codes = array_filter(explode(',', trim($json['errorCodes'], ',')));
                    $errorMap = [
                        '108' => 'Invalid Username or Password for the given GSTIN',
                        '212' => 'Total amount with tax mismatch',
                        '217' => 'Pincode does not belong to state code',
                        '371' => 'Invalid Transport distance KM',
                        '372' => 'Invalid Transporter ID / Vehicle Number',
                    ];
                    $explanations = array_map(fn($c) => $errorMap[$c] ?? "Code {$c}", $codes);
                    return implode(', ', $explanations) . " [NIC Error: {$json['errorCodes']}]";
                }
                return $decoded;
            }

            // Check if raw JSON string
            if ($json = json_decode($rawMsg, true)) {
                if (isset($json['errorCodes'])) {
                    $codes = array_filter(explode(',', trim($json['errorCodes'], ',')));
                    $errorMap = [
                        '108' => 'Invalid Username or Password for the given GSTIN',
                        '212' => 'Total amount with tax mismatch',
                        '217' => 'Pincode does not belong to state code',
                        '371' => 'Invalid Transport distance KM',
                        '372' => 'Invalid Transporter ID / Vehicle Number',
                    ];
                    $explanations = array_map(fn($c) => $errorMap[$c] ?? "Code {$c}", $codes);
                    return implode(', ', $explanations) . " [NIC Error: {$json['errorCodes']}]";
                }
            }

            return $rawMsg;
        }

        if (!empty($body['errors']) && is_array($body['errors'])) {
            $first = reset($body['errors']);
            return is_string($first) ? $first : json_encode($first);
        }

        return $default;
    }
}