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
     * In-app notifications addressed to this user.
     *
     * Overrides the Notifiable trait's notifications() (unused here) with a
     * plain hasMany to our own App\Models\Notification.
     */
    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
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
     * The user's active pair. Every user always has exactly one: a "solo" pair
     * (user_two_id = null) is created on demand until they pair up with someone.
     */
    public function activePair(): Pair
    {
        return Pair::query()
            ->where('status', 'active')
            ->where(function ($query) {
                $query->where('user_one_id', $this->id)
                    ->orWhere('user_two_id', $this->id);
            })
            ->first() ?? $this->createSoloPair();
    }

    /**
     * Create the solo pair that represents this user saving on their own.
     */
    public function createSoloPair(): Pair
    {
        return Pair::create([
            'user_one_id' => $this->id,
            'user_two_id' => null,
            'status' => 'active',
            'paired_at' => null,
        ]);
    }

    /**
     * Whether the active pair has two members (i.e. this user has a partner).
     * A solo user is NOT "paired".
     */
    public function isPaired(): bool
    {
        return $this->activePair()->user_two_id !== null;
    }

    /**
     * Whether the user is currently in solo mode (active pair has one member).
     */
    public function isSolo(): bool
    {
        return ! $this->isPaired();
    }

    /**
     * The partner in this user's active pair, or null when solo.
     */
    public function partner(): ?User
    {
        return $this->activePair()->partnerOf($this);
    }
}
