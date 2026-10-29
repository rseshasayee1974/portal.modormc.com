<?php

/*
Author: ragul-onemodo
Created: 2026-10-29 14:22:50 Asia/Calcutta (UTC+05:30)
*/

namespace App\Console\Commands;

use App\Models\Plant;
use App\Services\Logistics\MachineDocumentReminderService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class SendMachineDocumentExpiryReminders extends Command
{
    protected $signature = 'fleet:notify-document-expiry
        {--plant= : Limit to an active plant ID}
        {--dry-run : Preview due notifications without sending mail or recording deliveries}
        {--scheduled : Send only during the configured hour in each plant entity timezone}';

    protected $description = 'Email plant recipients about machine FC, permit, road tax, insurance and other document expiry dates.';

    public function handle(MachineDocumentReminderService $service): int
    {
        if (!config('machine_notifications.enabled')) {
            $this->info('Machine document expiry notifications are disabled.');
            return self::SUCCESS;
        }
        if ($this->option('plant') !== null && (!ctype_digit((string) $this->option('plant')) || (int) $this->option('plant') < 1)) {
            $this->error('The plant option must be a positive plant ID.');
            return self::FAILURE;
        }
        $plants = Plant::where('is_active', 1)->whereHas('entity', fn ($query) => $query->where('is_active', 1)->where('is_suspended', 0))
            ->when($this->option('plant'), fn ($query, $id) => $query->whereKey($id))->with('entity')->get();
        if ($this->option('plant') && $plants->isEmpty()) {
            $this->error('The selected plant is not active or available.');
            return self::FAILURE;
        }
        $rows = [];
        $failed = false;
        foreach ($plants as $plant) {
            try {
                $result = $service->sendForPlant($plant, (bool) $this->option('dry-run'), (bool) $this->option('scheduled'));
                if ($result['skipped']) continue;
                $rows[] = [$plant->id, $plant->name, $result['documents_due'], $result['recipients'], $result['emails_due'], $result['emails_sent'], $result['errors']];
                $failed = $failed || $result['errors'] > 0;
            } catch (\Throwable $exception) {
                $failed = true;
                Log::error('Machine expiry scan failed.', ['plant_id' => $plant->id, 'exception' => $exception]);
                $this->error('Machine expiry scan failed for plant '.$plant->id.'. Check the application log.');
            }
        }
        $this->table(['Plant ID', 'Plant', 'Documents Due', 'Recipients', 'Emails Due', 'Emails Sent', 'Failed'], $rows);
        if ($this->option('dry-run')) $this->info('Dry run only: no emails sent and no delivery records written.');
        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
