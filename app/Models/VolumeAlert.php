<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VolumeAlert extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'candle_open_time',
        'avg_volume',
        'current_volume',
        'buy_volume',
        'buy_volume_percentage',
        'trades',
        'volume_moment',
        'open_price',
        'high_price',
        'low_price',
        'close_price',
        'before_24_hours_price',
        'z_score_last_10_candles',
        'z_score_last_192_candles',
        'range_percentage',
        'last_5_candles',
    ];

    protected $casts = [
        'last_5_candles' => 'array',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function funnels()
    {
        return $this->hasMany(VolumeFunnel::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes (VERY useful for cron & bot engine)
    |--------------------------------------------------------------------------
    */
    public function scopeBySymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }
}
