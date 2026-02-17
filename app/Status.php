<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Status extends Model
{
    protected $table = 'statuses';
    protected $fillable = [
        'name',
        'code',
        'color',
        'is_final',
        'is_active'
    ];

    public function requests()
    {
        return $this->hasMany(Request::class);
    }
}

