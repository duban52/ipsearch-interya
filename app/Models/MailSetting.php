<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'host', 'port', 'username', 'password',
        'encryption', 'from_address', 'from_name', 'is_active',
    ];

    protected $casts = [
        'port' => 'integer',
        'is_active' => 'boolean',
    ];

    protected $hidden = ['password'];
}