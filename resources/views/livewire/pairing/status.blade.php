<?php

use App\Models\Pair;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * The current user's active pair (solo or coupled) - always present.
     */
    #[Computed]
    public function pair(): Pair
    {
        return Auth::user()->activePair();
    }

    /**
     * The partner within the active pair, or null when the user is still solo.
     */
    #[Computed]
    public function partner(): ?User
    {
        return $this->pair->partnerOf(Auth::user());
    }

    /**
     * Re-render when a child component reports a pairing change.
     */
    #[On('invite-created')]
    public function refresh(): void
    {
        unset($this->pair, $this->partner);
    }

    /**
     * Break up the couple (F-12, PRD 6.1). The coupled pair is retired - its
     * goals & contributions stay attached and become the read-only archive -
     * and each member gets a fresh solo pair to keep saving on their own.
     * Only the current user's own coupled pair can be unpaired.
     */
    public function unpair(): void
    {
        $pair = Auth::user()->activePair();

        abort_unless(! $pair->isSolo(), 403);

        $one = $pair->userOne;
        $two = $pair->userTwo;

        DB::transaction(function () use ($pair, $one, $two) {
            $pair->retire();
            $one->createSoloPair();
            $two->createSoloPair();
        });

        session()->flash('status', 'Pairing diputuskan. Semua goal kalian sudah diarsipkan dan menjadi read-only.');

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div>
    @if (session('status'))
        <div class="mb-4 rounded-field bg-accent-green/10 px-4 py-3 text-sm font-medium text-accent-green">
            {{ session('status') }}
        </div>
    @endif

    @if ($this->partner)
        @php
            $partner = $this->partner;
            $initials = collect(explode(' ', trim($partner->name)))
                ->filter()
                ->take(2)
                ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
                ->implode('');
            $palette = ['bg-accent-green', 'bg-accent-orange', 'bg-accent-purple', 'bg-primary'];
            $avatarBg = $palette[$partner->id % count($palette)];
        @endphp

        <div class="bg-surface rounded-card shadow-card border border-hairline p-6 sm:p-7">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-accent-green/10 px-3 py-1 text-xs font-medium text-accent-green">
                <span class="h-1.5 w-1.5 rounded-full bg-accent-green"></span>
                Terhubung
            </span>

            <div class="mt-4 flex items-center gap-4">
                @if ($partner->avatar_url ?? false)
                    <img src="{{ $partner->avatar_url }}" alt="{{ $partner->name }}"
                         class="h-14 w-14 rounded-full object-cover">
                @else
                    <span class="flex h-14 w-14 items-center justify-center rounded-full {{ $avatarBg }} text-lg font-semibold text-white">
                        {{ $initials }}
                    </span>
                @endif

                <div>
                    <p class="text-base font-semibold text-ink">{{ $partner->name }}</p>
                    <p class="text-sm text-ink-muted">
                        Terhubung sejak {{ $this->pair->paired_at->translatedFormat('d F Y') }}
                    </p>
                </div>
            </div>

            {{-- Unpair - rare, destructive; quiet until confirmed (design.md) --}}
            <div class="mt-5 border-t border-hairline pt-4" x-data="{ confirming: false }">
                <template x-if="!confirming">
                    <button type="button" x-on:click="confirming = true"
                            class="text-sm font-medium text-ink-muted transition hover:text-accent-red">
                        Putuskan pairing
                    </button>
                </template>
                <template x-if="confirming">
                    <div class="space-y-3">
                        <p class="text-sm text-ink">
                            Yakin memutuskan pairing dengan <span class="font-semibold">{{ $partner->name }}</span>?
                            Semua goal kalian akan <span class="font-semibold">diarsipkan dan menjadi read-only</span> &mdash;
                            tetap bisa dilihat di Arsip, tapi tidak bisa lagi menambah kontribusi.
                        </p>
                        <div class="flex items-center gap-3">
                            <button type="button" wire:click="unpair" wire:loading.attr="disabled"
                                    class="inline-flex h-10 items-center justify-center rounded-btn bg-accent-red px-4 text-sm font-semibold text-white transition hover:opacity-90 disabled:opacity-60">
                                Ya, putuskan
                            </button>
                            <button type="button" x-on:click="confirming = false"
                                    class="text-sm font-medium text-ink-muted transition hover:text-primary">
                                Batal
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    @else
        <div class="space-y-2">
            <div class="flex items-center gap-2">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-primary-light px-3 py-1 text-xs font-medium text-primary-dark">
                    <span class="h-1.5 w-1.5 rounded-full bg-primary"></span>
                    Mode solo
                </span>
            </div>
            <h2 class="text-xl font-bold text-ink">Menabung sendiri dulu</h2>
            <p class="text-sm text-ink-muted">
                Kamu bisa langsung membuat goal dan mencatat kontribusi. Kapan pun siap, undang
                pasangan untuk menabung bareng &mdash; opsional.
            </p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <livewire:pairing.create-invite />
            <livewire:pairing.accept-invite />
        </div>
    @endif
</div>
