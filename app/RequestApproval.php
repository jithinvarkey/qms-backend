<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestApproval extends Model
{
    protected $fillable = [
        'request_id',
        'approver_id',
        'level',
        'approval_status',
        'approved_at',
        'remarks'
    ];

    public function request()
    {
        return $this->belongsTo(QmsRequest::class, 'request_id');
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approver_id');
    }
}

