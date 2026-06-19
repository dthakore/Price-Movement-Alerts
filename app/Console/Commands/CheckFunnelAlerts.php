<?php

namespace App\Console\Commands;

use App\Jobs\FunnelAlertConfigurationJob;
use App\Models\AlertFunnel;
use App\Models\CronJobLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckFunnelAlerts extends Command
{
    protected $signature = 'funnel_alert:check';

    protected $description = 'Check price alert configurations for funnel coins';

    public function handle(): int
    {
        $cronLog = CronJobLog::create([
            'cron_job'   => $this->signature,
            'status'     => 'running',
            'start_time' => now(),
        ]);

        try {
            $configs = AlertFunnel::get();

            $alertsDispatched = 0;

            if ($configs->isNotEmpty()) {
                foreach ($configs as $config) {
                    Log::channel('funnel_alert')->info("Config ID {$config->id} price alert checking...");

                    dispatch(new FunnelAlertConfigurationJob($config->id))->onQueue('funnel_alert');
                    $alertsDispatched++;
                }
            }

            $cronLog->update([
                'status'           => 'completed',
                'end_time'         => now(),
                'alerts_processed' => $alertsDispatched,
            ]);

            return Command::SUCCESS;
        } catch (\Exception $error) {
            $cronLog->update([
                'status'   => 'failed',
                'end_time' => now(),
                'error'    => $error->getMessage(),
            ]);

            logger()->error('Cron funnel_alert:check error', ['error' => $error->getMessage()]);

            return Command::FAILURE;
        }
    }
}
