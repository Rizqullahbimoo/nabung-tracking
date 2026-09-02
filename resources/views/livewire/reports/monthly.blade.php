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
     * Deposit vs withdrawal totals for the 6 months ending at the selected
     * month (oldest first). Grouped in PHP so it works on any DB driver.
     *
     * @return \Illuminate\Support\Collection<int, array{key: string, label: string, deposit: float, withdrawal: float}>
     */
    #[Computed]
    public function trend()
    {
        $monthsBack = 6;

        $buckets = collect(range($monthsBack - 1, 0))->map(function ($offset) {
            $month = $this->period->copy()->subMonthsNoOverflow($offset);

            return [
                'key' => $month->format('Y-m'),
                'label' => $month->translatedFormat('M'),
                'deposit' => 0.0,
                'withdrawal' => 0.0,
            ];
        })->keyBy('key');

        if (! $this->pair) {
            return $buckets->values();
        }

        $start = $this->period->copy()->subMonthsNoOverflow($monthsBack - 1)->startOfMonth();
        $end = $this->period->copy()->endOfMonth();

        $rows = Contribution::query()
            ->whereHas('goal', fn ($query) => $query->where('pair_id', $this->pair->id))
            ->whereBetween('contributed_at', [$start->toDateString(), $end->toDateString()])
            ->get(['type', 'amount', 'contributed_at']);

        foreach ($rows as $row) {
            $key = $row->contributed_at->format('Y-m');

            if (! $buckets->has($key) || ! in_array($row->type, ['deposit', 'withdrawal'], true)) {
                continue;
            }

            $bucket = $buckets->get($key);
            $bucket[$row->type] += (float) $row->amount;
            $buckets->put($key, $bucket);
        }

        return $buckets->values();
    }

    /**
     * Tallest bar value, used to scale the chart heights.
     */
    #[Computed]
    public function trendMax(): float
    {
        return (float) $this->trend
            ->flatMap(fn ($bucket) => [$bucket['deposit'], $bucket['withdrawal']])
            ->max();
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
        return (float) $this->contributions->sum(fn ($c) => $c->signedAmount());
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
            'total' => (float) ($byUser->get($member->id)?->sum(fn ($c) => $c->signedAmount()) ?? 0),
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
                    'total' => (float) $rows->sum(fn ($c) => $c->signedAmount()),
                    'people' => $this->members->map(fn ($member) => [
                        'name' => $member->name,
                        'total' => (float) ($byUser->get($member->id)?->sum(fn ($c) => $c->signedAmount()) ?? 0),
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

        {{-- Trend: contributions vs withdrawals, last 6 months (design.md accent-green / accent-red) --}}
        <div class="mt-6 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7">
            <h2 class="text-lg font-semibold text-ink">Tren 6 bulan terakhir</h2>
            <p class="mt-1 text-xs text-ink-muted">Total kontribusi dan penarikan per bulan.</p>

            @if ($this->trendMax <= 0)
                <p class="mt-6 text-sm text-ink-muted">
                    Belum ada kontribusi maupun penarikan dalam 6 bulan terakhir.
                </p>
            @else
                <div class="mt-6 flex items-end gap-2 sm:gap-3" style="height: 160px">
                    @foreach ($this->trend as $bucket)
                        @php
                            $depositPct = (int) round($bucket['deposit'] / $this->trendMax * 100);
                            $withdrawalPct = (int) round($bucket['withdrawal'] / $this->trendMax * 100);
                        @endphp
                        <div class="flex h-full flex-1 items-end justify-center gap-1">
                            <div class="w-2.5 rounded-t bg-accent-green sm:w-3 {{ $bucket['deposit'] > 0 ? 'min-h-[3px]' : '' }}"
                                 style="height: {{ $depositPct }}%"
                                 title="{{ $bucket['label'] }} — Kontribusi: Rp {{ number_format($bucket['deposit'], 0, ',', '.') }}"></div>
                            <div class="w-2.5 rounded-t bg-accent-red sm:w-3 {{ $bucket['withdrawal'] > 0 ? 'min-h-[3px]' : '' }}"
                                 style="height: {{ $withdrawalPct }}%"
                                 title="{{ $bucket['label'] }} — Penarikan: Rp {{ number_format($bucket['withdrawal'], 0, ',', '.') }}"></div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-2 border-t border-hairline pt-2 sm:gap-3">
                    @foreach ($this->trend as $bucket)
                        <div class="flex-1 text-center text-xs text-ink-muted">{{ $bucket['label'] }}</div>
                    @endforeach
                </div>
                <div class="mt-4 flex items-center gap-4 text-xs text-ink-muted">
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-accent-green"></span> Kontribusi
                    </span>
                    <span class="flex items-center gap-1.5">
                        <span class="inline-block h-2.5 w-2.5 rounded-sm bg-accent-red"></span> Penarikan
                    </span>
                </div>
            @endif
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
