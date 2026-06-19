<?php

namespace App\Jobs;

use App\Models\AlertConfiguration;
use App\Models\AlertFunnel;
use App\Models\AlertLog;
use App\Models\AlertUserNotificationLog;
use App\Notifications\CoinPerformanceAlert;
use App\Services\FcmNotificationService;
use App\Services\GeneralService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessAlertConfigurationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $configId) {}

    public function handle(): void
    {
        $config = AlertConfiguration::with('notifyUsers')->find($this->configId);

        if (! $config || ! $config->is_active) {
            return;
        }

        try {
            $logs = [];
            $configSymbols = $config->symbols ?? [];
            $funnelSymbols = \App\Models\AlertFunnel::where(
                'alert_configuration_id',
                $config->id
            )->pluck('symbol')->toArray();
            // Step 1: Fetch all 24h ticker data
            $tickerResponse = Http::timeout(10)->get('https://fapi.binance.com/fapi/v1/ticker/24hr');

            if (! $tickerResponse->successful()) {
                Log::error('Failed to fetch 24hr ticker');

                return;
            }

            $tickers = collect($tickerResponse->json());

            // Step 2: Calculate threshold (40% of config percentage)
            $threshold = $config->percentage * 0.4;

            // Step 3: Filter coins
            $filteredCoins = $tickers->filter(function ($item) use ($config, $threshold, $configSymbols, $funnelSymbols) {

                $symbol = $item['symbol'];
                $change = (float) $item['priceChangePercent'];

                // Must be in config symbols
                if (! in_array($symbol, $configSymbols)) {
                    return false;
                }

                // Exclude funnel symbols
                if (in_array($symbol, $funnelSymbols)) {
                    return false;
                }

                // UP condition
                if ($config->direction === 1) {
                    return $change >= $threshold;
                }

                // DOWN condition
                if ($config->direction === 0) {
                    return $change <= -$threshold;
                }

                return false;
            });

            // Step 4: Extract symbols
            $coins = $filteredCoins->pluck('symbol')->values()->toArray();

            //            Log::channel('price_alert')->info("Coin List :  {count($coins)}");

            //            $coins = array_values(array_diff($configSymbols, $funnelSymbols));
            $url = 'https://fapi.binance.com/fapi/v1/klines';

            foreach ($coins as $coin) {

                try {

                    Log::channel('price_alert')->info("Coin {$coin} performance checking");

                    $limit = min($config->time_duration_minutes, 1500);

                    $response = Http::timeout(10)->get($url, [
                        'symbol' => strtoupper($coin),
                        'interval' => '1m',
                        'limit' => $limit,
                    ]);

                    if (! $response->successful()) {
                        continue;
                    }

                    $klines = $response->json();
                    $count = count($klines);

                    if ($count < 2) {
                        continue;
                    }
                    $last = $count - 1;

                    $openPrice = (float) $klines[0][4]; // first close
                    $lastOpenTime = (float) $klines[$last][0]; // last open time
                    $lastOpenPrice = (float) $klines[$last][1]; // last open
                    $lastHighPrice = (float) $klines[$last][2]; // last high
                    $lastLowPrice = (float) $klines[$last][3]; // last low
                    $closePrice = (float) $klines[$last][4]; // last close
                    $lastVoulme = (float) $klines[$last][5]; // last volume
                    Log::channel('price_alert')->info("Coin {$coin} open price {$openPrice} & close price {$closePrice} in last {$config->time_duration}");

                    if ($openPrice <= 0) {
                        continue;
                    }

                    // ✅ % performance
                    $performance = (($closePrice - $openPrice) / $openPrice) * 100;
                    $performance = round($performance, 2);
                    Log::channel('price_alert')->info("Coin {$coin} performance - {$performance} in last {$config->time_duration}");

                    $triggered = false;

                    if ($config->direction === 1 && $performance >= $config->percentage) {
                        $triggered = true;
                    }

                    if ($config->direction === 0 && abs($performance) >= $config->percentage && $performance < 0) {
                        $triggered = true;
                    }
                    Log::channel('price_alert')->info("Coin {$coin} price triggered - ".($triggered ? 'yes' : 'no'));

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

                        $alertLog = AlertLog::create([
                            'alert_configuration_id' => $config->id,
                            'symbol' => $coin,
                            'performance' => $performance,
                            'price_from' => $openPrice,
                            'price_to' => $closePrice,
                            'high' => $lastHighPrice,
                            'low' => $lastLowPrice,
                            'volume' => $lastVoulme,
                            'z_score_1d' => $zScore1d,
                            'z_score_2d' => $zScore2d,
                            'z_score_3d' => $zScore3d,
                            'r3' => $momentum['r3'],
                            'r5' => $momentum['r5'],
                            'r15' => $momentum['r15'],
                            'candle' => $candle,
                            'open_time' => $lastOpenTime,
                        ]);

                        if ($alertLog->z_score_1d > 1.6 && $alertLog->z_score_2d > 1.6 && $alertLog->z_score_3d > 1) {
                            AlertFunnel::create([
                                'alert_configuration_id' => $config->id,
                                'parent_id' => $alertLog->id,
                                'symbol' => $coin,
                            ]);
                        }

                        $logs[] = $alertLog;
                    }

                } catch (\Exception $error) {

                    logger()->error('Coin performance error', [
                        'coin' => $coin,
                        'error' => $error->getMessage(),
                    ]);
                }
            }

            /**
             * ============================
             * SEND NOTIFICATION TO USERS
             * ============================
             */
            if (! empty($logs)) {
                $message = "`Config ID {$config->id} Coins Performance Alert`\n";
                foreach ($logs as $log) {
                    $direction = $log->performance > 0 ? 'UP' : 'DOWN';

                    $message .= "> Symbol: {$log->symbol}\n";
                    $message .= "> Move: {$log->performance}% {$direction}\n";
                    $message .= "> From -> To Price: {$log->price_from} → {$log->price_to}\n";
                    $message .= "> High / Low: {$log->high} / {$log->low}\n";
                    $message .= '> Volume: '.($log->volume ? round($log->volume, 2) : '')."\n";
                    $message .= '> Z Score 24H / 2D / 3D: '.round($log->z_score_1d, 2).' / '.round($log->z_score_2d, 2).' / '.round($log->z_score_3d, 2)."\n";
                    $message .= '> R3 / R5 / R15: '
                        .($log->r3 ? round($log->r3, 2).'%' : '-').' / '
                        .($log->r5 ? round($log->r5, 2).'%' : '-').' / '
                        .($log->r15 ? round($log->r15, 2).'%' : '-')."\n";
                    $message .= '> Candle: '.($log->candle_label ?: '')."\n";
                    $message .= "> Open Time: {$log->open_timestamp} ({$log->open_time})\n";
                    $message .= "> Triggered at: {$log->created_at}\n\n";
                }

                $generalService = app(GeneralService::class);
                $generalService->slackNotification(env('FUTURE_ALERT_WEBHOOK'), trim($message));

                if ($config->notifyUsers->isNotEmpty()) {
                    foreach ($config->notifyUsers as $user) {
                        try {
                            $user->notify(new CoinPerformanceAlert($logs));

                            foreach ($logs as $log) {
                                AlertUserNotificationLog::create([
                                    'alert_log_id' => $log->id,
                                    'user_id' => $user->id,
                                    'email_sent' => 1,
                                    'sent_at' => now(),
                                ]);
                            }

                        } catch (\Exception $mailError) {

                            Log::channel('price_alert')->error('Mail failed', [
                                'user_id' => $user->id,
                                'error' => $mailError->getMessage(),
                            ]);
                        }
                    }
                }

                // ── FCM Push Notifications ────────────────────────────────────────────
                if ($config->notifyUsers->isNotEmpty()) {
                    $fcm = app(FcmNotificationService::class);

                    $symbols  = collect($logs)->pluck('symbol')->unique()->implode(', ');
                    $coinCount = collect($logs)->count();
                    $title    = "Price Alert: {$config->id}";
                    $body     = "{$coinCount} coin(s) triggered — {$symbols}";

                    foreach ($config->notifyUsers as $user) {
                        try {
                            $fcm->sendToUser($user->id, $title, $body, [
                                'type'              => 'price_alert',
                                'alert_config_id'   => $config->id,
                                'symbols'           => $symbols,
                                'triggered_count'   => $coinCount,
                            ]);
                        } catch (\Throwable $fcmError) {
                            Log::channel('price_alert')->error('FCM push failed', [
                                'user_id' => $user->id,
                                'error'   => $fcmError->getMessage(),
                            ]);
                        }
                    }
                }
                // ─────────────────────────────────────────────────────────────────────

                Log::channel('price_alert')->info("Config ID {$config->id} notifications sent");
            }

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
