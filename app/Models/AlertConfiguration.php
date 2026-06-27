<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertConfiguration extends Model
{
    use HasFactory;

    protected $dates = ['last_run_at'];

    public $table = 'alert_configurations';

    protected $fillable = [
        'type',
        'user_id',
        'symbols',
        'percentage',
        'reversion_percentage',
        'direction',
        'time_duration',
        'time_duration_minutes',
        'frequency_minutes',
        'is_active',
        'is_running',
        'last_run_at',
        'symbol_source',
    ];


    protected $casts = [
        'symbols' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function notifyUsers()
    {
        return $this->belongsToMany(
            User::class,
            'alert_configuration_users',
            'alert_configuration_id',
            'user_id'
        );
    }
}
