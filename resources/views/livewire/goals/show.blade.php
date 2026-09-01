<?php

use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Goal $goal;

    /**
     * Load the goal and make sure it belongs to the current user's pair.
     */
    public function mount(Goal $goal): void
    {
        $pair = Auth::user()->activePair();

        abort_unless($pair && $goal->pair_id === $pair->id, 403);

        $this->goal = $goal;
    }

    /**
     * Whether the current user proposed this goal.
     */
    #[Computed]
    public function isProposer(): bool
    {
        return $this->goal->proposed_by === Auth::id();
    }

    /**
     * Whether the current user may approve/reject this goal:
     * it is still pending and they are the partner, not the proposer.
     */
    #[Computed]
    public function canDecide(): bool
    {
        return $this->goal->status === 'pending' && ! $this->isProposer;
    }

    #[Computed]
    public function collected(): float
    {
        return (float) $this->goal->contributions()->sum('amount');
    }

    #[Computed]
    public function progress(): int
    {
        $target = (float) $this->goal->target_amount;

        return $target > 0 ? min(100, (int) round($this->collected / $target * 100)) : 0;
    }

    public function approve(): void
    {
        abort_unless($this->canDecide, 403);

        $this->goal->update([
            'status' => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        session()->flash('status', 'Goal disetujui dan sekarang aktif.');
        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }

    public function reject(): void
    {
        abort_unless($this->canDecide, 403);

        $this->goal->update(['status' => 'rejected']);

        session()->flash('status', 'Usulan goal ditolak.');
        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }
}; ?>

<div class="py-10">
    <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
        <a href="{{ route('goals.index') }}" wire:navigate class="text-sm text-ink-muted transition hover:text-primary">
            &larr; Kembali ke daftar goal
        </a>

        @if (session('status'))
            <div class="mt-4 rounded-field bg-accent-green/10 px-4 py-3 text-sm font-medium text-accent-green">
                {{ session('status') }}
            </div>
        @endif

        <div class="mt-4 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h1 class="text-xl font-bold text-ink">{{ $goal->name }}</h1>
                    <p class="mt-1 text-sm text-ink-muted">{{ $goal->category ?? 'Tanpa kategori' }}</p>
                </div>
                <x-goal-status-badge :status="$goal->status" />
            </div>

            <div class="mt-6">
                <div class="h-2 overflow-hidden rounded-full bg-hairline">
                    <div class="h-full rounded-full bg-accent-green" style="width: {{ $this->progress }}%"></div>
                </div>
                <p class="mt-2 text-sm tabular-nums text-ink-muted">
                    <span class="font-semibold text-ink">Rp {{ number_format($this->collected, 0, ',', '.') }}</span>
                    / Rp {{ number_format((float) $goal->target_amount, 0, ',', '.') }}
                    <span class="ms-1">({{ $this->progress }}%)</span>
                </p>
            </div>

            <dl class="mt-6 grid grid-cols-2 gap-4 border-t border-hairline pt-6 text-sm">
                <div>
                    <dt class="text-xs text-ink-muted">Target nominal</dt>
                    <dd class="mt-0.5 font-semibold tabular-nums text-ink">Rp {{ number_format((float) $goal->target_amount, 0, ',', '.') }}</dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-muted">Tenggat</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        {{ $goal->target_date ? $goal->target_date->translatedFormat('d F Y') : 'Tanpa tenggat' }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-muted">Diusulkan oleh</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        {{ $this->isProposer ? 'Kamu' : optional($goal->proposedBy)->name }}
                    </dd>
                </div>
                <div>
                    <dt class="text-xs text-ink-muted">Disetujui oleh</dt>
                    <dd class="mt-0.5 font-medium text-ink">
                        {{ optional($goal->approvedBy)->name ?? '—' }}
                    </dd>
                </div>
            </dl>

            @if ($goal->status === 'pending')
                <div class="mt-6 border-t border-hairline pt-6">
                    @if ($this->canDecide)
                        <p class="text-sm text-ink-muted">Pasanganmu mengusulkan goal ini. Setujui untuk mengaktifkannya.</p>
                        <div class="mt-3 flex items-center gap-3">
                            <button wire:click="approve"
                                    class="inline-flex h-11 items-center justify-center rounded-btn bg-primary px-5 text-sm font-semibold text-white transition hover:bg-primary-dark">
                                Setujui
                            </button>
                            <button wire:click="reject"
                                    class="inline-flex h-11 items-center justify-center rounded-btn bg-accent-red px-5 text-sm font-semibold text-white transition hover:opacity-90">
                                Tolak
                            </button>
                        </div>
                    @else
                        <div class="rounded-field bg-accent-orange/10 px-4 py-3 text-sm font-medium text-accent-orange">
                            Menunggu persetujuan pasangan.
                        </div>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
