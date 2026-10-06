<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\WhatsAppMessage;
use App\Notifications\DispatchCompletedNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DispatchWhatsAppService
{
    public function send(Dispatch $dispatch): void
    {
        $lock = Cache::lock('dispatch-whatsapp:' . $dispatch->id, 60);
        abort_unless($lock->get(), 409, 'A WhatsApp request for this dispatch is already in progress.');

        try {
            if (!Schema::hasTable('mm_whatsapp_messages')) {
                $this->fail('WhatsApp submission tracking is not configured. Please contact your administrator.');
            }
            abort_if(WhatsAppMessage::where('origin', 'dispatch')->where('origin_id', $dispatch->id)
                ->where('category', 'dispatch_details')->whereIn('status', ['pending', 'submitted', 'unknown'])->exists(),
                409, 'WhatsApp details for this dispatch have already been submitted or require checking with the provider.');
            $dispatch->loadMissing('batch');
            if ($dispatch->dispatch_status === 'Cancelled' || !in_array($dispatch->batch?->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->fail('WhatsApp details can be sent only for a completed or dispatched batch.');
            }

            $contact = $dispatch->getWhatsAppContact();
            if (!$contact) $this->fail('A valid primary customer mobile number is required.');
            $license = config('services.tendigit.license_number');
            $apiKey = config('services.tendigit.api_key');
            $template = 'modormc_dispatch';
            if (!$license || !$apiKey || !$template) $this->fail('Tendigit WhatsApp credentials and dispatch template must be configured.');

            $values = (new DispatchCompletedNotification($dispatch))->toWhatsAppTemplateParameters();
            // Tendigit uses commas as positional separators; commas inside addresses/names
            // must not shift the approved template's fourteen parameters.
            $values = array_map(static fn ($value) => trim(preg_replace('/\s+/u', ' ', str_replace(',', ';', (string) $value))), $values);
            $record = WhatsAppMessage::create([
                'entity_id' => session('active_entity_id') ?? session('entity_id'),
                'plant_id' => $dispatch->plant_id,
                'category' => 'dispatch_details', 'origin' => 'dispatch', 'origin_id' => $dispatch->id,
                'provider' => 'tendigit', 'contact' => $contact, 'template' => $template,
                'parameters' => $values, 'status' => 'pending',
                'created_by' => auth()->id(), 'updated_by' => auth()->id(),
            ]);

            try {
                // Do not retry a sending request: the provider may accept it before a timeout.
                $response = Http::connectTimeout(10)->timeout(30)->get('https://app.tendigit.in/api/sendtemplate.php', [
                    'LicenseNumber' => $license,
                    'APIKey' => $apiKey,
                    'Contact' => $contact,
                    'Template' => $template,
                    'Param' => implode(',', $values),
                ]);
            } catch (ConnectionException $e) {
                $message = 'Tendigit could not confirm the request. Check the provider before trying again.';
                $record->update(['status' => 'unknown', 'error_message' => $message, 'updated_by' => auth()->id()]);
                $this->fail($message);
            }

            // Keep the provider result for audit without storing API credentials.
            $record->update([
                'response_status' => $response->status(),
                'provider_response' => str_replace([(string) $apiKey, (string) $license], '[redacted]', $response->body()),
                'updated_by' => auth()->id(),
            ]);

            if (!$response->successful() || trim($response->body()) === '') {
                $this->reject($record, 'Tendigit did not accept the WhatsApp request. Check the provider configuration.');
            }
            $body = $response->json();
            $status = is_array($body) ? ($body['status'] ?? null) : null;
            $error = is_array($body) ? ($body['error'] ?? null) : null;
            $code = is_array($body) ? ($body['code'] ?? $body['Code'] ?? null) : null;
            $message = is_array($body) ? ($body['message'] ?? $body['Message'] ?? $body['response'] ?? '') : $response->body();
            $rejected = $status === false || in_array(strtolower((string) $status), ['error', 'failed', 'failure', 'false'])
                || !in_array($error, [null, false, 0, '', 'false', '0'], true)
                || (is_numeric($code) && ((int) $code >= 400 || (int) $code < 0))
                || (is_string($message) && preg_match('/\b(error|failed|failure|invalid|insufficient|unauthorized|unauthorised)\b/i', strip_tags($message)))
                || preg_match('/<(html|!doctype)\b/i', $response->body());
            if ($rejected) {
                $this->reject($record, 'Tendigit rejected the WhatsApp request. Check the template, recipient and API configuration.');
            }

            // Track API submission; final delivery is controlled by the WhatsApp provider.
            $record->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_by' => auth()->id()]);
        } finally {
            $lock->release();
        }
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['whatsapp' => $message]);
    }

    private function reject(WhatsAppMessage $record, string $message): never
    {
        $record->update(['status' => 'failed', 'error_message' => $message, 'updated_by' => auth()->id()]);
        $this->fail($message);
    }
}
