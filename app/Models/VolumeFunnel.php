<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class VolumeFunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'symbol',
        'volume_alert_id',
        'funnel_step',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function volumeAlert()
    {
        return $this->belongsTo(VolumeAlert::class);
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeBySymbol($query, $symbol)
    {
        return $query->where('symbol', $symbol);
    }
}
