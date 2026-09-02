<?php

use App\Models\Contribution;
use App\Models\Goal;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Laporan Bulanan — Nabung Tracking')] class extends Component
{
    public int $year;
    public int $month;

    public function mount(): void
    {
        $this->year = now()->year;
        $this->month = now()->month;
    }

    /**
     * The current user's active pair (solo or coupled) - always present.
     */
    #[Computed]
    public function pair()
    {
        return Auth::user()->activePair();
    }

    #[Computed]
    public function period(): Carbon
    {
        return Carbon::create($this->year, $this->month, 1)->startOfMonth();
    }

    /**
     * Don't allow navigating past the current month (nothing to show there).
     */
    #[Computed]
    public function canGoNext(): bool
    {
        return $this->period->lt(now()->startOfMonth());
    }

    public function previousMonth(): void
    {
        $period = $this->period->subMonthNoOverflow();
        $this->year = $period->year;
        $this->month = $period->month;
    }

    public function nextMonth(): void
    {
        if (! $this->canGoNext) {
            return;
        }

        $period = $this->period->addMonthNoOverflow();
        $this->year = $period->year;
        $this->month = $period->month;
    }

    /**
     * The two members of the pair (so we can render Rp 0 rows).
     */
    #[Computed]
    public function members()
    {
        return $this->pair
            ? collect([$this->pair->userOne, $this->pair->userTwo])->filter()->values()
            : collect();
    }

    /**
     * All of the pair's contributions dated within the selected month.
     */
    #[Computed]
    public function contributions()
    {
        if (! $this->pair) {
            return collect();
        }

        return Contribution::query()
            ->whereHas('goal', fn ($query) => $query->where('pair_id', $this->pair->id))
            ->whereYear('contributed_at', $this->year)
            ->whereMonth('contributed_at', $this->month)
            ->with(['user:id,name', 'goal:id,name'])
            ->get();
    }

    #[Computed]
    public function total(): float
    {
        return (float) $this->contributions->sum(fn ($c) => (float) $c->amount);
    }

    /**
     * Combined per-person total for the month (across every goal).
     *
     * @return \Illuminate\Support\Collection<int, array{user_id: int, name: string|null, total: float}>
     */
    #[Computed]
    public function perPerson()
    {
        $byUser = $this->contributions->groupBy('user_id');

        return $this->members->map(fn ($member) => [
            'user_id' => $member->id,
            'name' => $member->name,
            'total' => (float) ($byUser->get($member->id)?->sum(fn ($c) => (float) $c->amount) ?? 0),
        ]);
    }

    /**
     * Per-goal, then per-person totals for the month. Every active/achieved
     * goal of the pair is listed, even one with Rp 0 this month.
     */
    #[Computed]
    public function perGoal()
    {
        if (! $this->pair) {
            return collect();
        }

        $byGoal = $this->contributions->groupBy('goal_id');

        return Goal::query()
            ->where('pair_id', $this->pair->id)
            ->whereIn('status', ['active', 'achieved'])
            ->orderBy('name')
            ->get()
            ->map(function ($goal) use ($byGoal) {
                $rows = $byGoal->get($goal->id) ?? collect();
                $byUser = $rows->groupBy('user_id');

                return [
                    'name' => $goal->name,
                    'total' => (float) $rows->sum(fn ($c) => (float) $c->amount),
                    'people' => $this->members->map(fn ($member) => [
                        'name' => $member->name,
                        'total' => (float) ($byUser->get($member->id)?->sum(fn ($c) => (float) $c->amount) ?? 0),
                    ]),
                ];
            });
    }
}; ?>

<div class="py-10">
    <div class="mx-auto max-w-2xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <h1 class="text-xl font-bold text-ink">Laporan Bulanan</h1>
            <p class="mt-1 text-sm text-ink-muted">Rekap kontribusi per bulan &mdash; murni untuk informasi.</p>
        </div>

        {{-- Month selector --}}
        <div class="flex items-center justify-between rounded-card border border-hairline bg-surface p-4 shadow-card">
            <button type="button" wire:click="previousMonth"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-btn border border-hairline text-ink-muted transition hover:border-primary hover:text-primary">
                <span aria-hidden="true">&larr;</span>
                <span class="sr-only">Bulan sebelumnya</span>
            </button>

            <span class="text-base font-semibold text-ink">{{ $this->period->translatedFormat('F Y') }}</span>

            <button type="button" wire:click="nextMonth" @disabled(! $this->canGoNext)
                    class="inline-flex h-10 w-10 items-center justify-center rounded-btn border border-hairline text-ink-muted transition hover:border-primary hover:text-primary disabled:opacity-40 disabled:hover:border-hairline disabled:hover:text-ink-muted">
                <span aria-hidden="true">&rarr;</span>
                <span class="sr-only">Bulan berikutnya</span>
            </button>
        </div>

        @if ($this->contributions->isEmpty())
            <div class="mt-6 rounded-card border border-hairline bg-surface p-8 text-center shadow-card">
                <p class="text-base font-semibold text-ink">Belum ada kontribusi</p>
                <p class="mt-1 text-sm text-ink-muted">
                    Tidak ada kontribusi tercatat pada {{ $this->period->translatedFormat('F Y') }}.
                </p>
            </div>
        @else
            {{-- Summary --}}
            <div class="mt-6 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7">
                <p class="text-sm text-ink-muted">Total kontribusi gabungan</p>
                <p class="mt-1 text-3xl font-bold tabular-nums text-ink">Rp {{ number_format($this->total, 0, ',', '.') }}</p>

                <div class="mt-5 space-y-2 border-t border-hairline pt-5">
                    <p class="text-xs font-medium text-ink-muted">Per orang</p>
                    @foreach ($this->perPerson as $row)
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-ink">{{ $row['name'] ?? 'Pengguna' }}</span>
                            <span class="font-semibold tabular-nums text-ink">Rp {{ number_format($row['total'], 0, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Per goal --}}
            <div class="mt-6 space-y-3">
                <p class="text-xs font-medium text-ink-muted">Rincian per goal</p>
                @foreach ($this->perGoal as $goalRow)
                    <div class="rounded-card-sm border border-hairline bg-surface p-5 shadow-card">
                        <div class="flex items-center justify-between gap-3">
                            <h2 class="truncate text-base font-semibold text-ink">{{ $goalRow['name'] }}</h2>
                            <span class="shrink-0 text-sm font-semibold tabular-nums text-ink">Rp {{ number_format($goalRow['total'], 0, ',', '.') }}</span>
                        </div>
                        <div class="mt-3 space-y-1.5 border-t border-hairline pt-3">
                            @foreach ($goalRow['people'] as $person)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-ink-muted">{{ $person['name'] ?? 'Pengguna' }}</span>
                                    <span class="tabular-nums text-ink">Rp {{ number_format($person['total'], 0, ',', '.') }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
