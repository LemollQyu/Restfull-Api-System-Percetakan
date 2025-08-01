<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PasswordOtp extends Model
{
    protected $fillable = [
        'email_or_phone',
        'otp_code',
        'method',
        'used',
        'expires_at',
        'verified_at'
    ];

    protected $casts = [
        'used' => 'boolean',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}

