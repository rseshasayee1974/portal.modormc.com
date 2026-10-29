<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:22:49 Asia/Calcutta (UTC+05:30)
*/

namespace App\Services\Logistics;

use App\Helpers\DateTimeHelper;
use App\Mail\MachineDocumentExpiryMail;
use App\Models\{Machine, NotificationEmail, Plant};
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{Cache, DB, Log, Mail};
use Illuminate\Support\Str;

class MachineDocumentReminderService
{
    public function sendForPlant(Plant $plant, bool $dryRun = false, bool $scheduled = false): array
    {
        $result = ['documents_due' => 0, 'recipients' => 0, 'emails_due' => 0, 'emails_sent' => 0, 'errors' => 0, 'skipped' => false];
        $now = CarbonImmutable::now(DateTimeHelper::timezoneForPlant($plant->id));
        if (!config('machine_notifications.enabled') || !$plant->is_active || !$plant->entity?->is_active
            || $plant->entity?->is_suspended || ($scheduled && $now->hour !== (int) config('machine_notifications.send_hour', 9))) {
            return array_replace($result, ['skipped' => true]);
        }
        $lock = Cache::lock('machine-document-reminders:plant:'.$plant->id, (int) config('machine_notifications.lock_seconds', 3600));
        if (!$lock->get()) return array_replace($result, ['skipped' => true]);
        try {
            $documents = $this->dueDocuments($plant->id, $now->startOfDay());
            $result['documents_due'] = count($documents);
            $recipients = NotificationEmail::where('plant_id', $plant->id)
                ->where('type', config('machine_notifications.type'))->where('status', 1)->pluck('email')
                ->map(fn ($email) => strtolower(trim($email)))
                ->filter(fn ($email) => filter_var($email, FILTER_VALIDATE_EMAIL))->unique()->values();
            $result['recipients'] = $recipients->count();
            if (!$documents) return $result;
            foreach ($recipients as $email) {
                $sent = DB::table('mm_machine_document_alerts')->where('plant_id', $plant->id)->where('recipient', $email)
                    ->whereIn('machine_document_id', array_column($documents, 'document_id'))
                    ->get()->mapWithKeys(fn ($row) => [$row->machine_document_id.'|'.$row->expiry_date.'|'.$row->reminder_key => true]);
                $pending = array_values(array_filter($documents, fn ($document) =>
                    !isset($sent[$document['document_id'].'|'.$document['expiry_date'].'|'.$document['reminder_key']])));
                if (!$pending) continue;
                $result['emails_due']++;
                if ($dryRun) continue;
                try {
                    // Each configured recipient receives a private plant-specific digest.
                    Mail::to($email)->send(new MachineDocumentExpiryMail($plant->name, $now->format('d-M-Y'), $pending));
                    $timestamp = $now->utc()->toDateTimeString();
                    DB::table('mm_machine_document_alerts')->insertOrIgnore(array_map(fn ($document) => [
                        'plant_id' => $plant->id, 'machine_document_id' => $document['document_id'], 'recipient' => $email,
                        'expiry_date' => $document['expiry_date'], 'reminder_key' => $document['reminder_key'],
                        'sent_at' => $timestamp, 'created_at' => $timestamp, 'updated_at' => $timestamp,
                    ], $pending));
                    $result['emails_sent']++;
                } catch (\Throwable $exception) {
                    $result['errors']++;
                    Log::error('Machine document expiry email failed.', ['plant_id' => $plant->id, 'exception' => $exception]);
                }
            }
            return $result;
        } finally {
            $lock->release();
        }
    }

    private function dueDocuments(int $plantId, CarbonImmutable $today): array
    {
        $documents = [];
        $machines = Machine::withoutGlobalScope('plant_id')->where('plant_id', $plantId)
            ->where('is_active', true)->with(['documents' => fn ($query) => $query->whereNotNull('expiry_date')])
            ->orderBy('registration')->get();
        foreach ($machines as $machine) {
            foreach ($machine->documents as $document) {
                try {
                    $date = substr((string) $document->expiry_date, 0, 10);
                    $expiry = CarbonImmutable::createFromFormat('!Y-m-d', $date, $today->timezone);
                    if ($expiry->toDateString() !== $date) throw new \InvalidArgumentException('Invalid expiry date.');
                } catch (\Throwable) {
                    Log::warning('Machine document has an invalid expiry date.', ['document_id' => $document->id]);
                    continue;
                }
                $days = (int) $today->diffInDays($expiry, false);
                $key = $this->reminderKey($days);
                if ($key === null) continue;
                $documents[] = [
                    'document_id' => $document->id, 'registration' => $machine->registration,
                    'document_type' => $this->documentLabel($document->type), 'expiry_date' => $expiry->toDateString(),
                    'expiry_label' => $expiry->format('d-M-Y'), 'days_remaining' => $days, 'reminder_key' => $key,
                    'status' => $days < 0 ? 'Expired '.abs($days).' day(s) ago'
                        : ($days === 0 ? 'Expires today' : 'Expires in '.$days.' day(s)'),
                ];
            }
        }
        return $documents;
    }

    private function reminderKey(int $days): ?string
    {
        if ($days <= 0) {
            $week = intdiv(abs($days), max(1, (int) config('machine_notifications.overdue_interval_days', 7)));
            return $week === 0 ? 'expiry' : 'overdue_'.$week;
        }
        $thresholds = array_filter(config('machine_notifications.reminder_days', [30, 15, 7, 1, 0]), fn ($day) => $day > 0);
        sort($thresholds);
        foreach ($thresholds as $threshold) {
            // Catch up once at the current threshold if the scheduler missed its exact day.
            if ($days <= $threshold) return 'before_'.$threshold;
        }
        return null;
    }

    private function documentLabel(string $type): string
    {
        return match (strtolower(trim($type))) {
            'fc', 'fitness', 'fitness_certificate' => 'FC / Fitness Certificate',
            'road_tax' => 'Road Tax', 'puc', 'pollution' => 'PUC / Pollution Certificate',
            'permit' => 'Permit', 'national_permit' => 'National Permit',
            'insurance' => 'Insurance', default => Str::headline($type),
        };
    }
}
