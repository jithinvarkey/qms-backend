<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model {

    protected $fillable = [
        'name',
        'description',
        'department_id',
        'isActive',
        'created_by'
    ];
    
    public function documents() {
        return $this->hasMany(Document::class,'category_id');
    }
    public function department() {
        return $this->belongsTo(Department::class, 'department_id');
    }
}
