<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestType extends Model
{
    protected $fillable = [
        'name',
        'prefix',
        'description',
        'is_active'
    ];

    public function requests()
    {
        return $this->hasMany(Request::class);
    }
}
