<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class RequestAttachment extends Model
{
    protected $fillable = [
        'request_id',
        'file_name',
        'file_path',
        'uploaded_by'
    ];

    public function request()
    {
        return $this->belongsTo(QmsRequest::class, 'request_id');
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
