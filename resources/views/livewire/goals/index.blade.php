<?php

use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Goals — Nabung Tracking')] class extends Component
{
    /**
     * The current user's active pair (solo or coupled) - always present.
     */
    #[Computed]
    public function pair()
    {
        return Auth::user()->activePair();
    }

    /**
     * Goals for the pair, with the contribution total attached as "collected".
     */
    #[Computed]
    public function goals()
    {
        return Goal::query()
            ->where('pair_id', $this->pair->id)
            ->withSum('contributions as collected', 'amount')
            ->latest()
            ->get();
    }
}; ?>

<div class="py-10">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between">
            <h1 class="text-xl font-bold text-ink">Goal Tabungan</h1>
            <a href="{{ route('goals.create') }}" wire:navigate
               class="inline-flex h-11 items-center justify-center rounded-btn bg-primary px-5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                + Buat Goal
            </a>
        </div>

        @if (session('status'))
            <div class="mt-4 rounded-field bg-accent-green/10 px-4 py-3 text-sm font-medium text-accent-green">
                {{ session('status') }}
            </div>
        @endif

        @if ($this->goals->isEmpty())
            <div class="mt-6 rounded-card border border-hairline bg-surface p-8 text-center shadow-card">
                <p class="text-base font-semibold text-ink">Belum ada goal</p>
                <p class="mt-1 text-sm text-ink-muted">Buat goal pertamamu, mis. "Dana Nikah" atau "Liburan".</p>
            </div>
        @else
            <div class="mt-6 space-y-3">
                @foreach ($this->goals as $goal)
                    @php
                        $collected = (float) ($goal->collected ?? 0);
                        $target = (float) $goal->target_amount;
                        $pct = $target > 0 ? min(100, (int) round($collected / $target * 100)) : 0;
                    @endphp

                    <a href="{{ route('goals.show', $goal) }}" wire:navigate
                       class="block rounded-card-sm border border-hairline bg-surface p-5 shadow-card transition hover:border-primary/40">
                        <div class="flex items-start gap-4">
                            <span class="mt-0.5 flex h-11 w-11 shrink-0 items-center justify-center rounded-full bg-primary-light text-primary">
                                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                    <circle cx="12" cy="12" r="9" />
                                    <circle cx="12" cy="12" r="5" />
                                    <circle cx="12" cy="12" r="1" />
                                </svg>
                            </span>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center justify-between gap-3">
                                    <h2 class="truncate text-base font-semibold text-ink">{{ $goal->name }}</h2>
                                    <x-goal-status-badge :status="$goal->status" />
                                </div>

                                <p class="mt-0.5 text-xs text-ink-muted">
                                    {{ $goal->category ?? 'Tanpa kategori' }}
                                    @if ($goal->target_date)
                                        &middot; tenggat {{ $goal->target_date->translatedFormat('d M Y') }}
                                    @endif
                                </p>

                                <div class="mt-3 flex items-center gap-3">
                                    <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-hairline">
                                        <div class="h-full rounded-full bg-accent-green" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-xs font-medium tabular-nums text-ink-muted">{{ $pct }}%</span>
                                </div>

                                <p class="mt-2 text-sm tabular-nums text-ink-muted">
                                    <span class="font-semibold text-ink">Rp {{ number_format($collected, 0, ',', '.') }}</span>
                                    / Rp {{ number_format($target, 0, ',', '.') }}
                                </p>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        @endif

        <div class="mt-8 text-center">
            <a href="{{ route('goals.archive') }}" wire:navigate
               class="text-xs text-ink-muted transition hover:text-primary">
                Lihat arsip goal
            </a>
        </div>
    </div>
</div>
