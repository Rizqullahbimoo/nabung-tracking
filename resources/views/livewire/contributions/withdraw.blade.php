<?php

use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public Goal $goal;

    public string $amount = '';
    public string $note = '';
    public string $withdrawn_at = '';

    /**
     * The goal must belong to the current user's active pair.
     */
    public function mount(Goal $goal): void
    {
        abort_unless($goal->pair_id === Auth::user()->activePair()->id, 403);

        $this->goal = $goal;
        $this->withdrawn_at = now()->toDateString();
    }

    /**
     * Record a "withdrawal" (positive amount, subtracted from the balance),
     * then re-sync the goal status in case it drops below target.
     */
    public function save(): void
    {
        abort_unless(in_array($this->goal->status, ['active', 'achieved'], true), 403);

        // The nominal field shows thousand separators; keep only digits.
        $this->amount = preg_replace('/\D/', '', (string) $this->amount);

        $available = $this->goal->collectedAmount();

        if ($available <= 0) {
            $this->addError('amount', 'Saldo goal masih Rp 0, belum ada yang bisa ditarik.');

            return;
        }

        $validated = $this->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:'.(int) floor($available)],
            'note' => ['required', 'string', 'max:255'],
            'withdrawn_at' => ['required', 'date', 'before_or_equal:today'],
        ], messages: [
            'amount.max' => 'Nominal penarikan tidak boleh melebihi saldo goal (Rp :max).',
            'note.required' => 'Keterangan penarikan wajib diisi.',
        ], attributes: [
            'amount' => 'nominal',
            'note' => 'keterangan',
            'withdrawn_at' => 'tanggal',
        ]);

        $this->goal->contributions()->create([
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'type' => 'withdrawal',
            'note' => $validated['note'],
            'contributed_at' => $validated['withdrawn_at'],
        ]);

        $this->goal->syncAchievedStatus();

        session()->flash('status', 'Penarikan dana dicatat.');

        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }
}; ?>

<form wire:submit="save" class="mt-4 space-y-4 rounded-card-sm border border-accent-red/30 bg-accent-red/5 p-5">
    <p class="text-xs text-ink-muted">
        Saldo saat ini: <span class="font-semibold tabular-nums text-ink">Rp {{ number_format($goal->collectedAmount(), 0, ',', '.') }}</span>
    </p>

    <div x-data="{
            raw: @js((string) $amount),
            get formatted() {
                return this.raw !== '' ? new Intl.NumberFormat('id-ID').format(Number(this.raw)) : '';
            },
            onInput(event) {
                this.raw = event.target.value.replace(/\D/g, '');
                event.target.value = this.formatted;
                $wire.set('amount', this.raw, false);
            }
        }">
        <label for="withdraw-amount" class="block text-xs font-medium text-ink-muted">Nominal penarikan (Rp)</label>
        <div class="mt-1 flex items-center border-b border-hairline focus-within:border-accent-red">
            <span class="pe-2 text-ink-muted">Rp</span>
            <input id="withdraw-amount" type="text" inputmode="numeric" autocomplete="off" required
                   placeholder="250.000"
                   :value="formatted"
                   x-on:input="onInput($event)"
                   class="w-full border-0 bg-transparent px-0 py-2 font-semibold tabular-nums text-ink placeholder:text-ink-disabled focus:ring-0">
        </div>
        @error('amount') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="withdraw-note" class="block text-xs font-medium text-ink-muted">Keterangan / alasan <span class="text-accent-red">*</span></label>
        <input wire:model="note" id="withdraw-note" type="text" maxlength="255" required
               placeholder="mis. DP tiket pesawat"
               class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-accent-red focus:ring-0">
        @error('note') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="withdraw-date" class="block text-xs font-medium text-ink-muted">Tanggal</label>
        <input wire:model="withdrawn_at" id="withdraw-date" type="date" max="{{ now()->toDateString() }}"
               class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink focus:border-accent-red focus:ring-0">
        @error('withdrawn_at') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-3 pt-1">
        <button type="submit"
                class="inline-flex h-11 items-center justify-center rounded-btn bg-accent-red px-5 text-sm font-semibold text-white transition hover:opacity-90">
            Catat Penarikan
        </button>
        <button type="button" x-on:click="$dispatch('close-contribution-form')"
                class="text-sm font-medium text-ink-muted transition hover:text-primary">
            Batal
        </button>
    </div>
</form>
