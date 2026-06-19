<?php

namespace App\Jobs;

use App\Models\AlertConfiguration;
use App\Models\AlertFunnel;
use App\Models\AlertLog;
use App\Models\AlertUserNotificationLog;
use App\Notifications\CoinPerformanceAlert;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class FunnelAlertConfigurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $configId) {}

    public function handle(): void
    {
        $funnel = AlertFunnel::find($this->configId);
        $config = AlertConfiguration::with('notifyUsers')->find($funnel->alert_configuration_id);

        if (! $config || ! $config->is_active) {
            return;
        }

        try {

            $logs = [];
            $coin = $funnel->symbol;
            $url = 'https://fapi.binance.com/fapi/v1/klines';

            try {

                Log::channel('funnel_alert')->info("Coin {$coin} performance checking");

                $limit = min($config->time_duration_minutes, 1500);

                $response = Http::timeout(10)->get($url, [
                    'symbol' => strtoupper($coin),
                    'interval' => '1m',
                    'limit' => $limit,
                ]);

                if (! $response->successful()) {
                    return;
                }

                $klines = $response->json();
                $count = count($klines);

                if ($count < 2) {
                    return;
                }
                $last = $count - 1;

                $openPrice = (float) $klines[0][4]; // first close
                $lastOpenTime = (float) $klines[$last][0]; // last open time
                $lastOpenPrice = (float) $klines[$last][1]; // last open
                $lastHighPrice = (float) $klines[$last][2]; // last high
                $lastLowPrice = (float) $klines[$last][3]; // last low
                $closePrice = (float) $klines[$last][4]; // last close
                $lastVoulme = (float) $klines[$last][5]; // last volume
                Log::channel('funnel_alert')->info("Coin {$coin} open price {$openPrice} & close price {$closePrice} in last {$config->time_duration}");

                if ($openPrice <= 0) {
                    return;
                }

                // ✅ % performance
                $performance = (($closePrice - $openPrice) / $openPrice) * 100;
                $performance = round($performance, 2);
                Log::channel('funnel_alert')->info("Coin {$coin} performance - {$performance} in last {$config->time_duration}");

                $triggered = false;

                if ($config->direction === 1 && $performance >= $config->percentage) {
                    $triggered = true;
                }

                if ($config->direction === 0 && abs($performance) >= $config->percentage && $performance < 0) {
                    $triggered = true;
                }
                Log::channel('funnel_alert')->info("Coin {$coin} price triggered - ".($triggered ? 'yes' : 'no'));

                if ($triggered) {

                    $zScore1d = null;
                    $zScore2d = null;
                    $zScore3d = null;

                    $zResponse = Http::timeout(10)->get($url, [
                        'symbol' => strtoupper($coin),
                        'interval' => '4h',
                        'limit' => 18,
                    ]);

                    if ($zResponse->successful()) {

                        $zKlines = $zResponse->json();

                        $closes = collect($zKlines)
                            ->pluck(4)
                            ->map(fn ($price) => (float) $price)
                            ->values()
                            ->toArray();

                        if (count($closes) >= 6) {
                            $zScore1d = $this->calculateZScore(array_slice($closes, -6));
                        }

                        if (count($closes) >= 12) {
                            $zScore2d = $this->calculateZScore(array_slice($closes, -12));
                        }

                        if (count($closes) >= 18) {
                            $zScore3d = $this->calculateZScore(array_slice($closes, -18));
                        }
                    }

                    $momentum = $this->calculateMomentum($klines);

                    $candle = match (true) {
                        $lastOpenPrice > $closePrice => 1,
                        $lastOpenPrice < $closePrice => 0,
                        default => null,
                    };

                    $existingLog = AlertLog::selectRaw('MAX(high) as max_high, MIN(low) as min_low')
                        ->where('alert_configuration_id', $config->id)
                        ->where('symbol', $coin)
                        ->where('source_job', 'funnel_alert')
                        ->first();

                    $alertLog = AlertLog::create([
                        'alert_configuration_id' => $config->id,
                        'parent_id' => $funnel->parent_id,
                        'symbol' => $coin,
                        'performance' => $performance,
                        'price_from' => $openPrice,
                        'price_to' => $closePrice,
                        'high' => $lastHighPrice,
                        'low' => $lastLowPrice,
                        'high' => max($lastHighPrice, (float) ($existingLog->max_high ?? $lastHighPrice)),
                        'low' => min($lastLowPrice, (float) ($existingLog->min_low ?? $lastLowPrice)),
                        'volume' => $lastVoulme,
                        'z_score_1d' => $zScore1d,
                        'z_score_2d' => $zScore2d,
                        'z_score_3d' => $zScore3d,
                        'r3' => $momentum['r3'],
                        'r5' => $momentum['r5'],
                        'r15' => $momentum['r15'],
                        'funnel_id' => $funnel->funnel_id,
                        'source_job' => 'funnel_alert',
                        'candle' => $candle,
                        'open_time' => $lastOpenTime,
                    ]);

                    $logs[] = $alertLog;
                } else {
                    $funnel->delete();
                }

            } catch (\Exception $error) {

                logger()->error('Coin performance error', [
                    'coin' => $coin,
                    'error' => $error->getMessage(),
                ]);
            }

            /**
             * ============================
             * SEND NOTIFICATION TO USERS
             * ============================
             */

            //            if (! empty($logs) && $config->notifyUsers->isNotEmpty()) {
            //
            //                foreach ($config->notifyUsers as $user) {
            //
            //                    try {
            //
            //                        $user->notify(new CoinPerformanceAlert($logs));
            //
            //                        foreach ($logs as $log) {
            //
            //                            AlertUserNotificationLog::create([
            //                                'alert_log_id' => $log->id,
            //                                'user_id' => $user->id,
            //                                'email_sent' => 1,
            //                                'sent_at' => now()
            //                            ]);
            //                        }
            //
            //                    } catch (\Exception $mailError) {
            //
            //                        Log::channel('funnel_alert')->error("Mail failed", [
            //                            'user_id' => $user->id,
            //                            'error' => $mailError->getMessage()
            //                        ]);
            //                    }
            //                }
            //
            //                Log::channel('funnel_alert')->info("Config ID {$config->id} notifications sent");
            //            }

        } finally {

            $config->update([
                'last_run_at' => now(),
                'is_running' => 0,
            ]);
        }
    }

    private function calculateZScore(array $prices): ?float
    {
        $count = count($prices);

        if ($count < 2) {
            return null;
        }

        $mean = array_sum($prices) / $count;

        $variance = 0;

        foreach ($prices as $price) {
            $variance += pow($price - $mean, 2);
        }

        $variance /= $count;

        $stdDev = sqrt($variance);

        if ($stdDev == 0) {
            return null;
        }

        $current = end($prices);

        return round(($current - $mean) / $stdDev, 4);
    }

    private function calculateMomentum(array $klines): array
    {
        $closes = collect($klines)
            ->pluck(4)
            ->map(fn ($price) => (float) $price)
            ->values()
            ->toArray();

        $current = end($closes);

        $r3 = null;
        $r5 = null;
        $r15 = null;

        if (count($closes) >= 4) {
            $price3 = $closes[count($closes) - 4];
            $r3 = round((($current - $price3) / $price3) * 100, 2);
        }

        if (count($closes) >= 6) {
            $price5 = $closes[count($closes) - 6];
            $r5 = round((($current - $price5) / $price5) * 100, 2);
        }

        if (count($closes) >= 16) {
            $price15 = $closes[count($closes) - 16];
            $r15 = round((($current - $price15) / $price15) * 100, 2);
        }

        return [
            'r3' => $r3,
            'r5' => $r5,
            'r15' => $r15,
        ];
    }

    /**
     * Handle a job failure.
     */
    public function failed(Throwable $exception): void
    {
        AlertConfiguration::where('id', $this->configId)->update(['is_running' => 0]);
    }
}
