<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Goal extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'pair_id',
        'proposed_by',
        'approved_by',
        'name',
        'category',
        'target_amount',
        'target_date',
        'status',
        'approved_at',
        'archived_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'target_amount' => 'decimal:2',
            'target_date' => 'date',
            'approved_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /**
     * The pair this goal belongs to.
     */
    public function pair(): BelongsTo
    {
        return $this->belongsTo(Pair::class);
    }

    /**
     * Contributions recorded against this goal.
     */
    public function contributions(): HasMany
    {
        return $this->hasMany(Contribution::class);
    }

    /**
     * The user who proposed this goal.
     */
    public function proposedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposed_by');
    }

    /**
     * The user who approved this goal (if any).
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Total contributed to this goal (SUM of contribution amounts).
     */
    public function collectedAmount(): float
    {
        return (float) $this->contributions()->sum('amount');
    }

    /**
     * Progress toward the target as a whole percentage, capped at 100.
     */
    public function progressPercent(): int
    {
        $target = (float) $this->target_amount;

        return $target > 0 ? min(100, (int) floor($this->collectedAmount() / $target * 100)) : 0;
    }

    /**
     * Per-user contribution totals, shaped like api-spec "contribution_breakdown".
     *
     * @return Collection<int, array{user_id: int, name: string|null, total: float}>
     */
    public function contributionBreakdown(): Collection
    {
        return $this->contributions()
            ->with('user:id,name')
            ->get()
            ->groupBy('user_id')
            ->map(fn ($rows) => [
                'user_id' => (int) $rows->first()->user_id,
                'name' => $rows->first()->user?->name,
                'total' => (float) $rows->sum('amount'),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Flip an active goal to "achieved" once its target is reached.
     */
    public function syncAchievedStatus(): void
    {
        if ($this->status === 'active' && $this->collectedAmount() >= (float) $this->target_amount) {
            $this->update(['status' => 'achieved']);
        }
    }
}
