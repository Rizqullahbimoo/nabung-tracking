<?php

use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Arsip Goal — Nabung Tracking')] class extends Component
{
    /**
     * Read-only goals from every pair of the current user that is no longer
     * active (status = 'unpaired') - superseded solo pairs, and, in the
     * future, couples that have unpaired.
     */
    #[Computed]
    public function goals()
    {
        $user = Auth::user();

        return Goal::query()
            ->whereHas('pair', fn ($query) => $query->where('status', 'unpaired')->forUser($user))
            ->with('pair')
            ->withSum('contributions as collected', 'amount')
            ->latest()
            ->get();
    }
}; ?>

<div class="py-10">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div>
            <a href="{{ route('goals.index') }}" wire:navigate class="text-sm text-ink-muted transition hover:text-primary">
                &larr; Kembali ke daftar goal
            </a>
            <h1 class="mt-2 text-xl font-bold text-ink">Arsip Goal</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Goal dari pairing yang sudah tidak aktif. Hanya bisa dilihat &mdash; tidak bisa diubah.
            </p>
        </div>

        @if ($this->goals->isEmpty())
            <div class="mt-6 rounded-card border border-hairline bg-surface p-8 text-center shadow-card">
                <p class="text-base font-semibold text-ink">Belum ada goal terarsip</p>
                <p class="mt-1 text-sm text-ink-muted">
                    Goal akan muncul di sini kalau kamu pernah menabung di mode solo lalu terhubung
                    dengan pasangan, atau memutus pairing.
                </p>
            </div>
        @else
            <div class="mt-6 space-y-3">
                @foreach ($this->goals as $goal)
                    @php
                        $collected = (float) ($goal->collected ?? 0);
                        $target = (float) $goal->target_amount;
                        $pct = $target > 0 ? min(100, (int) round($collected / $target * 100)) : 0;
                        $fromSolo = $goal->pair?->isSolo();
                    @endphp

                    <div class="rounded-card-sm border border-hairline bg-surface p-5 opacity-75">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="truncate text-base font-semibold text-ink">{{ $goal->name }}</h2>
                                <p class="mt-0.5 text-xs text-ink-muted">{{ $goal->category ?? 'Tanpa kategori' }}</p>
                            </div>
                            <div class="flex shrink-0 flex-col items-end gap-1.5">
                                <x-goal-status-badge :status="$goal->status" />
                                <span class="inline-flex items-center rounded-full bg-ink-disabled/15 px-2.5 py-1 text-xs font-medium text-ink-muted">
                                    {{ $fromSolo ? 'Dari mode solo' : 'Dari pairing sebelumnya' }}
                                </span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <div class="h-1.5 overflow-hidden rounded-full bg-hairline">
                                <div class="h-full rounded-full bg-ink-disabled" style="width: {{ $pct }}%"></div>
                            </div>
                            <p class="mt-2 text-sm tabular-nums text-ink-muted">
                                <span class="font-semibold text-ink">Rp {{ number_format($collected, 0, ',', '.') }}</span>
                                terkumpul dari target Rp {{ number_format($target, 0, ',', '.') }} ({{ $pct }}%)
                            </p>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
