<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class QmsRequest extends Model
{
    protected $table = 'requests';

    protected $fillable = [
        'request_no',
        'title',
        'description',
        'department_id',
        'request_type_id',
        'status',
        'priority',
        'created_by',
        'assigned_to',
        'approved_by',
        'approved_at',
        'rejected_reason',
        'due_date',
        'is_active'
    ];

    public function department()
    {
        return $this->belongsTo(Department::class);
    }

    public function type()
    {
        return $this->belongsTo(RequestType::class, 'request_type_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class,'status');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approvals()
    {
        return $this->hasMany(RequestApproval::class, 'request_id');
    }

    public function comments()
    {
        return $this->hasMany(RequestComment::class, 'request_id') ->orderBy('created_at', 'desc');
    }
    
     public function assigner()
    {
         return $this->belongsTo(User::class, 'assigned_to');
    }

    public function attachments()
    {
        return $this->hasMany(RequestAttachment::class, 'request_id') ->orderBy('created_at', 'desc');
    }

    public function histories()
    {
        return $this->hasMany(RequestHistory::class, 'request_id')->orderBy('created_at', 'desc');
    }
    
}

