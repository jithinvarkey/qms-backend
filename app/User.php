<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

// app/Models/User.php
class User extends Authenticatable {
     
    use HasApiTokens, Notifiable;
   


    public function roles(): BelongsToMany {
        return $this->belongsToMany(
                        Role::class,
                        'user_roles', // pivot table name
                        'user_id',
                        'role_id'
        );
    }

    public function hasRole(string $role): bool {
        return $this->roles()->where('name', $role)->exists();
    }

    public function hasAnyRole(array $roles): bool {
        return $this->roles()->whereIn('name', $roles)->exists();
    }
}
