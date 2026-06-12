<?php

namespace App\Models;

use App\Notifications\DenebVerifyEmail;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements MustVerifyEmail
{
    /**
     * @use HasFactory<\Database\Factories\UserFactory>
     */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function role(): ?Role
    {
        /**
         * @var ?Role
         */
        $userRole = $this->roles->first();

        return $userRole;
    }

    /**
     * @return array<string>
     */
    public function getAllPermissionsNames(): array
    {
        return $this->getAllPermissions()->pluck('name')->toArray();
    }

    /**
     * This method is overrides the default sendEmailVerificationNotification in order to
     * use the DenebVerifyEmail notification. That notification is exactly the same as the
     * one that it is extending, however, it queues the mail.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new DenebVerifyEmail);
    }
}
