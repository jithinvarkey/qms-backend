<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Document extends Model {

    protected $fillable = [
        'document_code',
        'title',
        'category_id',
        'type_id',
        'version',
        'status',
        'file_path',
        'effective_date',
        'review_date',
        'approve_status',
        'approved_by',
        'approved_at',
        'created_by',
        'updated_by'
    ];

    public function category() {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function type() {
        return $this->belongsTo(DocumentType::class);
    }

    public function creator() {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver() {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
