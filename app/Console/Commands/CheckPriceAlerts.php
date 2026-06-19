<?php

namespace App\Console\Commands;

use App\Jobs\ProcessAlertConfigurationJob;
use App\Models\AlertConfiguration;
use App\Models\CronJobLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPriceAlerts extends Command
{
    protected $signature = 'alerts:check';

    protected $description = 'Check price alert configurations';

    public function handle(): int
    {
        $cronLog = CronJobLog::create([
            'cron_job'   => $this->signature,
            'status'     => 'running',
            'start_time' => now(),
        ]);

        try {
            AlertConfiguration::where('is_running', 1)
                ->where('last_run_at', '<', now()->subHour())
                ->update(['is_running' => 0]);

            $configs = AlertConfiguration::where('is_active', 1)
                ->where('is_running', 0)
                ->get();

            $alertsDispatched = 0;

            if ($configs->isNotEmpty()) {
                foreach ($configs as $config) {
                    if (
                        $config->last_run_at &&
                        now()->diffInMinutes(Carbon::parse($config->last_run_at)) < $config->frequency_minutes
                    ) {
                        continue;
                    }

                    $locked = AlertConfiguration::where('id', $config->id)
                        ->where('is_running', 0)
                        ->update(['is_running' => 1]);

                    if (! $locked) {
                        if (app()->isLocal()) {
                            $this->info("Config ID {$config->id} is already running");
                        }
                        continue;
                    }

                    $config->refresh();

                    Log::channel('price_alert')->info("Config ID {$config->id} price alert checking...");

                    dispatch(new ProcessAlertConfigurationJob($config->id))->onQueue('price_alert');
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

            logger()->error('Cron alerts:check error', ['error' => $error->getMessage()]);

            return Command::FAILURE;
        }
    }
}
