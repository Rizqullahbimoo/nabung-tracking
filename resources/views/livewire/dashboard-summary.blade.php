<?php

use App\Models\Contribution;
use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    #[Computed]
    public function pair()
    {
        return Auth::user()->activePair();
    }

    /**
     * Active goals for the pair, each with its contribution total attached.
     */
    #[Computed]
    public function activeGoals()
    {
        if (! $this->pair) {
            return collect();
        }

        return Goal::query()
            ->where('pair_id', $this->pair->id)
            ->where('status', 'active')
            ->withSum('contributions as collected', 'amount')
            ->get();
    }

    #[Computed]
    public function activeCount(): int
    {
        return $this->activeGoals->count();
    }

    #[Computed]
    public function pendingCount(): int
    {
        return $this->pair
            ? Goal::where('pair_id', $this->pair->id)->where('status', 'pending')->count()
            : 0;
    }

    /**
     * Everything the pair has saved across all of its goals.
     */
    #[Computed]
    public function totalSaved(): float
    {
        if (! $this->pair) {
            return 0.0;
        }

        return (float) Contribution::whereHas('goal', fn ($query) => $query->where('pair_id', $this->pair->id))
            ->sum('amount');
    }

    /**
     * Combined progress across active goals (collected / target).
     */
    #[Computed]
    public function overallProgress(): int
    {
        $target = (float) $this->activeGoals->sum(fn ($goal) => (float) $goal->target_amount);

        if ($target <= 0) {
            return 0;
        }

        $collected = (float) $this->activeGoals->sum(fn ($goal) => (float) ($goal->collected ?? 0));

        return min(100, (int) floor($collected / $target * 100));
    }

    /**
     * The 5 most recent contributions across all of the pair's goals.
     */
    #[Computed]
    public function recentContributions()
    {
        if (! $this->pair) {
            return collect();
        }

        return Contribution::query()
            ->whereHas('goal', fn ($query) => $query->where('pair_id', $this->pair->id))
            ->with(['user:id,name', 'goal:id,name'])
            ->latest('contributed_at')
            ->latest('id')
            ->limit(5)
            ->get();
    }
}; ?>

<div>
    {{-- Hero card (design.md §6.1) --}}
    <div class="rounded-card bg-gradient-to-br from-primary to-primary-dark p-6 text-white shadow-card-elevated sm:p-7">
        <p class="text-sm text-white/70">Total Tabungan</p>
        <p class="mt-1 text-3xl font-bold tabular-nums">Rp {{ number_format($this->totalSaved, 0, ',', '.') }}</p>

        @if ($this->activeGoals->isNotEmpty())
            <div class="mt-4">
                <div class="h-1.5 overflow-hidden rounded-full bg-white/20">
                    <div class="h-full rounded-full bg-white" style="width: {{ $this->overallProgress }}%"></div>
                </div>
                <p class="mt-1.5 text-xs text-white/70">{{ $this->overallProgress }}% dari total target goal aktif</p>
            </div>
        @endif

        <div class="mt-5 flex gap-6 border-t border-white/15 pt-4 text-sm text-white/80">
            <p><span class="text-lg font-bold tabular-nums text-white">{{ $this->activeCount }}</span> goal aktif</p>
            <p><span class="text-lg font-bold tabular-nums text-white">{{ $this->pendingCount }}</span> menunggu persetujuan</p>
        </div>
    </div>

    {{-- Recent contributions --}}
    <div class="mt-6 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7">
        <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-ink">Kontribusi terbaru</h2>
            <a href="{{ route('goals.index') }}" wire:navigate class="text-sm font-medium text-primary transition hover:text-primary-dark">
                Lihat semua goal
            </a>
        </div>

        @if ($this->recentContributions->isEmpty())
            <p class="mt-4 text-sm text-ink-muted">Belum ada kontribusi tercatat.</p>
        @else
            <ul class="mt-4 space-y-3">
                @foreach ($this->recentContributions as $item)
                    <li class="flex items-center gap-3">
                        <x-user-avatar :name="optional($item->user)->name" :id="$item->user_id" size="md" />
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium text-ink">{{ optional($item->goal)->name ?? 'Goal' }}</p>
                            <p class="truncate text-xs text-ink-muted">
                                {{ optional($item->user)->name ?? 'Pengguna' }} &middot; {{ $item->contributed_at->translatedFormat('d M Y') }}
                            </p>
                        </div>
                        <span class="shrink-0 text-sm font-semibold tabular-nums text-accent-green">
                            + Rp {{ number_format((float) $item->amount, 0, ',', '.') }}
                        </span>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
