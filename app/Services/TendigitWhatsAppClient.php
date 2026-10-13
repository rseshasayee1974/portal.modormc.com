<?php
/*
Author: ragul-onemodo
Created: 2026-10-12 13:46:15 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TendigitWhatsAppClient
{
    public function sendTemplate(string $contact, string $template, array $parameters): Response
    {
        $license = config('services.tendigit.license_number');
        $apiKey = config('services.tendigit.api_key');
        $endpoint = config('services.tendigit.endpoint', 'https://login.tendigit.in/api/sendtemplate.php');
        $values=array_map(static fn($value)=>trim(preg_replace('/\s+/u',' ',str_replace(',',';',(string)$value))),$parameters);
        // One provider request; never retry uncertain submissions or log credentials/OTP values.
        return Http::connectTimeout(10)->timeout(30)->get($endpoint,[
            'LicenseNumber'=>$license,'APIKey'=>$apiKey,
            'Contact'=>preg_replace('/[^0-9]/','',$contact),
            'Template'=>$template,'Param'=>implode(',',$values),
        ]);
    }

    public function isCertificateFailure(ConnectionException $exception): bool
    {
        // Certificate verification fails before the HTTP request can be submitted.
        for ($cause = $exception; $cause !== null; $cause = $cause->getPrevious()) {
            if (method_exists($cause, 'getHandlerContext')) {
                $context = $cause->getHandlerContext();
                if (in_array((int) ($context['errno'] ?? 0), [51, 60, 77, 90, 91], true)) return true;
            }
            if (preg_match('/cURL error (51|60|77|90|91)\b/i', $cause->getMessage())) return true;
        }
        return false;
    }

    public function isAccepted(Response $response): bool
    {
        if (!$response->successful() || trim($response->body())==='') return false;
        $body=$response->json();
        $status=is_array($body)?($body['status']??$body['success']??null):null;
        $error=is_array($body)?($body['error']??null):null;
        $code=is_array($body)?($body['code']??$body['Code']??null):null;
        $message=is_array($body)?($body['message']??$body['Message']??$body['response']??''):$response->body();
        return !($status===false
            || in_array(strtolower((string)$status),['error','failed','failure','false'],true)
            || !in_array($error,[null,false,0,'','false','0'],true)
            || (is_numeric($code) && ((int)$code>=400 || (int)$code<0))
            || (is_string($message) && preg_match('/\b(error|failed|failure|invalid|insufficient|unauthorized|unauthorised)\b/i',strip_tags($message)))
            || preg_match('/<(html|!doctype)\b/i',$response->body()));
    }
}