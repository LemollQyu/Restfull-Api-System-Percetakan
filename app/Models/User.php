<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'phone_number',
        'password',
        'role',
        'is_approved',
        'approval_token',
        'approved_at',
        'email_verification_token',
        'email_verified_at'
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'approval_token',
        'email_verification_token'
    ];

    protected $casts = [
        'is_approved' => 'boolean',
        'approved_at' => 'datetime',
        'email_verified_at' => 'datetime',
    ];
}
