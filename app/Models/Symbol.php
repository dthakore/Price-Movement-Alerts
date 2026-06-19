<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Symbol extends Model
{
    use HasFactory;
    public $table = 'symbols';

    protected $fillable = ['symbol','exchange_id'];

    public function exchange()
    {
        return $this->belongsTo(Exchange::class);
    }

}
