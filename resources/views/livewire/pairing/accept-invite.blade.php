<?php

use App\Models\Invite;
use App\Models\Pair;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /** Invite code typed by the user. */
    public string $code = '';

    /**
     * Normalise the code as the user types: upper-case, no spaces.
     */
    public function updatedCode(string $value): void
    {
        $this->code = strtoupper(preg_replace('/\s+/', '', $value));
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
     * Validate the code and, if everything checks out, create the pair.
     */
    public function redeem(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:8'],
        ], attributes: ['code' => 'kode invite']);

        $user = Auth::user();

        if ($user->isPaired()) {
            $this->addError('code', 'Kamu sudah terhubung dengan pasangan.');

            return;
        }

        $invite = Invite::where('code', $this->code)->first();

        if (! $invite) {
            $this->addError('code', 'Kode invite tidak ditemukan.');

            return;
        }

        if ($invite->status !== 'pending') {
            $this->addError('code', 'Kode invite ini sudah dipakai atau tidak berlaku lagi.');

            return;
        }

        if ($invite->expires_at->isPast()) {
            $this->addError('code', 'Kode invite sudah kedaluwarsa.');

            return;
        }

        if ($invite->created_by === $user->id) {
            $this->addError('code', 'Kamu tidak bisa memakai kode invite buatanmu sendiri.');

            return;
        }

        $inviter = User::find($invite->created_by);

        if (! $inviter || $inviter->isPaired()) {
            $this->addError('code', 'Pembuat kode ini sudah terhubung dengan pengguna lain.');

            return;
        }

        DB::transaction(function () use ($invite, $inviter, $user) {
            // Retire both people's solo pairs. They are kept as history (their
            // goals & contributions stay attached) but are no longer "active".
            Pair::query()
                ->where('status', 'active')
                ->whereNull('user_two_id')
                ->whereIn('user_one_id', [$inviter->id, $user->id])
                ->update([
                    'status' => 'unpaired',
                    'unpaired_at' => now(),
                ]);

            Pair::create([
                'user_one_id' => $inviter->id,
                'user_two_id' => $user->id,
                'status' => 'active',
                'paired_at' => now(),
            ]);

            $invite->update([
                'status' => 'accepted',
                'accepted_by' => $user->id,
            ]);
        });

        session()->flash('status', 'Berhasil terhubung dengan ' . $inviter->name . '.');

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<div class="bg-surface rounded-card shadow-card border border-hairline p-6 sm:p-7">
    <h3 class="text-lg font-semibold text-ink">Punya kode invite?</h3>
    <p class="mt-1 text-sm text-ink-muted">
        Masukkan kode 8 karakter dari pasanganmu untuk menghubungkan akun.
    </p>

    @if ($this->paired)
        <div class="mt-5 rounded-field bg-primary-light px-4 py-3 text-sm text-primary-dark">
            Kamu sudah terhubung dengan pasangan.
        </div>
    @else
        <form wire:submit="redeem" class="mt-5 space-y-4">
            <div>
                <label for="invite-code" class="block text-xs font-medium text-ink-muted">Kode invite</label>
                <input
                    id="invite-code"
                    type="text"
                    wire:model.live="code"
                    maxlength="8"
                    autocomplete="off"
                    placeholder="ABCD2345"
                    class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 font-mono text-lg uppercase tracking-[0.3em] text-ink placeholder:text-ink-disabled placeholder:tracking-[0.3em] focus:border-primary focus:ring-0"
                />
                @error('code')
                    <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
                @enderror
            </div>

            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="redeem"
                class="inline-flex h-[52px] w-full items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark disabled:opacity-60 sm:w-auto">
                <span wire:loading.remove wire:target="redeem">Hubungkan Akun</span>
                <span wire:loading wire:target="redeem">Memproses…</span>
            </button>
        </form>
    @endif
</div>
