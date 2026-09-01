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

    /**
     * The user's currently active pair, if any.
     */
    public function activePair(): ?Pair
    {
        return Pair::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('user_one_id', $this->id)
                    ->orWhere('user_two_id', $this->id);
            })
            ->first();
    }

    /**
     * Whether this user is currently paired with someone.
     */
    public function isPaired(): bool
    {
        return $this->activePair() !== null;
    }

    /**
     * The partner in this user's active pair, or null if unpaired.
     */
    public function partner(): ?User
    {
        return $this->activePair()?->partnerOf($this);
    }
}
