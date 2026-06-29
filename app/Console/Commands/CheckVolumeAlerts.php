<?php

namespace App\Console\Commands;

use App\Models\Symbol;
use App\Models\VolumeAlert;
use App\Models\VolumeFunnel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CheckVolumeAlerts extends Command
{
    protected $signature   = 'volume:alerts';
    protected $description = 'Check 15m volume spikes across Binance symbols and record alerts';

    private const KLINES_URL     = 'https://fapi.binance.com/fapi/v1/klines';
    private const CANDLE_LIMIT   = 700;
    private const INTERVAL       = '15m';
    private const SPIKE_MULTIPLE = 5;   // buy_volume / avg_volume > 5x triggers alert
    private const CANDLES_24H    = 96;  // 96 × 15 min = 24 hours

    public function handle(): int
    {
        try {
            $symbols = Symbol::where('exchange_id', 1)->orderBy('id')->pluck('symbol');

            // Symbols already in the funnel are skipped until cleared
//            $funnelSymbols = VolumeFunnel::pluck('symbol')->flip();

            $triggered = 0;

            foreach ($symbols as $symbol) {

//                if (isset($funnelSymbols[$symbol])) {
//                    Log::channel('volume_alerts')->info("[$symbol] Skipped — already in funnel");
//                    continue;
//                }

                try {
                    $klines = $this->fetchKlines($symbol);
                } catch (\Exception $e) {
                    Log::channel('volume_alerts')->error("[$symbol] Klines fetch failed: " . $e->getMessage());
                    continue;
                }

                $count = count($klines);

                // Need at least CANDLES_24H + 2 candles to have a valid hero & 24h-ago candle
                if ($count < self::CANDLES_24H + 2) {
                    Log::channel('volume_alerts')->warning("[$symbol] Not enough candles ($count), skipping");
                    continue;
                }

                // ── Candle assignments ─────────────────────────────────
                // Last candle  (still forming) → ignored
                // Hero candle  → 2nd last (index count-2)
                // 24h-ago candle → 96 positions before hero (index count-2-96)
                // Avg candles  → 0 … count-3  (all except hero and last)
                // ──────────────────────────────────────────────────────

                $heroIndex       = $count - 2;
                $hero            = $klines[$heroIndex];
                $before24hCandle = $klines[$heroIndex - self::CANDLES_24H];

                $heroBuyVolume  = (float) $hero[9];   // taker buy base asset volume
                $heroTotalVol   = (float) $hero[5];
                $heroTrades     = (int)   $hero[8];

                // ── 24h range % (last 96 × 15m candles ending at hero) ────────────
                // range_pct = (high_24h - low_24h) / low_24h * 100
                // If > 20 % the coin already had volatility today → skip
                $rangeStart   = max(0, $heroIndex - self::CANDLES_24H + 1);
                $high24h      = -INF;
                $low24h       = INF;
                for ($i = $rangeStart; $i <= $heroIndex; $i++) {
                    $high24h = max($high24h, (float) $klines[$i][2]);
                    $low24h  = min($low24h,  (float) $klines[$i][3]);
                }
                $rangePct = $low24h > 0 ? round(($high24h - $low24h) / $low24h * 100, 4) : null;
                Log::channel('volume_alerts')->info(
                    "[$symbol] 24h range {$rangePct}%"
                );
                if ($rangePct !== null && $rangePct > 20) {
                    Log::channel('volume_alerts')->info(
                        "[$symbol] Skipped — 24h range {$rangePct}% > 20% (already volatile)"
                    );
                    continue;
                }
                // ─────────────────────────────────────────────────────────────────

                // ✅ Collect raw buy volumes up to hero candle (before any sort/cutout)
                $buyVolumes = [];

                for ($i = 0; $i <= $heroIndex; $i++) {
                    $buyVolumes[] = (float) $klines[$i][9];
                }

                // ✅ Last 10 candles Z-score (ending at hero, raw values)
                $zScore10 = null;
                if (count($buyVolumes) >= 10) {
                    $zScore10 = $this->calculateZScore(array_slice($buyVolumes, -10));
                    Log::channel('volume_alerts')->info("[$symbol] Z-score last 10 candles: {$zScore10}");
                }

                // ✅ Last 192 candles Z-score (ending at hero, raw values)
                $zScore192 = null;
                if (count($buyVolumes) >= 192) {
                    $zScore192 = $this->calculateZScore(array_slice($buyVolumes, -192));
                    Log::channel('volume_alerts')->info("[$symbol] Z-score last 192 candles: {$zScore192}");
                }

                // Avg buy volume from candles 0 … heroIndex-1 (excludes hero & last)
                $avgVolume = $this->computeAvgBuyVolume($klines, 0, $heroIndex - 1);

                if ($avgVolume <= 0) {
                    Log::channel('volume_alerts')->warning("[$symbol] Avg volume is 0, skipping");
                    continue;
                }

                $volumeMoment = $heroBuyVolume / $avgVolume;

                Log::channel('volume_alerts')->info(
                    "[$symbol] buy_vol={$heroBuyVolume} avg_vol={$avgVolume} moment=" . round($volumeMoment, 2)
                );

                if ($volumeMoment <= self::SPIKE_MULTIPLE) {
                    continue;
                }

                // ── Collect last 5 candles (C-4 … C*) ─────────────────
                $last5 = [];
                for ($offset = 4; $offset >= 0; $offset--) {
                    $c = $klines[$heroIndex - $offset];
                    $last5[] = [
                        'open'  => (float) $c[1],
                        'high'  => (float) $c[2],
                        'low'   => (float) $c[3],
                        'close' => (float) $c[4],
                    ];
                }

                // ── Spike detected — persist ────────────────────────────
                $alert = VolumeAlert::create([
                    'symbol'               => $symbol,
                    'candle_open_time'     => (int) $hero[0],  // Unix ms timestamp from Binance klines[0]
                    'avg_volume'           => round($avgVolume, 8),
                    'current_volume'       => (float) $hero[5],
                    'buy_volume'           => $heroBuyVolume,
                    'buy_volume_percentage'=> $heroTotalVol > 0
                                                ? round($heroBuyVolume / $heroTotalVol * 100, 2)
                                                : null,
                    'z_score_last_10_candles'  => $zScore10,
                    'z_score_last_192_candles' => $zScore192,
                    'range_percentage'         => $rangePct,
                    'trades'               => $heroTrades,
                    'volume_moment'        => round($volumeMoment, 4),
                    'open_price'           => (float) $hero[1],
                    'high_price'           => (float) $hero[2],
                    'low_price'            => (float) $hero[3],
                    'close_price'          => (float) $hero[4],
                    'before_24_hours_price'=> (float) $before24hCandle[4],
                    'last_5_candles'       => $last5,
                ]);

                VolumeFunnel::create([
                    'symbol'          => $symbol,
                    'volume_alert_id' => $alert->id,
                    'funnel_step'     => 1,
                ]);

                $triggered++;

                Log::channel('volume_alerts')->info(
                    "[$symbol] ✅ Volume spike {$volumeMoment}x — alert #{$alert->id} created"
                );
            }

            Log::channel('volume_alerts')->info("volume:alerts finished — {$triggered} spike(s) detected");

            return Command::SUCCESS;

        } catch (\Exception $error) {
            Log::channel('volume_alerts')->error('volume:alerts fatal error: ' . $error->getMessage());
            logger()->error('Volume performance error', ['error' => $error->getMessage()]);

            return Command::FAILURE;
        }
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Fetch 15m klines for a symbol from Binance futures.
     */
    private function fetchKlines(string $symbol): array
    {
        $response = Http::timeout(10)->get(self::KLINES_URL, [
            'symbol'   => $symbol,
            'interval' => self::INTERVAL,
            'limit'    => self::CANDLE_LIMIT,
        ]);

        if (! $response->successful()) {
            throw new \RuntimeException("HTTP {$response->status()} for {$symbol}");
        }

        return $response->json();
    }

    /**
     * Trimmed average of taker-buy-base-asset-volume (index 9) over candles [$from … $to].
     *
     * Steps:
     *  1. Collect buy volumes and sort ascending.
     *  2. Drop bottom 10 % (70 of 698) — removes dead/illiquid periods.
     *  3. Drop top 40 % (280 of 698) — removes old pump noise.
     *  4. Average the remaining middle ~348 candles.
     */
    private function computeAvgBuyVolume(array $klines, int $from, int $to): float
    {
        $volumes = [];

        for ($i = $from; $i <= $to; $i++) {
            $volumes[] = (float) $klines[$i][9];
        }

        $total = count($volumes);

        if ($total === 0) {
            return 0.0;
        }

        sort($volumes); // ascending

        $dropBottom = (int) round($total * 0.10); // 10 % from low end
        $dropTop    = (int) round($total * 0.40); // 40 % from high end

        $middle = array_slice($volumes, $dropBottom, $total - $dropBottom - $dropTop);

        if (empty($middle)) {
            return 0.0;
        }

        return array_sum($middle) / count($middle);
    }

    private function calculateZScore(array $values): ?float
    {
        $count = count($values);

        if ($count < 2) {
            return null;
        }

        $mean = array_sum($values) / $count;

        $variance = 0;

        foreach ($values as $v) {
            $variance += pow($v - $mean, 2);
        }

        $variance /= $count;

        $stdDev = sqrt($variance);

        if ($stdDev == 0) {
            return null;
        }

        $current = end($values);

        return round(($current - $mean) / $stdDev, 4);
    }
}
