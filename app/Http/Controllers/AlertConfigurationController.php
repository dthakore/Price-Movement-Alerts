<?php

namespace App\Http\Controllers;

use App\Models\AlertConfiguration;
use App\Models\AlertConfigurationUser;
use App\Models\AlertLog;
use App\Models\Symbol;
use Illuminate\Http\Request;

class AlertConfigurationController extends Controller
{
    public function index()
    {
        if (auth()->user()->isAdmin()) {
            $configs = AlertConfiguration::latest()
                ->get();
        } else {
            $configs = AlertConfiguration::where('is_active', 1)
                ->latest()
                ->get();
        }
        $users = \App\Models\User::select('id', 'name', 'email')->get();

        $symbols = Symbol::where(['exchange_id' => 1])->orderBy('id')->pluck('symbol');

        return view('alerts.config', compact('configs', 'symbols', 'users'));
    }

    public function notifications(AlertConfiguration $config)
    {
        $allLogs = AlertLog::with(['notificationUsers.user'])
            ->where('alert_configuration_id', $config->id)
            ->latest()
            ->get()
            ->each
            ->append(['candle_label', 'candle_badge_class', 'open_timestamp']);

        // GROUPED (for price_alert)
        $notifications = $allLogs->groupBy('parent_id');

        $parents = $notifications[null] ?? collect();

        // FORMAT PARENTS
        $parents = $parents->map(function ($n) {
            $n->pretty_time = $n->created_at->diffForHumans();
            $n->full_time = $n->created_at->format('d M Y, H:i');

            return $n;
        });

        $notifications = $notifications->map(function ($group) {
            return $group->map(function ($n) {
                $n->pretty_time = $n->created_at->diffForHumans();
                $n->full_time = $n->created_at->format('d M Y, H:i');

                return $n;
            });
        });

        $funnelLogs = $allLogs
            ->where('funnel_id', 1)
            ->map(function ($n) {
                $n->pretty_time = $n->created_at->diffForHumans();
                $n->full_time = $n->created_at->format('d M Y, H:i');

                return $n;
            });

        return view('alerts.notifications', compact(
            'config',
            'parents',
            'notifications',
            'funnelLogs'
        ));
    }

    public function clear($configId)
    {
        AlertLog::where('alert_configuration_id', $configId)->delete();

        return redirect()
            ->back()
            ->with('success', 'All notifications cleared successfully.');
    }

    public function store(Request $request)
    {
        //        dd($request);
        $request->validate([
            'symbol_source' => 'required|in:1,2',
            'symbols' => 'nullable',
            'percentage' => 'required|numeric|min:0.1',
            'direction' => 'required|numeric',
            'time_duration' => 'required',
            'frequency_minutes' => 'required|integer|min:1',
            'notify_users' => 'nullable|array',
        ]);

        try {

            $alert = AlertConfiguration::create([
                'user_id' => $request->user_id,
                'symbol_source' => (int) $request->symbol_source,
                'symbols' => $request->symbol_source == 2 ? $request->symbols : Symbol::where(['exchange_id' => 1])->orderBy('id')->pluck('symbol'),
                'percentage' => $request->percentage,
                'direction' => $request->direction,
                'time_duration' => $request->time_duration.' Hour',
                'time_duration_minutes' => $request->time_duration * 60,
                'frequency_minutes' => $request->frequency_minutes,
            ]);

            if ($request->notify_users) {

                foreach ($request->notify_users as $uid) {

                    AlertConfigurationUser::create([
                        'alert_configuration_id' => $alert->id,
                        'user_id' => $uid,
                    ]);
                }
            }

            return back()->with('success', 'Alert configuration saved');

        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Inline Edit (UPDATE)
     */
    public function update(Request $request, AlertConfiguration $alert)
    {
        //        dd($request);
        $request->validate([
            'symbol_source' => 'required|in:1,2',
            'symbols' => 'nullable',
            'percentage' => 'required|numeric|min:0.1',
            'direction' => 'required|boolean',
            'time_duration' => 'required|integer|min:1',
            'frequency_minutes' => 'required|integer|min:1',
        ]);

        if ($alert->update([
            'symbol_source' => (int) $request->symbol_source,
            'symbols' => $request->symbol_source == 2 ? isset($request->symbols)
                ? explode(',', $request->symbols)
                : [] : Symbol::where(['exchange_id' => 1])->orderBy('id')->pluck('symbol'),
            'percentage' => $request->percentage,
            'direction' => $request->direction,
            'time_duration' => $request->time_duration.' Hour',
            'time_duration_minutes' => $request->time_duration * 60,
            'frequency_minutes' => $request->frequency_minutes,
            'is_active' => $request->boolean('is_active'), // ✅ IMPORTANT
        ])) {
            AlertConfigurationUser::where(
                'alert_configuration_id',
                $alert->id
            )->delete();

            if ($request->notify_users) {
                $notify_users = explode(',', $request->notify_users);

                foreach ($notify_users as $uid) {

                    AlertConfigurationUser::create([
                        'alert_configuration_id' => $alert->id,
                        'user_id' => $uid,
                    ]);
                }
            }

            return response()->json([
                'status' => true,
                'message' => 'Alert updated successfully',
            ]);
        } else {
            dd('Something went wrong');
        }

    }

    public function toggle(AlertConfiguration $config)
    {
        abort_if($config->user_id !== auth()->id(), 403);

        $config->update([
            'is_active' => ! $config->is_active,
        ]);

        return back();
    }

    public function destroy(AlertConfiguration $alert)
    {
        $alert->delete();

        return redirect()
            ->back()
            ->with('success', 'Alert deleted successfully');
    }

    public function toggleUser(Request $request)
    {
        $config = $request->alert_configuration_id;
        $user = $request->user_id;

        if ($request->enabled) {

            AlertConfigurationUser::firstOrCreate([
                'alert_configuration_id' => $config,
                'user_id' => $user,
            ]);

        } else {

            AlertConfigurationUser::where([
                'alert_configuration_id' => $config,
                'user_id' => $user,
            ])->delete();

        }

        return response()->json([
            'status' => true,
        ]);
    }
}
