<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestHistory extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'request_id',
        'action',
        'old_status',
        'new_status',
        'changed_by',
        'remarks'
    ];

    public function request()
    {
        return $this->belongsTo(QmsRequest::class, 'request_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
