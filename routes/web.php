<?php

use App\Http\Controllers\AlertConfigurationController;
use App\Http\Controllers\ProfileController;
use App\Models\AlertLog;
use App\Models\CronJobLog;
use App\Models\Symbol;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('alerts.config.index')
        : redirect()->route('login');
});

Route::post('/alerts/read', function (Request $request) {
    AlertLog::where('id', $request->id)
        ->where(function ($q) {
            $q->whereNull('user_id')
                ->orWhere('user_id', auth()->id());
        })
        ->update(['is_read' => true]);

    return response()->json(['success' => true]);
})->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::prefix('alerts/config')->name('alerts.config.')->group(function () {
        Route::get('/', [AlertConfigurationController::class, 'index'])->name('index');
        Route::post('/', [AlertConfigurationController::class, 'store'])->name('store');
        Route::get('{config}/notifications', [AlertConfigurationController::class, 'notifications'])->name('notifications');
        Route::post('{alert}/toggle', [AlertConfigurationController::class, 'toggle'])->name('toggle');
        Route::delete('{alert}', [AlertConfigurationController::class, 'destroy'])->name('destroy');
        Route::put('{alert}', [AlertConfigurationController::class, 'update'])->name('update');
        Route::post('user-toggle', [AlertConfigurationController::class, 'toggleUser']);
    });

    Route::delete(
        '/alerts/{config}/notifications/clear',
        [AlertConfigurationController::class, 'clear']
    )->name('alerts.notifications.clear');

    Route::get('/volume-alerts', [\App\Http\Controllers\VolumeAlertController::class, 'index'])->name('volume-alerts.index');
    Route::delete('/volume-alerts/clear-all', [\App\Http\Controllers\VolumeAlertController::class, 'clearAll'])->name('volume-alerts.clear');

    Route::get('/cron-logs', function (Request $request) {
        $query = CronJobLog::latest('start_time');
        if ($request->filled('job')) {
            $query->where('cron_job', $request->job);
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('from_date')) {
            $query->whereDate('start_time', '>=', $request->from_date);
        }
        if ($request->filled('to_date')) {
            $query->whereDate('start_time', '<=', $request->to_date);
        }
        $logs = $query->paginate(100)->withQueryString();

        return view('cron_logs.index', compact('logs'));
    })->middleware('admin')->name('cron-logs.index');

    Route::delete('/cron-logs/clear', function () {
        CronJobLog::query()->delete();

        return back()->with('success', 'Cron logs cleared.');
    })->middleware('admin')->name('cron-logs.clear');

    Route::get('/sync-exchange-info', function () {
        $response = Http::timeout(15)->get('https://fapi.binance.com/fapi/v1/exchangeInfo');

        if (! $response->successful()) {
            return back()->with('error', 'Failed to fetch Binance exchange info.');
        }

        Symbol::where('exchange_id', 1)->delete();

        $symbols = collect($response->json('symbols'))
            ->filter(fn ($symbol) => $symbol['status'] === 'TRADING'
                && $symbol['contractType'] === 'PERPETUAL')
            ->pluck('symbol');

        foreach ($symbols as $symbol) {
            Symbol::create(['symbol' => $symbol, 'exchange_id' => 1]);
        }

        return back()->with('success', "Synced {$symbols->count()} Binance symbols.");
    })->middleware('admin')->name('sync-exchange-info');
});

require __DIR__.'/auth.php';
