<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertConfiguration;
use App\Models\AlertConfigurationUser;
use App\Models\AlertLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    private const CANDLE    = [1 => 'green', 0 => 'red'];
    private const DIRECTION = [0 => 'DOWN', 1 => 'UP'];

    // -------------------------------------------------------------------------
    // GET /api/notifications/recent
    // Params: limit (1-100, default 20), offset (default 0), type, alert_id
    // -------------------------------------------------------------------------
    public function recent(Request $request)
    {
        $type    = $request->input('type');
        $alertId = $request->input('alert_id');
        $limit   = min(100, max(1, (int) $request->input('limit', 20)));
        $offset  = max(0, (int) $request->input('offset', 0));

        if ($type !== null && !in_array($type, ['price', 'funnel'], true)) {
            return response()->json([
                'error'   => 'validation_error',
                'message' => 'type must be price or funnel.',
                'details' => ['type' => 'Allowed values: price, funnel'],
            ], 422);
        }

        if ($alertId !== null && (!is_numeric($alertId) || (int) $alertId < 1)) {
            return response()->json([
                'error'   => 'validation_error',
                'message' => 'alert_id must be a positive integer.',
                'details' => ['alert_id' => 'Must be a valid alert rule ID'],
            ], 422);
        }

        // ── Load alert configs the user has subscribed to ─────────────────────
        $subscribedIds = AlertConfigurationUser::where('user_id', auth()->id())
            ->pluck('alert_configuration_id')
            ->all();

        $configQuery = AlertConfiguration::whereIn('id', $subscribedIds);
        if ($alertId) {
            $configQuery->where('id', (int) $alertId);
        }
        $configs   = $configQuery->get()->keyBy('id');
        $configIds = $configs->keys()->all();

        if (empty($configIds)) {
            return response()->json([
                'meta'          => $this->buildMeta(0, $limit, $offset, $type),
                'notifications' => [],
            ]);
        }

        // ── Build log query ───────────────────────────────────────────────────
        $query = AlertLog::whereIn('alert_configuration_id', $configIds)->latest();
        $this->applyTypeFilter($query, $type);

        $total = (clone $query)->count();
        $logs  = $query->offset($offset)->limit($limit)->get();

        // ── Shape notifications ───────────────────────────────────────────────
        $notifications = $logs->map(function (AlertLog $log) use ($configs) {
            $config = $configs->get($log->alert_configuration_id);

            return [
                'id'          => $log->id,
                'alert_id'    => $log->alert_configuration_id,
                'alert_label' => $this->buildLabel($config),
                'type'        => $log->source_job === 'funnel_alert' ? 'funnel' : 'price',
                'symbol'      => $log->symbol,
                'exchange'    => 'Binance',
                'direction'   => $log->performance >= 0 ? 'up' : 'down',
                'fired_at'    => $log->created_at->toIso8601String(),
                'price'       => [
                    'from' => $log->price_from,
                    'to'   => $log->price_to,
                    'high' => $log->high,
                    'low'  => $log->low,
                ],
                'volume'      => $log->volume,
                'move_pct'    => (float) $log->performance,
                'z_score'     => [
                    'h24' => $log->z_score_1d,
                    'd2'  => $log->z_score_2d,
                    'd3'  => $log->z_score_3d,
                ],
                'returns'     => [
                    'r3_pct'  => $log->r3,
                    'r5_pct'  => $log->r5,
                    'r15_pct' => $log->r15,
                ],
                'candle_color' => self::CANDLE[$log->candle] ?? null,
            ];
        });

        return response()->json([
            'meta'          => $this->buildMeta($total, $limit, $offset, $type),
            'notifications' => $notifications,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function applyTypeFilter($query, ?string $type): void
    {
        if ($type === 'price') {
            // Top-level price alert logs only (no children, no funnel)
            $query->whereNull('parent_id')
                  ->where(function ($q) {
                      $q->whereNull('source_job')
                        ->orWhere('source_job', '!=', 'funnel_alert');
                  });
        } elseif ($type === 'funnel') {
            $query->where('source_job', 'funnel_alert');
        } else {
            // All: top-level price logs + funnel logs (exclude price children)
            $query->where(function ($q) {
                $q->where(function ($price) {
                    $price->whereNull('parent_id')
                          ->where(function ($notFunnel) {
                              $notFunnel->whereNull('source_job')
                                        ->orWhere('source_job', '!=', 'funnel_alert');
                          });
                })->orWhere('source_job', 'funnel_alert');
            });
        }
    }

    private function buildLabel(?AlertConfiguration $config): string
    {
        if (!$config) {
            return 'Unknown Alert';
        }

        $symbol    = $config->symbol_source == 1 ? 'AUTO' : implode(',', (array) $config->symbols);
        $direction = self::DIRECTION[$config->direction] ?? 'BOTH';
        $pct       = rtrim(rtrim(number_format((float) $config->percentage, 2), '0'), '.');

        return "{$symbol} · Binance · {$direction} ≥{$pct}%";
    }

    private function buildMeta(int $total, int $limit, int $offset, ?string $type): array
    {
        return [
            'total'    => $total,
            'limit'    => $limit,
            'offset'   => $offset,
            'has_more' => ($offset + $limit) < $total,
            'type'     => $type ?? 'all',
            'as_of'    => now()->toIso8601String(),
        ];
    }
}
