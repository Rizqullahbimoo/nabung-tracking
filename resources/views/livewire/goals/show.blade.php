<?php

use App\Models\Contribution;
use App\Models\Goal;
use App\Support\Notifier;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.app')] class extends Component
{
    public Goal $goal;

    // Inline edit state for one of the current user's own deposit rows.
    public ?int $editingId = null;
    public string $editAmount = '';
    public string $editNote = '';
    public string $editDate = '';

    /**
     * Load the goal and make sure it belongs to the current user's pair.
     */
    public function mount(Goal $goal): void
    {
        $pair = Auth::user()->activePair();

        abort_unless($pair && $goal->pair_id === $pair->id, 403);

        $this->goal = $goal;
    }

    #[Computed]
    public function isProposer(): bool
    {
        return $this->goal->proposed_by === Auth::id();
    }

    /**
     * Whether the goal's pair is still solo (approval flow doesn't apply).
     */
    #[Computed]
    public function isSolo(): bool
    {
        return Auth::user()->activePair()->isSolo();
    }

    /**
     * Pending goal + a couple + current user is the partner (not the proposer).
     */
    #[Computed]
    public function canDecide(): bool
    {
        return $this->goal->status === 'pending' && ! $this->isSolo && ! $this->isProposer;
    }

    #[Computed]
    public function collected(): float
    {
        return $this->goal->collectedAmount();
    }

    #[Computed]
    public function progress(): int
    {
        return $this->goal->progressPercent();
    }

    /**
     * Per-user totals, shaped like api-spec "contribution_breakdown".
     */
    #[Computed]
    public function breakdown()
    {
        return $this->goal->contributionBreakdown();
    }

    /**
     * Contribution history, newest first.
     */
    #[Computed]
    public function history()
    {
        return $this->goal->contributions()
            ->with('user:id,name')
            ->latest('contributed_at')
            ->latest('id')
            ->get();
    }

    /**
     * Ids of deposits that have been "deleted" (a correction row points at them).
     */
    #[Computed]
    public function voidedIds(): array
    {
        return $this->goal->contributions()
            ->whereNotNull('corrects_contribution_id')
            ->pluck('corrects_contribution_id')
            ->all();
    }

    /**
     * Guard: only the creator may edit/delete their own, non-voided deposit
     * (F-10). Withdrawals and corrections are never editable here.
     */
    private function ownDeposit(int $contributionId): Contribution
    {
        $contribution = $this->goal->contributions()->find($contributionId);

        abort_unless(
            $contribution
                && $contribution->type === 'deposit'
                && $contribution->user_id === Auth::id()
                && ! $contribution->correction()->exists(),
            403,
        );

        return $contribution;
    }

    public function startEdit(int $contributionId): void
    {
        $contribution = $this->ownDeposit($contributionId);

        $this->editingId = $contribution->id;
        $this->editAmount = (string) (int) $contribution->amount;
        $this->editNote = (string) $contribution->note;
        $this->editDate = $contribution->contributed_at->toDateString();
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->reset('editingId', 'editAmount', 'editNote', 'editDate');
        $this->resetErrorBag();
    }

    public function saveEdit(): void
    {
        $contribution = $this->ownDeposit((int) $this->editingId);

        $this->editAmount = preg_replace('/\D/', '', (string) $this->editAmount);

        $validated = $this->validate([
            'editAmount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'editNote' => ['nullable', 'string', 'max:255'],
            'editDate' => ['required', 'date', 'before_or_equal:today'],
        ], attributes: [
            'editAmount' => 'nominal',
            'editNote' => 'catatan',
            'editDate' => 'tanggal',
        ]);

        $contribution->update([
            'amount' => $validated['editAmount'],
            'note' => $validated['editNote'] ?: null,
            'contributed_at' => $validated['editDate'],
        ]);

        $this->goal->syncAchievedStatus();

        session()->flash('status', 'Kontribusi diperbarui.');
        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }

    /**
     * "Delete" a deposit: keep the row, add a negative correction entry that
     * reverses it, then re-sync the goal status.
     */
    public function deleteContribution(int $contributionId): void
    {
        $contribution = $this->ownDeposit($contributionId);

        $this->goal->contributions()->create([
            'user_id' => $contribution->user_id,
            'amount' => -1 * (float) $contribution->amount,
            'type' => 'correction',
            'note' => 'Koreksi: menghapus kontribusi Rp '
                .number_format((float) $contribution->amount, 0, ',', '.')
                .' ('.$contribution->contributed_at->translatedFormat('d M Y').')',
            'contributed_at' => now()->toDateString(),
            'corrects_contribution_id' => $contribution->id,
        ]);

        $this->goal->syncAchievedStatus();

        session()->flash('status', 'Kontribusi dihapus dan dicatat sebagai koreksi.');
        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }

    public function approve(): void
    {
        abort_unless($this->canDecide, 403);

        $this->goal->update([
            'status' => 'active',
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);

        Notifier::goalDecided($this->goal, Auth::user(), approved: true);

        session()->flash('status', 'Goal disetujui dan sekarang aktif.');
        $this->redirect(route('goals.show', $this->goal), navigate: true);
    }

    public function reject(): void
    {
        abort_unless($this->canDecide, 403);

        $this->goal->update(['status' => 'rejected']);

        Notifier::goalDecided($this->goal, Auth::user(), approved: false);

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

            @if ($goal->status === 'pending' && ! $this->isSolo)
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

        {{-- Contributions & withdrawals --}}
        @if (in_array($goal->status, ['active', 'achieved'], true))
            <div class="mt-6 rounded-card border border-hairline bg-surface p-6 shadow-card sm:p-7"
                 x-data="{ panel: null }"
                 x-on:close-contribution-form.window="panel = null">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-ink">Kontribusi &amp; Penarikan</h2>
                    <div class="flex items-center gap-2">
                        @if ($goal->status === 'active')
                            <button type="button" x-on:click="panel = (panel === 'deposit' ? null : 'deposit')"
                                    class="inline-flex h-10 items-center justify-center rounded-btn bg-primary px-4 text-sm font-semibold text-white transition hover:bg-primary-dark">
                                + Tambah Kontribusi
                            </button>
                        @endif
                        <button type="button" x-on:click="panel = (panel === 'withdraw' ? null : 'withdraw')"
                                class="inline-flex h-10 items-center justify-center rounded-btn border-[1.5px] border-accent-red px-4 text-sm font-semibold text-accent-red transition hover:bg-accent-red/10">
                            Tarik Dana
                        </button>
                    </div>
                </div>

                @if ($goal->status === 'active')
                    <div x-show="panel === 'deposit'" x-cloak x-collapse>
                        <livewire:contributions.create :goal="$goal" :key="'contrib-form-'.$goal->id" />
                    </div>
                @endif
                <div x-show="panel === 'withdraw'" x-cloak x-collapse>
                    <livewire:contributions.withdraw :goal="$goal" :key="'withdraw-form-'.$goal->id" />
                </div>

                {{-- Net contribution per user (deposits minus that person's withdrawals) --}}
                @if ($this->breakdown->isNotEmpty())
                    <div class="mt-5 space-y-2 border-t border-hairline pt-5">
                        <p class="text-xs font-medium text-ink-muted">Kontribusi bersih per orang</p>
                        @foreach ($this->breakdown as $row)
                            <div class="flex items-center justify-between text-sm">
                                <span class="text-ink">{{ $row['name'] ?? 'Pengguna' }}</span>
                                <span class="font-semibold tabular-nums {{ $row['total'] < 0 ? 'text-accent-red' : 'text-ink' }}">
                                    Rp {{ number_format($row['total'], 0, ',', '.') }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif

                {{-- History (design.md §6.3) --}}
                <div class="mt-5 border-t border-hairline pt-5">
                    <p class="text-xs font-medium text-ink-muted">Riwayat</p>
                    @if ($this->history->isEmpty())
                        <p class="mt-3 text-sm text-ink-muted">Belum ada kontribusi. Tambahkan yang pertama.</p>
                    @else
                        <ul class="mt-3 space-y-3">
                            @foreach ($this->history as $item)
                                @php
                                    $voided = in_array($item->id, $this->voidedIds, true);
                                    $canModify = $item->isDeposit() && $item->user_id === auth()->id() && ! $voided;
                                @endphp

                                <li>
                                    @if ($this->editingId === $item->id)
                                        {{-- Inline edit form --}}
                                        <form wire:submit="saveEdit" class="space-y-3 rounded-card-sm border border-hairline bg-canvas p-4">
                                            <div x-data="{
                                                    raw: @js((string) $editAmount),
                                                    get formatted() { return this.raw !== '' ? new Intl.NumberFormat('id-ID').format(Number(this.raw)) : ''; },
                                                    onInput(e) { this.raw = e.target.value.replace(/\D/g, ''); e.target.value = this.formatted; $wire.set('editAmount', this.raw, false); }
                                                }">
                                                <label class="block text-xs font-medium text-ink-muted">Nominal (Rp)</label>
                                                <div class="mt-1 flex items-center border-b border-hairline focus-within:border-primary">
                                                    <span class="pe-2 text-ink-muted">Rp</span>
                                                    <input type="text" inputmode="numeric" autocomplete="off" required
                                                           :value="formatted" x-on:input="onInput($event)"
                                                           class="w-full border-0 bg-transparent px-0 py-2 font-semibold tabular-nums text-ink focus:ring-0">
                                                </div>
                                                @error('editAmount') <p class="mt-1 text-xs text-accent-red">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-ink-muted">Catatan <span class="text-ink-disabled">(opsional)</span></label>
                                                <input type="text" wire:model="editNote" maxlength="255"
                                                       class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-sm text-ink focus:border-primary focus:ring-0">
                                                @error('editNote') <p class="mt-1 text-xs text-accent-red">{{ $message }}</p> @enderror
                                            </div>
                                            <div>
                                                <label class="block text-xs font-medium text-ink-muted">Tanggal</label>
                                                <input type="date" wire:model="editDate" max="{{ now()->toDateString() }}"
                                                       class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-sm text-ink focus:border-primary focus:ring-0">
                                                @error('editDate') <p class="mt-1 text-xs text-accent-red">{{ $message }}</p> @enderror
                                            </div>
                                            <div class="flex items-center gap-3 pt-1">
                                                <button type="submit"
                                                        class="inline-flex h-9 items-center justify-center rounded-btn bg-primary px-4 text-xs font-semibold text-white transition hover:bg-primary-dark">
                                                    Simpan
                                                </button>
                                                <button type="button" wire:click="cancelEdit"
                                                        class="text-xs font-medium text-ink-muted transition hover:text-primary">
                                                    Batal
                                                </button>
                                            </div>
                                        </form>
                                    @else
                                        <div class="flex items-center gap-3 {{ $voided ? 'opacity-60' : '' }}" x-data="{ confirming: false }">
                                            <x-user-avatar :name="optional($item->user)->name" :id="$item->user_id" size="md" />
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-sm font-medium text-ink">
                                                    {{ optional($item->user)->name ?? 'Pengguna' }}
                                                    @if ($item->isWithdrawal())
                                                        <span class="ms-1 rounded-full bg-accent-red/10 px-1.5 py-0.5 text-[10px] font-medium text-accent-red">Penarikan</span>
                                                    @elseif ($item->isCorrection())
                                                        <span class="ms-1 rounded-full bg-ink-disabled/15 px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">Koreksi</span>
                                                    @elseif ($voided)
                                                        <span class="ms-1 rounded-full bg-ink-disabled/15 px-1.5 py-0.5 text-[10px] font-medium text-ink-muted">Dihapus</span>
                                                    @endif
                                                </p>
                                                <p class="truncate text-xs text-ink-muted">
                                                    {{ $item->contributed_at->translatedFormat('d M Y') }}
                                                    @if ($item->note) &middot; {{ $item->note }} @endif
                                                </p>
                                            </div>

                                            @if ($item->isWithdrawal())
                                                <span class="shrink-0 text-sm font-semibold tabular-nums text-accent-red">
                                                    - Rp {{ number_format((float) $item->amount, 0, ',', '.') }}
                                                </span>
                                            @elseif ($item->isCorrection())
                                                <span class="shrink-0 text-sm font-semibold tabular-nums text-ink-muted">
                                                    - Rp {{ number_format(abs((float) $item->amount), 0, ',', '.') }}
                                                </span>
                                            @else
                                                <span class="shrink-0 text-sm font-semibold tabular-nums text-accent-green {{ $voided ? 'line-through' : '' }}">
                                                    + Rp {{ number_format((float) $item->amount, 0, ',', '.') }}
                                                </span>
                                            @endif

                                            @if ($canModify)
                                                <div class="flex shrink-0 items-center">
                                                    <template x-if="!confirming">
                                                        <div class="flex items-center gap-1">
                                                            <button type="button" wire:click="startEdit({{ $item->id }})"
                                                                    class="p-1 text-ink-muted transition hover:text-primary" title="Edit kontribusi">
                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M12 20h9" />
                                                                    <path d="M16.5 3.5a2.12 2.12 0 0 1 3 3L7 19l-4 1 1-4Z" />
                                                                </svg>
                                                            </button>
                                                            <button type="button" x-on:click="confirming = true"
                                                                    class="p-1 text-ink-muted transition hover:text-accent-red" title="Hapus kontribusi">
                                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                                                    <path d="M3 6h18" />
                                                                    <path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2m3 0v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6" />
                                                                    <path d="M10 11v6M14 11v6" />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    </template>
                                                    <template x-if="confirming">
                                                        <div class="flex items-center gap-2 text-xs">
                                                            <span class="text-ink-muted">Yakin hapus?</span>
                                                            <button type="button" wire:click="deleteContribution({{ $item->id }})"
                                                                    class="font-semibold text-accent-red">Ya</button>
                                                            <button type="button" x-on:click="confirming = false"
                                                                    class="text-ink-muted">Batal</button>
                                                        </div>
                                                    </template>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>
</div>
