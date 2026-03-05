<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestStatusSla extends Model
{
    protected $table = 'request_status_sla';

    protected $fillable = [
        'request_id',
        'status_id',
        'entered_at',
        'exited_at',
        'changed_by'
    ];

    protected $dates = [
        'entered_at',
        'exited_at',
        'created_at',
        'updated_at'
    ];

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function request()
    {
        return $this->belongsTo(QmsRequest::class, 'request_id');
    }

    public function status()
    {
        return $this->belongsTo(Status::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    /*
    |--------------------------------------------------------------------------
    | Helper: Duration in Hours
    |--------------------------------------------------------------------------
    */

    public function getDurationHoursAttribute()
    {
        if (!$this->entered_at) {
            return 0;
        }

        $exit = $this->exited_at ?: now();

        return $this->entered_at->diffInHours($exit);
    }
}