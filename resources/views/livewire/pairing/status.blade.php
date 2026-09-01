<?php

use App\Models\Pair;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * The current user's active pair (with both members eager-loaded).
     */
    #[Computed]
    public function pair(): ?Pair
    {
        return Pair::query()
            ->active()
            ->forUser(Auth::user())
            ->with(['userOne', 'userTwo'])
            ->first();
    }

    /**
     * The partner within the active pair, if any.
     */
    #[Computed]
    public function partner()
    {
        return $this->pair?->partnerOf(Auth::user());
    }

    /**
     * Re-render when a child component reports a pairing change.
     */
    #[On('invite-created')]
    public function refresh(): void
    {
        unset($this->pair, $this->partner);
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
        </div>
    @else
        <div class="space-y-2">
            <h2 class="text-xl font-bold text-ink">Hubungkan dengan pasanganmu</h2>
            <p class="text-sm text-ink-muted">
                Buat kode invite lalu bagikan ke pasanganmu, atau masukkan kode yang kamu terima.
            </p>
        </div>

        <div class="mt-5 grid gap-4 sm:grid-cols-2">
            <livewire:pairing.create-invite />
            <livewire:pairing.accept-invite />
        </div>
    @endif
</div>
