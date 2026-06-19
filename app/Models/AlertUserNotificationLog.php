<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertUserNotificationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_log_id',
        'user_id',
        'email_sent',
        'response',
        'sent_at'
    ];

    public function user()
    {
        return $this->belongsTo(
            \App\Models\User::class,
            'user_id'
        );
    }
}
