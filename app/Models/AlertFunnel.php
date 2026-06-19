<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AlertFunnel extends Model
{
    use HasFactory;

    protected $fillable = [
        'alert_configuration_id',
        'symbol',
        'funnel_id',
        'parent_id'
    ];

    public $table = 'alert_funnel';

    public function configuration()
    {
        return $this->belongsTo(AlertConfiguration::class, 'alert_configuration_id');
    }

    public function parent()
    {
        return $this->belongsTo(AlertLog::class, 'parent_id');
    }
}
