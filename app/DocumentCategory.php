<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class DocumentCategory extends Model {

    protected $fillable = [
        'name',
        'description',
        'isActive'
    ];
    
    public function documents() {
        return $this->hasMany(Document::class);
    }
}
