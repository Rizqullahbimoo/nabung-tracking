<?php

use App\Models\Goal;
use App\Support\Notifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public Goal $goal;

    public string $amount = '';
    public ?string $note = '';
    public string $contributed_at = '';

    /**
     * The goal must belong to the current user's active pair.
     */
    public function mount(Goal $goal): void
    {
        abort_unless($goal->pair_id === Auth::user()->activePair()->id, 403);

        $this->goal = $goal;
        $this->contributed_at = now()->toDateString();
    }

    /**
     * Record a "deposit" contribution and flip the goal to achieved if the
     * target has been reached.
     */
    public function save(): void
    {
        abort_unless($this->goal->status === 'active', 403);

        // The nominal field shows thousand separators; keep only digits.
        $this->amount = preg_replace('/\D/', '', (string) $this->amount);

        $validated = $this->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'note' => ['nullable', 'string', 'max:255'],
            'contributed_at' => ['required', 'date', 'before_or_equal:today'],
        ], attributes: [
            'amount' => 'nominal',
            'note' => 'catatan',
            'contributed_at' => 'tanggal kontribusi',
        ]);

        $contribution = $this->goal->contributions()->create([
            'user_id' => Auth::id(),
            'amount' => $validated['amount'],
            'type' => 'deposit',
            'note' => $validated['note'] ?: null,
            'contributed_at' => $validated['contributed_at'],
        ]);

        $this->goal->syncAchievedStatus();

        Notifier::contributionAdded($this->goal, $contribution);

        session()->flash('status', 'Kontribusi berhasil dicatat.');

        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }
}; ?>

<form wire:submit="save" class="mt-4 space-y-4 rounded-card-sm border border-hairline bg-canvas p-5">
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
        <label for="contribution-amount" class="block text-xs font-medium text-ink-muted">Nominal (Rp)</label>
        <div class="mt-1 flex items-center border-b border-hairline focus-within:border-primary">
            <span class="pe-2 text-ink-muted">Rp</span>
            <input id="contribution-amount" type="text" inputmode="numeric" autocomplete="off" required
                   placeholder="500.000"
                   :value="formatted"
                   x-on:input="onInput($event)"
                   class="w-full border-0 bg-transparent px-0 py-2 font-semibold tabular-nums text-ink placeholder:text-ink-disabled focus:ring-0">
        </div>
        @error('amount') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="contribution-note" class="block text-xs font-medium text-ink-muted">Catatan <span class="text-ink-disabled">(opsional)</span></label>
        <input wire:model="note" id="contribution-note" type="text" maxlength="255"
               placeholder="mis. Gajian bulan ini"
               class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0">
        @error('note') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="contribution-date" class="block text-xs font-medium text-ink-muted">Tanggal kontribusi</label>
        <input wire:model="contributed_at" id="contribution-date" type="date" max="{{ now()->toDateString() }}"
               class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink focus:border-primary focus:ring-0">
        @error('contributed_at') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-3 pt-1">
        <button type="submit"
                class="inline-flex h-11 items-center justify-center rounded-btn bg-primary px-5 text-sm font-semibold text-white transition hover:bg-primary-dark">
            Simpan Kontribusi
        </button>
        <button type="button" x-on:click="$dispatch('close-contribution-form')"
                class="text-sm font-medium text-ink-muted transition hover:text-primary">
            Batal
        </button>
    </div>
</form>
