<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ReportSchedule;
use App\Models\Plant;
use App\Models\User;
use App\Services\Reports\ReportServiceFactory;
use App\Services\Reports\ExcelExportService;
use App\Mail\ScheduledReportMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class SendScheduledReports extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'reports:send-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Process and send scheduled compliance/financial reports via email';

    /**
     * Execute the console command.
     */
    public function handle(ReportServiceFactory $factory, ExcelExportService $excelService)
    {
        $this->info("Checking for scheduled reports due for execution...");

        $now = Carbon::now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // Fetch active report schedules
        $schedules = ReportSchedule::where('is_active', true)->get();

        if ($schedules->isEmpty()) {
            $this->info("No active report schedules found.");
            return Command::SUCCESS;
        }

        $executedCount = 0;

        foreach ($schedules as $schedule) {
            $this->info("Processing Schedule ID: {$schedule->id} (Type: {$schedule->report_type}, Frequency: {$schedule->frequency})");

            // 1. Time & Frequency Check
            $isDue = false;

            if ($currentTime >= $schedule->schedule_time) {
                if (empty($schedule->last_run_at)) {
                    $isDue = true;
                } else {
                    $lastRun = Carbon::parse($schedule->last_run_at);
                    if ($schedule->frequency === 'daily') {
                        $isDue = $lastRun->isBefore($now->copy()->startOfDay());
                    } elseif ($schedule->frequency === 'weekly') {
                        $isDue = $lastRun->diffInDays($now) >= 7;
                    } elseif ($schedule->frequency === 'monthly') {
                        $isDue = $lastRun->diffInDays($now) >= 28;
                    }
                }
            }

            if (!$isDue) {
                $this->line("Schedule is not due yet. Skipping.");
                continue;
            }

            $this->info("Schedule is due. Generating report...");

            // 2. Determine Date Range based on frequency
            $startDate = null;
            $endDate = null;

            if ($schedule->frequency === 'daily') {
                // Previous day
                $startDate = $now->copy()->subDay()->toDateString();
                $endDate = $now->copy()->subDay()->toDateString();
            } elseif ($schedule->frequency === 'weekly') {
                // Previous 7 days
                $startDate = $now->copy()->subDays(7)->toDateString();
                $endDate = $now->copy()->subDay()->toDateString();
            } elseif ($schedule->frequency === 'monthly') {
                // Previous calendar month
                $startDate = $now->copy()->subMonth()->startOfMonth()->toDateString();
                $endDate = $now->copy()->subMonth()->endOfMonth()->toDateString();
            }

            // 3. Inject Context: Active Plant & Authenticated User
            Session::put('active_plant_id', $schedule->plant_id);
            $plant = Plant::find($schedule->plant_id);
            if ($plant) {
                Session::put('active_entity_id', $plant->entity_id);
            }

            // Authenticate a fallback user for auditing fields if none logged in
            $user = User::where('default_plant_id', $schedule->plant_id)->first() ?? User::first();
            if ($user) {
                Auth::login($user);
            }

            // 4. Generate Report Data
            try {
                $service = $factory->make($schedule->report_type);
                
                $params = $schedule->report_params ?? [];
                $params['start'] = $startDate;
                $params['end'] = $endDate;
                $params['plant_id'] = $schedule->plant_id;

                $data = $service->generate($params);
                
                // 5. Render Excel Spreadsheet
                $spreadsheet = $excelService->generateExcelReport($schedule->report_type, $startDate, $endDate, $data);

                // Buffer output
                $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
                ob_start();
                $writer->save('php://output');
                $attachmentData = ob_get_clean();

                $attachmentName = sprintf(
                    'Report_%s_%s_to_%s.xlsx',
                    $schedule->report_type,
                    $startDate,
                    $endDate
                );

                // 6. Deliver Emails
                $emails = array_map('trim', explode(',', $schedule->email_recipients));
                $plantName = $plant ? $plant->name : 'Plant';

                Mail::to($emails)->send(new ScheduledReportMail(
                    $schedule->report_type,
                    $plantName,
                    $schedule->frequency,
                    "{$startDate} to {$endDate}",
                    $attachmentData,
                    $attachmentName
                ));

                // 7. Update Schedule details
                $schedule->update([
                    'last_run_at' => now(),
                ]);

                $this->info("Successfully sent scheduled report to: " . implode(', ', $emails));
                $executedCount++;

            } catch (\Exception $e) {
                $this->error("Failed executing schedule ID {$schedule->id}: " . $e->getMessage());
                logger()->error("Scheduled Report Execution Error (ID: {$schedule->id}): " . $e->getMessage(), [
                    'exception' => $e
                ]);
            } finally {
                // Clear logged in session after schedule processing
                Auth::logout();
            }
        }

        $this->info("Completed processing scheduled reports. Executed count: {$executedCount}.");

        return Command::SUCCESS;
    }
}
