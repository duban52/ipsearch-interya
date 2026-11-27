<?php

namespace App\Models;

use Illuminate\Auth\MustVerifyEmail as MustVerifyEmailTrait;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, MustVerifyEmailTrait, Notifiable;

    protected $fillable = [
        'name', 'lastname', 'username', 'email', 'password',
        'role', 'is_active', 'suspended_at', 'suspended_reason',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'email_verified_at' => 'datetime',
        'last_login_at' => 'datetime',
        'suspended_at' => 'datetime',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isUser(): bool
    {
        return $this->role === 'user';
    }

    public function isActive(): bool
    {
        return $this->is_active && $this->suspended_at === null;
    }

    public function searches(): HasMany
    {
        return $this->hasMany(MacSearch::class);
    }

    public function audits(): HasMany
    {
        return $this->hasMany(AuditLog::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }

    public function fullName(): string
    {
        return trim($this->name.' '.$this->lastname);
    }

    /**
     * Notificación de recuperación de contraseña en español.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\ResetPasswordNotification($token));
    }

    /**
     * Notificación de verificación de correo en español.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\VerifyEmailNotification);
    }
}