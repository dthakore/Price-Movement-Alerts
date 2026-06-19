<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Exchange extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'is_active',
    ];


    public function apiKeys()
    {
        return $this->hasMany(UserApiKey::class);
    }

    public function symbols()
    {
        return $this->hasMany(Symbol::class);
    }
}
