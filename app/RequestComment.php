<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestComment extends Model
{
    protected $fillable = [
        'request_id',
        'user_id',
        'comment'
    ];

    public function request()
    {
        return $this->belongsTo(QmsRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
