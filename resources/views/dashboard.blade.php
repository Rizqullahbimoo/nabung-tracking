<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-ink leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            <section>
                <livewire:pairing.status />
            </section>

            <a href="{{ route('goals.index') }}" wire:navigate
               class="flex items-center justify-between rounded-card border border-hairline bg-surface p-5 shadow-card transition hover:border-primary/40">
                <div>
                    <p class="text-base font-semibold text-ink">Goal Tabungan</p>
                    <p class="mt-0.5 text-sm text-ink-muted">Lihat, usulkan, dan setujui target tabungan bersama.</p>
                </div>
                <span class="text-primary" aria-hidden="true">&rarr;</span>
            </a>
        </div>
    </div>
</x-app-layout>
