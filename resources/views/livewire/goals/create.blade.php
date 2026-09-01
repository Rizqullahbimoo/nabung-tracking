<?php

use App\Models\Goal;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] #[Title('Usulkan Goal')] class extends Component
{
    public const CATEGORIES = ['Liburan', 'Rumah', 'Pernikahan', 'Lainnya'];

    public string $name = '';
    public string $category = '';
    public string $target_amount = '';
    public ?string $target_date = null;

    /**
     * Only a paired user may propose a goal.
     */
    public function mount(): void
    {
        abort_unless(Auth::user()->isPaired(), 403);
    }

    /**
     * Store the proposed goal as "pending" and go back to the list.
     */
    public function save(): void
    {
        $pair = Auth::user()->activePair();

        abort_unless($pair, 403);

        // The nominal field is shown with thousand separators ("1.000.000");
        // keep only digits before validating/storing.
        $this->target_amount = preg_replace('/\D/', '', (string) $this->target_amount);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::in(self::CATEGORIES)],
            'target_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'target_date' => ['nullable', 'date', 'after_or_equal:today'],
        ], attributes: [
            'name' => 'nama goal',
            'target_amount' => 'target nominal',
            'target_date' => 'target tanggal',
        ]);

        Goal::create([
            'pair_id' => $pair->id,
            'proposed_by' => Auth::id(),
            'name' => $validated['name'],
            'category' => $validated['category'] ?: null,
            'target_amount' => $validated['target_amount'],
            'target_date' => $validated['target_date'] ?: null,
            'status' => 'pending',
        ]);

        session()->flash('status', 'Usulan goal terkirim. Menunggu persetujuan pasangan.');

        $this->redirect(route('goals.index'), navigate: true);
    }
}; ?>

<div class="py-10">
    <div class="mx-auto max-w-xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="{{ route('goals.index') }}" wire:navigate class="text-sm text-ink-muted transition hover:text-primary">
                &larr; Kembali ke daftar goal
            </a>
            <h1 class="mt-2 text-xl font-bold text-ink">Usulkan goal baru</h1>
            <p class="mt-1 text-sm text-ink-muted">
                Goal akan berstatus <span class="font-medium">Pending Approval</span> sampai pasanganmu menyetujui.
            </p>
        </div>

        <form wire:submit="save"
              class="space-y-5 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7">
            <div>
                <label for="name" class="block text-xs font-medium text-ink-muted">Nama goal</label>
                <input wire:model="name" id="name" type="text" required autofocus
                       placeholder="mis. Dana Nikah"
                       class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0">
                @error('name') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="category" class="block text-xs font-medium text-ink-muted">Kategori <span class="text-ink-disabled">(opsional)</span></label>
                <select wire:model="category" id="category"
                        class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink focus:border-primary focus:ring-0">
                    <option value="">— Tidak ada —</option>
                    @foreach (self::CATEGORIES as $option)
                        <option value="{{ $option }}">{{ $option }}</option>
                    @endforeach
                </select>
                @error('category') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
            </div>

            <div x-data="{
                    raw: @js((string) $target_amount),
                    get formatted() {
                        return this.raw !== '' ? new Intl.NumberFormat('id-ID').format(Number(this.raw)) : '';
                    },
                    onInput(event) {
                        this.raw = event.target.value.replace(/\D/g, '');
                        event.target.value = this.formatted;
                        $wire.set('target_amount', this.raw, false);
                    }
                }">
                <label for="target_amount" class="block text-xs font-medium text-ink-muted">Target nominal (Rp)</label>
                <div class="mt-1 flex items-center border-b border-hairline focus-within:border-primary">
                    <span class="pe-2 text-ink-muted">Rp</span>
                    <input id="target_amount" type="text" inputmode="numeric" autocomplete="off" required
                           placeholder="1.000.000"
                           :value="formatted"
                           x-on:input="onInput($event)"
                           class="w-full border-0 bg-transparent px-0 py-2 font-semibold tabular-nums text-ink placeholder:text-ink-disabled focus:ring-0">
                </div>
                @error('target_amount') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
            </div>

            <div>
                <label for="target_date" class="block text-xs font-medium text-ink-muted">Target tanggal <span class="text-ink-disabled">(opsional)</span></label>
                <input wire:model="target_date" id="target_date" type="date"
                       class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink focus:border-primary focus:ring-0">
                @error('target_date') <p class="mt-2 text-sm text-accent-red">{{ $message }}</p> @enderror
            </div>

            <div class="flex items-center gap-3 pt-2">
                <button type="submit"
                        class="inline-flex h-[52px] items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark">
                    Kirim Usulan
                </button>
                <a href="{{ route('goals.index') }}" wire:navigate
                   class="text-sm font-medium text-ink-muted transition hover:text-primary">
                    Batal
                </a>
            </div>
        </form>
    </div>
</div>
