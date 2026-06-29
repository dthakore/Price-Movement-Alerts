<?php

namespace App\Http\Controllers;

use App\Models\VolumeAlert;
use Illuminate\Http\Request;

class VolumeAlertController extends Controller
{
    public function index()
    {
        VolumeAlert::where('created_at', '<', now()->subHours(48))->delete();

        $alerts   = VolumeAlert::latest()->limit(500)->get();
        $lastScan = VolumeAlert::max('created_at');

        return view('volume_alerts.index', compact('alerts', 'lastScan'));
    }

    public function clearAll()
    {
        // Cascade delete removes funnels automatically
        VolumeAlert::query()->delete();

        return redirect()->route('volume-alerts.index')->with('success', 'All volume alerts cleared.');
    }
}
