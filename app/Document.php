<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Document extends Model {

    public function category() {
        return $this->belongsTo(DocumentCategory::class);
    }

    public function type() {
        return $this->belongsTo(DocumentType::class);
    }

    public function versions() {
        return $this->hasMany(DocumentVersion::class);
    }
}
