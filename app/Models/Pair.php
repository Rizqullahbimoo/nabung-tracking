<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Pair extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_one_id',
        'user_two_id',
        'status',
        'paired_at',
        'unpaired_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'unpaired_at' => 'datetime',
        ];
    }

    /**
     * The first member of the pair.
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * The second member of the pair.
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * Savings goals owned by this pair.
     */
    public function goals(): HasMany
    {
        return $this->hasMany(Goal::class);
    }

    /**
     * Limit the query to active pairs.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Limit the query to pairs that include the given user.
     */
    public function scopeForUser(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $query) use ($user) {
            $query->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id);
        });
    }

    /**
     * Whether this pair currently has only one member (solo mode).
     */
    public function isSolo(): bool
    {
        return $this->user_two_id === null;
    }

    /**
     * Retire this pair: it stays as history (its goals & contributions are
     * untouched) but is no longer anyone's active pair. Used both when an
     * invite supersedes a solo pair and when a couple unpairs (F-12, 6.1).
     */
    public function retire(): void
    {
        $this->update([
            'status' => 'unpaired',
            'unpaired_at' => now(),
        ]);
    }

    /**
     * Return the other member of this pair relative to the given user.
     */
    public function partnerOf(User $user): ?User
    {
        if ($this->user_one_id === $user->id) {
            return $this->userTwo;
        }

        if ($this->user_two_id === $user->id) {
            return $this->userOne;
        }

        return null;
    }
}
