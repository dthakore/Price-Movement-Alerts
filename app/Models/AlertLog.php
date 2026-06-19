<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertLog extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'alert_configuration_id',
        'user_id',
        'symbol',
        'performance',
        'price_from',
        'price_to',
        'high',
        'low',
        'volume',
        'is_read',
        'z_score_1d',
        'z_score_2d',
        'z_score_3d',
        'r3',
        'r5',
        'r15',
        'source_job',
        'parent_id',
        'funnel_id',
        'candle',
        'open_time',
    ];

    public function configuration()
    {
        return $this->belongsTo(AlertConfiguration::class, 'alert_configuration_id');
    }

    public function parent()
    {
        return $this->belongsTo(AlertLog::class, 'parent_id');
    }

    public function notificationUsers()
    {
        return $this->hasMany(
            \App\Models\AlertUserNotificationLog::class,
            'alert_log_id'
        );
    }

    protected function candleLabel(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->attributes['candle']) {
                1 => 'Green',
                0 => 'Red',
                default => null,
            },
        );
    }

    protected function candleBadgeClass(): Attribute
    {
        return Attribute::make(
            get: fn () => match ($this->attributes['candle']) {
                1 => 'bg-success',
                0 => 'bg-danger',
                default => null,
            },
        );
    }

    protected function openTimestamp(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->attributes['open_time']
                ? Carbon::createFromTimestampMs($this->attributes['open_time'])->format('Y-m-d H:i:s')
                : null,
        );
    }
}
