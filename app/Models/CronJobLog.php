<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CronJobLog extends Model
{
    protected $fillable = [
        'cron_job',
        'status',
        'start_time',
        'end_time',
        'bots_processed',
        'trades_dispatched',
        'alerts_processed',
        'keys_processed',
        'error',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time'   => 'datetime',
    ];

    public function getDurationAttribute(): ?string
    {
        if (!$this->end_time) return null;
        $secs = $this->start_time->diffInSeconds($this->end_time);
        return $secs < 60 ? "{$secs}s" : round($secs / 60, 1).'m';
    }
}
