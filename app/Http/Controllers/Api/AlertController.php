<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AlertConfiguration;
use App\Models\AlertLog;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AlertController extends Controller
{
    private const DIRECTION = [1 => 'up', 0 => 'down'];
    private const CANDLE    = [1 => 'green', 0 => 'red'];

    // -------------------------------------------------------------------------
    // GET /api/alerts
    // -------------------------------------------------------------------------
    public function index()
    {
        $user = request()->user();
        $alertQuery = AlertConfiguration::with('notifyUsers:id,name')->latest();
        if (!$user->isAdmin()) {
            $alertQuery->where('user_id', $user->id);
        }
        $configs = $alertQuery->get();

        if ($configs->isEmpty()) {
            return response()->json([
                'alerts'  => [],
                'summary' => [
                    'total_rules'                  => 0,
                    'active_rules'                 => 0,
                    'notifications_today'          => 0,
                    'total_notifications_all_time' => 0,
                ],
            ]);
        }

        $ids   = $configs->pluck('id');
        $today = now()->startOfDay();

        $totals = AlertLog::whereIn('alert_configuration_id', $ids)
            ->selectRaw('alert_configuration_id, COUNT(*) as total')
            ->groupBy('alert_configuration_id')
            ->pluck('total', 'alert_configuration_id');

        $todayTotals = AlertLog::whereIn('alert_configuration_id', $ids)
            ->where('created_at', '>=', $today)
            ->selectRaw('alert_configuration_id, COUNT(*) as total')
            ->groupBy('alert_configuration_id')
            ->pluck('total', 'alert_configuration_id');

        $latestIds = AlertLog::whereIn('alert_configuration_id', $ids)
            ->selectRaw('MAX(id) as id')
            ->groupBy('alert_configuration_id')
            ->pluck('id');

        $lastLogs = AlertLog::whereIn('id', $latestIds)
            ->select(['alert_configuration_id', 'symbol', 'created_at'])
            ->get()
            ->keyBy('alert_configuration_id');

        $alerts = $configs->map(function ($config) use ($totals, $todayTotals, $lastLogs) {
            $last = $lastLogs->get($config->id);

            return [
                'id'              => $config->id,
                'symbol'          => $config->symbol_source == 1 ? 'AUTO' : implode(',', (array) $config->symbols),
                'exchange'        => 'Binance',
                'symbol_type'     => $config->symbol_source == 1 ? 'auto' : 'specific',
                'move_pct'        => (float) $config->percentage,
                'direction'       => self::DIRECTION[$config->direction] ?? 'up',
                'time_window_hrs' => (int) ($config->time_duration_minutes / 60),
                'frequency_mins'  => (int) $config->frequency_minutes,
                'status'          => $config->is_active ? 'active' : 'inactive',
                'notify_users'    => $config->notifyUsers
                    ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
                'created_at' => $config->created_at->toIso8601String(),
                'stats'      => [
                    'total_notifications' => (int) $totals->get($config->id, 0),
                    'notifications_today' => (int) $todayTotals->get($config->id, 0),
                    'last_fired_at'       => $last ? $last->created_at->toIso8601String() : null,
                    'last_symbol'         => $last?->symbol,
                ],
            ];
        });

        return response()->json([
            'alerts'  => $alerts,
            'summary' => [
                'total_rules'                  => $configs->count(),
                'active_rules'                 => $configs->where('is_active', 1)->count(),
                'notifications_today'          => (int) $todayTotals->sum(),
                'total_notifications_all_time' => (int) $totals->sum(),
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // GET /api/alerts/{id}/notifications
    // -------------------------------------------------------------------------
    public function notifications(Request $request, int $id)
    {
        $request->validate([
            'type'      => 'sometimes|in:price,funnel',
            'limit'     => 'sometimes|integer|min:1|max:100',
            'offset'    => 'sometimes|integer|min:0',
            'date_from' => 'sometimes|date_format:Y-m-d',
            'date_to'   => 'sometimes|date_format:Y-m-d',
        ]);

        $user = $request->user();
        $alertQuery = AlertConfiguration::with('notifyUsers:id,name')->where('id', $id);
        if (!$user->isAdmin()) {
            $alertQuery->where('user_id', $user->id);
        }
        $config = $alertQuery->firstOrFail();

        $type      = $request->input('type', 'price');
        $limit     = (int) $request->input('limit', 20);
        $offset    = (int) $request->input('offset', 0);
        $dateFrom  = $request->input('date_from');
        $dateTo    = $request->input('date_to');
        $isPricTab = $type === 'price';

        // ── Filtered count (type + dates) ─────────────────────────────────────
        $filteredQuery = AlertLog::where('alert_configuration_id', $id);
        $this->applyTypeFilter($filteredQuery, $type, $isPricTab);
        $this->applyDateFilter($filteredQuery, $dateFrom, $dateTo);
        $totalFiltered = $filteredQuery->count();

        // ── All-time count (type only, no dates) ──────────────────────────────
        $allTimeQuery = AlertLog::where('alert_configuration_id', $id);
        $this->applyTypeFilter($allTimeQuery, $type, $isPricTab);
        $totalAllTime = $allTimeQuery->count();

        // ── Funnel tab badge ──────────────────────────────────────────────────
        $funnelCount = AlertLog::where('alert_configuration_id', $id)
            ->where('source_job', 'funnel_alert')
            ->count();

        // ── Paginated parent rows ─────────────────────────────────────────────
        $parents = (clone $filteredQuery)->latest()->offset($offset)->limit($limit)->get();

        // ── Children: one batch query for all visible parents ─────────────────
        $childrenByParent = collect();
        if ($isPricTab && $parents->isNotEmpty()) {
            $parentIds = $parents->pluck('id');
            $childrenByParent = AlertLog::with(['notificationUsers.user'])
                ->whereIn('parent_id', $parentIds)
                ->orderBy('id', 'desc')
                ->get()
                ->groupBy('parent_id');
        }

        // ── Notification users for parent rows ────────────────────────────────
        $parents->load(['notificationUsers.user']);

        $notifications = $parents->map(function (AlertLog $log) use ($childrenByParent, $isPricTab) {
            $children = $isPricTab
                ? ($childrenByParent->get($log->id) ?? collect())->map(
                    fn ($c) => $this->formatLog($c, true)
                )->values()
                : [];

            return array_merge(
                $this->formatLog($log, false),
                ['children' => $children]
            );
        });

        return response()->json([
            'alert' => [
                'id'              => $config->id,
                'symbol'          => $config->symbol_source == 1 ? 'AUTO' : implode(',', (array) $config->symbols),
                'exchange'        => 'Binance',
                'move_pct'        => (float) $config->percentage,
                'direction'       => self::DIRECTION[$config->direction] ?? 'up',
                'time_window_hrs' => (int) ($config->time_duration_minutes / 60),
                'frequency_mins'  => (int) $config->frequency_minutes,
                'status'          => $config->is_active ? 'active' : 'inactive',
                'notify_users'    => $config->notifyUsers
                    ->map(fn($u) => ['id' => $u->id, 'name' => $u->name])
                    ->values(),
            ],
            'meta' => [
                'total_filtered' => $totalFiltered,
                'total_all_time' => $totalAllTime,
                'limit'          => $limit,
                'offset'         => $offset,
                'has_more'       => ($offset + $limit) < $totalFiltered,
                'type'           => $type,
                'funnel_count'   => $funnelCount,
            ],
            'notifications' => $notifications,
        ]);
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function formatLog(AlertLog $log, bool $withDiff): array
    {
        $priceTo = (float) $log->price_to;

        $row = [
            'id'           => $log->id,
            'type'         => $log->source_job === 'funnel_alert' ? 'funnel' : 'price',
            'symbol'       => $log->symbol,
            'exchange'     => 'Binance',
            'direction'    => $log->performance >= 0 ? 'up' : 'down',
            'fired_at'     => $log->created_at->toIso8601String(),
            'open_time'    => $log->open_time
                ? Carbon::createFromTimestampMs($log->open_time)->toIso8601String()
                : null,
            'price'        => [
                'from' => $log->price_from,
                'to'   => $log->price_to,
                'high' => $log->high,
                'low'  => $log->low,
            ],
            'volume'       => $log->volume,
            'move_pct'     => (float) $log->performance,
            'z_score'      => [
                'h24' => $log->z_score_1d,
                'd2'  => $log->z_score_2d,
                'd3'  => $log->z_score_3d,
            ],
            'returns'      => [
                'r3_pct'  => $log->r3,
                'r5_pct'  => $log->r5,
                'r15_pct' => $log->r15,
            ],
            'candle_color' => self::CANDLE[$log->candle] ?? null,
            'notification_users' => $log->relationLoaded('notificationUsers')
                ? $log->notificationUsers->map(fn($nu) => [
                    'id'         => $nu->user_id,
                    'name'       => $nu->user?->name,
                    'email_sent' => (bool) $nu->email_sent,
                    'sent_at'    => $nu->sent_at,
                ])->values()
                : [],
        ];

        // Extra diff fields shown in the child row table
        if ($withDiff && $priceTo > 0) {
            $row['high_diff_pct'] = $log->high
                ? round(((float)$log->high - $priceTo) / $priceTo * 100, 2)
                : null;
            $row['low_diff_pct'] = $log->low
                ? round(((float)$log->low - $priceTo) / $priceTo * 100, 2)
                : null;
        }

        return $row;
    }

    private function applyTypeFilter($query, string $type, bool $isPriceTab): void
    {
        if (!$isPriceTab) {
            $query->where('source_job', 'funnel_alert');
        } else {
            // Price parents only — exclude funnel rows and child rows
            $query->whereNull('parent_id')
                  ->where(function ($q) {
                      $q->where('source_job', '!=', 'funnel_alert')
                        ->orWhereNull('source_job');
                  });
        }
    }

    private function applyDateFilter($query, ?string $from, ?string $to): void
    {
        if ($from) {
            $query->where('created_at', '>=', Carbon::parse($from)->startOfDay());
        }
        if ($to) {
            $query->where('created_at', '<=', Carbon::parse($to)->endOfDay());
        }
    }
}
