<?php

namespace App\Services;

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
     * Get base URL based on environment.
     */
    public function getBaseUrl(?Plant $plant = null): string
    {
        return $this->isProduction($plant) ? $this->prodBaseUrl : $this->sandboxBaseUrl;
    }

    /**
     * Get email based on environment.
     */
    public function getEmail(?Plant $plant = null): string
    {
        return $this->isProduction($plant) ? ($plant?->email_address ?: $this->prodEmail) : $this->sandboxEmail;
    }

    /**
     * Resolve plant credentials for PeriOne E-Way Bill gateway.
     */
    public function getCredentials(?Plant $plant = null): array
    {
        $isProd = $this->isProduction($plant);
        return [
            'baseUrl'      => $this->getBaseUrl($plant),
            'clientId'     => $isProd ? $this->prodClientId : $this->sandboxClientId,
            'clientSecret' => $isProd ? $this->prodClientSecret : $this->sandboxClientSecret,
            'email'        => $this->getEmail($plant),
            'username'     => $plant?->ewaybill_client_id ?: $this->sandboxUsername,
            'password'     => $plant?->ewaybill_secret ?: $this->sandboxPassword,
            'gstin'        => $isProd ? ($plant?->gstin ?: ($plant?->entity?->gstin ?: '')) : ($plant?->gstin ?: $this->sandboxGstin),
            'ip'           => request()?->ip() ?: $this->defaultIp,
        ];
    }

    /**
     * Get the generate E-Way Bill endpoint URL.
     */
    public function getGenEwayBillUrl(?Plant $plant = null): string
    {
        $baseUrl = $this->getBaseUrl($plant);
        $email   = $this->getEmail($plant);

        return rtrim($baseUrl, '/') . '/ewaybillapi/v1.03/ewayapi/genewaybill?email=' . urlencode($email);
    }

    /**
     * Build HTTP headers for E-Way Bill requests.
     */
    public function buildGatewayHeaders(string $username, string $password, string $gstin, ?Plant $plant = null): array
    {
        $isProd       = $this->isProduction($plant);
        $clientId     = $isProd ? $this->prodClientId : $this->sandboxClientId;
        $clientSecret = $isProd ? $this->prodClientSecret : $this->sandboxClientSecret;
        $ipAddress    = request()?->ip() ?: $this->defaultIp;
        
        return [
            'accept'        => 'application/json',
            'content-type'  => 'application/json',
            'username'      => $username,
            'password'      => $password,
            'ip_address'    => $ipAddress,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'gstin'         => $gstin,
        ];
    }

    /**
     * Authenticate with PeriOne E-Way Bill Gateway.
     */
    public function authenticatePortel(?Plant $plant = null): array
    {
        $c = $this->getCredentials($plant);
        $url = rtrim($c['baseUrl'], '/') . '/ewaybillapi/v1.03/authenticate?email=' . urlencode($c['email']) . '&username=' . urlencode($c['username']) . '&password=' . urlencode($c['password']);
        $headers = $this->buildGatewayHeaders($c['username'], $c['password'], $c['gstin'], $plant);

        $response = Http::withHeaders($headers)->timeout(20)->get($url);
        return $response->json() ?? [];
    }

    /**
     * Fetch E-Way Bills list for transporter/taxpayer by date (format: d/m/Y).
     */
    public function fetchEWBList(string $date, ?Plant $plant = null): array
    {
        $c = $this->getCredentials($plant);
        $url = rtrim($c['baseUrl'], '/') . '/ewaybillapi/v1.03/ewayapi/getewaybillsfortransporter?email=' . urlencode($c['email']) . '&date=' . urlencode($date);
        $headers = $this->buildGatewayHeaders($c['username'], $c['password'], $c['gstin'], $plant);

        $response = Http::withHeaders($headers)->timeout(30)->get($url);
        return $response->json() ?? [];
    }

    /**
     * Fetch full E-Way Bill details by EWB number from PeriOne.
     */
    public function fetchEWBDetails(string $ewbNo, ?Plant $plant = null): array
    {
        $c = $this->getCredentials($plant);
        $url = rtrim($c['baseUrl'], '/') . '/ewaybillapi/v1.03/ewayapi/getewaybill?email=' . urlencode($c['email']) . '&ewbNo=' . urlencode($ewbNo);
        $headers = $this->buildGatewayHeaders($c['username'], $c['password'], $c['gstin'], $plant);

        $response = Http::withHeaders($headers)->timeout(30)->get($url);
        return $response->json() ?? [];
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