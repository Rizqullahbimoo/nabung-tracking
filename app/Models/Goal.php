<?php

namespace App\Models;

use App\Support\Notifier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Goal extends Model
{
    use HasFactory;

    /**
     * Notify members whenever a goal transitions into "achieved", no matter
     * which code path did it.
     */
    protected static function booted(): void
    {
        static::updated(function (self $goal): void {
            if ($goal->wasChanged('status') && $goal->status === 'achieved') {
                Notifier::goalAchieved($goal);
            }
        });
    }

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
     * SQL expression for a contribution row's balance impact:
     * withdrawals subtract, everything else adds as stored.
     */
    private const NET_AMOUNT_SQL = "CASE WHEN type = 'withdrawal' THEN -1 * amount ELSE amount END";

    /**
     * Current balance of the goal:
     *   SUM(deposit) - SUM(withdrawal) + SUM(correction)
     *
     * Withdrawals are stored as a positive amount but subtract here.
     * Corrections keep their existing behaviour (a signed adjustment row,
     * per database-schema.md 2.5) and are summed as stored.
     */
    public function collectedAmount(): float
    {
        return (float) $this->contributions()
            ->selectRaw('COALESCE(SUM('.self::NET_AMOUNT_SQL.'), 0) as net_amount')
            ->value('net_amount');
    }

    /**
     * Attach the current balance as a "collected" attribute in one query,
     * so list views don't need N+1 calls to collectedAmount().
     */
    public function scopeWithCollected(Builder $query): Builder
    {
        return $query->addSelect([
            'collected' => Contribution::query()
                ->selectRaw('COALESCE(SUM('.self::NET_AMOUNT_SQL.'), 0)')
                ->whereColumn('goal_id', 'goals.id'),
        ]);
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
                // Net per person: deposits, minus their own withdrawals, minus
                // their own corrections (deleted deposits) - so the rows still
                // add up to the goal balance.
                'total' => (float) $rows->sum(fn ($row) => $row->signedAmount()),
            ])
            ->sortByDesc('total')
            ->values();
    }

    /**
     * Keep the goal status in sync with its balance, in both directions:
     *   active   -> achieved  when the balance reaches the target
     *   achieved -> active    when a withdrawal drops it back below target
     */
    public function syncAchievedStatus(): void
    {
        $collected = $this->collectedAmount();
        $target = (float) $this->target_amount;

        if ($this->status === 'active' && $collected >= $target) {
            $this->update(['status' => 'achieved']);
        } elseif ($this->status === 'achieved' && $collected < $target) {
            $this->update(['status' => 'active']);
        }
    }
}
