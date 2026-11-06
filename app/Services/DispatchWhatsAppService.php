<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\Dispatch;
use App\Models\WhatsAppMessage;
use App\Notifications\DispatchCompletedNotification;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class DispatchWhatsAppService
{
    private TendigitWhatsAppClient $client;

    public function __construct(?TendigitWhatsAppClient $client = null)
    {
        $this->client = $client ?? new TendigitWhatsAppClient;
    }

    public function send(Dispatch $dispatch): void
    {
        $lock = Cache::lock('dispatch-whatsapp:' . $dispatch->id, 3600);
        abort_unless($lock->get(), 409, 'A WhatsApp request for this dispatch is already in progress.');

        try {
            if (!Schema::hasTable('mm_whatsapp_messages')) {
                $this->fail('WhatsApp submission tracking is not configured. Please contact your administrator.');
            }
            $dispatch->loadMissing('batch');
            if ($dispatch->dispatch_status === 'Cancelled' || !in_array($dispatch->batch?->status, [Batch::STATUS_DISPATCHED, Batch::STATUS_COMPLETED])) {
                $this->fail('WhatsApp details can be sent only for a completed or dispatched batch.');
            }

            $configured = Schema::hasTable('mm_notification_whatsapps') && \App\Models\NotificationWhatsapp::where('plant_id', $dispatch->plant_id)->where('type', 'Dispatch')->exists();
            $contacts = $configured ? app(WhatsappRecipients::class)->forPlant((int) $dispatch->plant_id, 'Dispatch')
                : collect([$dispatch->getWhatsAppContact()])->filter();
            if ($contacts->isEmpty()) $this->fail($configured ? 'No active WhatsApp recipients with valid mobile numbers are configured for this plant.' : 'A valid primary customer mobile number is required.');
            $license = config('services.tendigit.license_number');
            $apiKey = config('services.tendigit.api_key');
            $template = 'modormc_dispatch_v1';
            if (!$license || !$apiKey) $this->fail('Tendigit WhatsApp credentials must be configured.');

            $values = (new DispatchCompletedNotification($dispatch))->toWhatsAppTemplateParameters();
            // Tendigit uses commas as positional separators; commas inside addresses/names
            // must not shift the approved template's fourteen parameters.
            $values = array_map(static fn ($value) => trim(preg_replace('/\s+/u', ' ', str_replace(',', ';', (string) $value))), $values);
            $failed = 0;
            foreach ($contacts as $contact) {
                // Duplicate protection is per recipient, including uncertain provider submissions.
                $previous = WhatsAppMessage::where('plant_id', $dispatch->plant_id)->where('origin', 'dispatch')->where('origin_id', $dispatch->id)
                    ->where('category', 'dispatch_details')->where('contact', $contact)->whereIn('status', ['pending', 'submitted', 'unknown'])->pluck('status');
                if ($previous->isNotEmpty()) {
                    if (!$previous->contains('submitted')) $failed++;
                    continue;
                }
                try {
                    $this->sendRecipient($dispatch, $contact, $template, $values, (string) $apiKey, (string) $license);
                } catch (ValidationException $exception) {
                    $failed++;
                }
            }
            if ($failed) $this->fail($failed.' WhatsApp recipient(s) could not be submitted. Check WhatsApp message tracking before retrying. Other recipients were processed separately.');
        } finally {
            $lock->release();
        }
    }

    private function sendRecipient(Dispatch $dispatch, string $contact, string $template, array $values, string $apiKey, string $license): void
    {
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
            $response = $this->client->sendTemplate($contact, $template, $values);
        } catch (ConnectionException $e) {
            if ($this->client->isCertificateFailure($e)) {
                $this->reject($record, 'Tendigit HTTPS certificate verification failed. No WhatsApp request was submitted. Ask Tendigit to repair the endpoint certificate, then try again.');
            }
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
        if (!$this->client->isAccepted($response)) {
            $this->reject($record, 'Tendigit rejected the WhatsApp request. Check the template, recipient and API configuration.');
        }

        // Track API submission; final delivery is controlled by the WhatsApp provider.
        $record->update(['status' => 'submitted', 'submitted_at' => now(), 'updated_by' => auth()->id()]);
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
