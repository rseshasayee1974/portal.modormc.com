<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class MonitorWebsite extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'website:monitor {url=modomines.com} {--plant= : Limit alerts to one active plant ID}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor a website and send email alerts if it goes down';

    /**
     * Execute the console command.
     */
    public function handle(\App\Services\PlantAlertRecipients $recipientResolver)
    {
        if ($this->option('plant') !== null && (!ctype_digit((string) $this->option('plant')) || (int) $this->option('plant') < 1)) {
            $this->error('The plant option must be a positive plant ID.');
            return self::FAILURE;
        }
        $plants = \App\Models\Plant::where('is_active', true)
            ->when($this->option('plant'), fn ($query, $id) => $query->whereKey($id))->get();
        if ($this->option('plant') && $plants->isEmpty()) {
            $this->error('The selected plant is not active or available.');
            return self::FAILURE;
        }
        $url = $this->argument('url');
        $cleanUrl = str_replace(['http://', 'https://'], '', $url);
        $fullUrl = 'https://' . $cleanUrl;

        $this->info("Checking status for {$fullUrl}...");

        $isUp = false;
        try {
            $response = \Illuminate\Support\Facades\Http::timeout(10)->head($fullUrl);
            if ($response->successful()) {
                $isUp = true;
            }
        } catch (\Exception $e) {
            $this->error("Error checking website: " . $e->getMessage());
            $isUp = false;
        }

        $statusText = $isUp ? 'UP' : 'DOWN';
        foreach ($plants as $plant) {
            $cacheKey = 'website_status_' . md5($cleanUrl) . ':plant:' . $plant->id;
            $lastStatus = \Illuminate\Support\Facades\Cache::get($cacheKey);
            if ($lastStatus === $statusText) continue;

            $recipients = $recipientResolver->forPlant($plant);
            foreach ($recipients as $email) {
                // Fresh mailable per recipient prevents addresses accumulating in To.
                \Illuminate\Support\Facades\Mail::to($email)->send(
                    new \App\Mail\WebsiteStatusAlert($cleanUrl, $statusText, $plant->name)
                );
            }
            \Illuminate\Support\Facades\Cache::put($cacheKey, $statusText);
            $this->info('Plant ' . $plant->id . ': ' . $recipients->count() . ' separate alert(s) sent.');
        }

        return 0;
    }
}
