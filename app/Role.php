<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Model;

// app/Models/Role.php
class Role extends Model
{
    protected $fillable = ['name'];

   public function users(): BelongsToMany
    {
        return $this->belongsToMany(
            User::class,
            'user_roles',   // pivot table
            'role_id',
            'user_id'
        );
    }
}
