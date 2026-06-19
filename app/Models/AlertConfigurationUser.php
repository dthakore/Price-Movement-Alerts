<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AlertConfigurationUser extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_configuration_id',
        'user_id',
    ];
}
