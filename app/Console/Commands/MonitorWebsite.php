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
    protected $signature = 'website:monitor {url=modomines.com}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor a website and send email alerts if it goes down';

    /**
     * Execute the console command.
     */
    public function handle()
    {
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
        $cacheKey = "website_status_" . md5($cleanUrl);
        $lastStatus = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if ($isUp) {
            $this->info("Website is UP.");
        } else {
            $this->warn("Website is DOWN!");
        }

        // Only send email if status has changed
        if ($lastStatus !== $statusText) {
            $this->info("Status changed from " . ($lastStatus ?? 'UNKNOWN') . " to {$statusText}. Sending alert...");
            
            // ── 1. Get all email addresses from the Plants table ──────────
            $plantEmails = \App\Models\Plant::where('is_active', true)
                ->whereNotNull('email_address')
                ->pluck('email_address')
                ->toArray();

            // ── 2. Get all users who have "Admin" roles ──────────────────
            // Based on mm_roles table: 2=PLATFORM_ADMIN, 3=SUPER_ADMIN, 4=ADMINISTRATOR
            $adminUserEmails = \App\Models\User::whereHas('entityUsers', function($query) {
                $query->whereIn('role_id', [2, 3, 4]);
            })->pluck('email')->toArray();

            // ── 3. Merge and unique recipients ───────────────────────────
            $recipients = array_unique(array_merge($plantEmails, $adminUserEmails));
            
            if (empty($recipients)) {
                $this->warn("No recipients found (no plant emails or admin users). Sending to default...");
                $recipients = ['ragul@onemodo.com'];
            }

            $this->info("Sending alert to: " . implode(', ', $recipients));
            
            \Illuminate\Support\Facades\Mail::to($recipients)->send(new \App\Mail\WebsiteStatusAlert($cleanUrl, $statusText));
            
            \Illuminate\Support\Facades\Cache::put($cacheKey, $statusText);
            $this->info("Alert sent successfully.");
        } else {
            $this->line("No status change detected. No email sent.");
        }

        return 0;
    }
}
