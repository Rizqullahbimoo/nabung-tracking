<?php

use App\Models\Invite;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /** The invite code currently shown to the user. */
    public ?string $code = null;

    /** When the shown code expires. */
    public ?string $expiresAt = null;

    /**
     * On load, surface the newest still-valid invite this user has created
     * so a refresh doesn't "lose" a code they haven't shared yet.
     */
    public function mount(): void
    {
        $invite = Auth::user()
            ->invitesCreated()
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->latest()
            ->first();

        if ($invite) {
            $this->code = $invite->code;
            $this->expiresAt = $invite->expires_at->toIso8601String();
        }
    }

    /**
     * Whether the current user already has an active pair.
     */
    #[Computed]
    public function paired(): bool
    {
        return Auth::user()->isPaired();
    }

    /**
     * Generate a fresh 8-character invite code valid for 24 hours.
     */
    public function generate(): void
    {
        if ($this->paired) {
            $this->addError('code', 'Kamu sudah terhubung dengan pasangan, tidak bisa membuat kode invite baru.');

            return;
        }

        $expiresAt = Carbon::now()->addHours(24);

        $invite = Auth::user()->invitesCreated()->create([
            'code' => $this->uniqueCode(),
            'status' => 'pending',
            'expires_at' => $expiresAt,
        ]);

        $this->code = $invite->code;
        $this->expiresAt = $expiresAt->toIso8601String();

        $this->dispatch('invite-created');
    }

    /**
     * Build a random code that isn't already in the invites table.
     * Ambiguous characters (0/O, 1/I) are excluded so codes are easy to read out.
     */
    protected function uniqueCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';

            for ($i = 0; $i < 8; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while (Invite::where('code', $code)->exists());

        return $code;
    }
}; ?>

<div class="bg-surface rounded-card shadow-card border border-hairline p-6 sm:p-7">
    <h3 class="text-lg font-semibold text-ink">Buat kode invite</h3>
    <p class="mt-1 text-sm text-ink-muted">
        Bagikan kode ini ke pasanganmu. Kode berlaku selama 24 jam.
    </p>

    @if ($this->paired)
        <div class="mt-5 rounded-field bg-primary-light px-4 py-3 text-sm text-primary-dark">
            Kamu sudah terhubung dengan pasangan. Opsi membuat kode invite dinonaktifkan.
        </div>
    @else
        @if ($code)
            <div class="mt-5">
                <div class="rounded-card-sm border border-dashed border-primary/40 bg-primary-light px-5 py-4 text-center">
                    <span class="block font-mono text-2xl font-bold tracking-[0.35em] text-primary-dark">
                        {{ $code }}
                    </span>
                </div>
                <p class="mt-2 text-xs text-ink-muted"
                   x-data="{ exp: '{{ $expiresAt }}' }"
                   x-text="'Berlaku sampai ' + new Date(exp).toLocaleString('id-ID')">
                </p>
            </div>

            <button type="button" wire:click="generate" wire:loading.attr="disabled"
                class="mt-5 inline-flex h-11 items-center justify-center rounded-btn border-[1.5px] border-primary px-5 text-sm font-semibold text-primary transition hover:bg-primary-light disabled:opacity-50">
                Buat kode baru
            </button>
        @else
            <button type="button" wire:click="generate" wire:loading.attr="disabled"
                class="mt-5 inline-flex h-[52px] w-full items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60 sm:w-auto">
                <span wire:loading.remove wire:target="generate">Buat Kode Invite</span>
                <span wire:loading wire:target="generate">Membuat…</span>
            </button>
        @endif

        @error('code')
            <p class="mt-3 text-sm text-accent-red">{{ $message }}</p>
        @enderror
    @endif
</div>
