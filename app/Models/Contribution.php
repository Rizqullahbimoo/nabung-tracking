<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Contribution extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'goal_id',
        'user_id',
        'amount',
        'type',
        'note',
        'contributed_at',
        'corrects_contribution_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contributed_at' => 'date',
        ];
    }

    public function isDeposit(): bool
    {
        return $this->type === 'deposit';
    }

    /**
     * Whether this row records money taken out of the goal.
     */
    public function isWithdrawal(): bool
    {
        return $this->type === 'withdrawal';
    }

    /**
     * Whether this row is an adjustment entry (e.g. a deleted deposit).
     */
    public function isCorrection(): bool
    {
        return $this->type === 'correction';
    }

    /**
     * Whether this deposit has been "deleted" (reversed by a correction row).
     */
    public function isVoided(): bool
    {
        return $this->correction()->exists();
    }

    /**
     * Amount signed for balance math: withdrawals subtract; corrections are
     * stored negative and add as-is; deposits add.
     */
    public function signedAmount(): float
    {
        return $this->isWithdrawal() ? -1 * (float) $this->amount : (float) $this->amount;
    }

    /**
     * The goal this contribution is applied to.
     */
    public function goal(): BelongsTo
    {
        return $this->belongsTo(Goal::class);
    }

    /**
     * The user who recorded this contribution.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The deposit this correction row reverses (set only on correction rows).
     */
    public function corrects(): BelongsTo
    {
        return $this->belongsTo(Contribution::class, 'corrects_contribution_id');
    }

    /**
     * The correction row that reversed this deposit, if any.
     */
    public function correction(): HasOne
    {
        return $this->hasOne(Contribution::class, 'corrects_contribution_id');
    }
}
