<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
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

    /**
     * Invites this user has created.
     */
    public function invitesCreated(): HasMany
    {
        return $this->hasMany(Invite::class, 'created_by');
    }

    /**
     * Contributions this user has recorded.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * The pair where this user is the first member.
     */
    public function pairAsOne(): HasOne
    {
        return $this->hasOne(Pair::class, 'user_one_id');
    }

    /**
     * The pair where this user is the second member.
     */
    public function pairAsTwo(): HasOne
    {
        return $this->hasOne(Pair::class, 'user_two_id');
    }
}
