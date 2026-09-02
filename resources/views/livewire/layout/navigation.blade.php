<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }

    /**
     * Number of unread in-app notifications for the current user.
     */
    #[Computed]
    public function unreadCount(): int
    {
        return Auth::user()->notifications()->whereNull('read_at')->count();
    }

    /**
     * The 10 most recent notifications for the dropdown.
     */
    #[Computed]
    public function recentNotifications()
    {
        return Auth::user()->notifications()->latest()->limit(10)->get();
    }

    /**
     * Mark one notification read and open the goal it points to.
     */
    public function openNotification(int $id): void
    {
        $notification = Auth::user()->notifications()->find($id);

        if (! $notification) {
            return;
        }

        $notification->markAsRead();

        if ($url = $notification->url()) {
            $this->redirect($url, navigate: true);
        }
    }

    public function markAllRead(): void
    {
        Auth::user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);
    }
}; ?>

<nav x-data="{ open: false }" class="bg-surface border-b border-hairline">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" wire:navigate class="flex items-center gap-2.5">
                        <span class="inline-flex h-9 w-9 items-center justify-center rounded-card-sm bg-primary text-sm font-bold text-white">
                            NT
                        </span>
                        <span class="text-base font-bold text-ink">Nabung Tracking</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                        {{ __('Dashboard') }}
                    </x-nav-link>
                    <x-nav-link :href="route('goals.index')" :active="request()->routeIs('goals.*')" wire:navigate>
                        {{ __('Goals') }}
                    </x-nav-link>
                    <x-nav-link :href="route('reports.monthly')" :active="request()->routeIs('reports.*')" wire:navigate>
                        {{ __('Laporan') }}
                    </x-nav-link>
                </div>
            </div>

            <div class="flex items-center gap-1 sm:gap-3">
                <!-- Notification bell (all breakpoints) -->
                <x-dropdown align="right" width="w-80" contentClasses="bg-surface">
                    <x-slot name="trigger">
                        <button type="button"
                                class="relative inline-flex h-10 w-10 items-center justify-center rounded-btn text-ink-muted transition hover:bg-canvas hover:text-ink focus:outline-none">
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9" />
                                <path d="M10.3 21a1.94 1.94 0 0 0 3.4 0" />
                            </svg>
                            @if ($this->unreadCount > 0)
                                <span class="absolute -top-0.5 -end-0.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-accent-red px-1 text-[10px] font-bold leading-4 text-white">
                                    {{ $this->unreadCount > 9 ? '9+' : $this->unreadCount }}
                                </span>
                            @endif
                            <span class="sr-only">Notifikasi</span>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <div class="flex items-center justify-between border-b border-hairline px-4 py-2.5">
                            <span class="text-sm font-semibold text-ink">Notifikasi</span>
                            @if ($this->unreadCount > 0)
                                <button type="button" wire:click="markAllRead"
                                        class="text-xs font-medium text-primary transition hover:text-primary-dark">
                                    Tandai semua sudah dibaca
                                </button>
                            @endif
                        </div>

                        <div class="max-h-96 overflow-y-auto">
                            @forelse ($this->recentNotifications as $notification)
                                <button type="button" wire:click="openNotification({{ $notification->id }})"
                                        class="flex w-full items-start gap-3 px-4 py-3 text-start transition hover:bg-canvas {{ $notification->read_at ? '' : 'bg-primary-light/40' }}">
                                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $notification->read_at ? 'bg-transparent' : 'bg-primary' }}"></span>
                                    <span class="min-w-0 flex-1">
                                        <span class="block text-sm font-medium text-ink">{{ $notification->title }}</span>
                                        <span class="block text-xs text-ink-muted">{{ $notification->message }}</span>
                                        <span class="mt-0.5 block text-[11px] text-ink-disabled">{{ $notification->created_at?->diffForHumans() }}</span>
                                    </span>
                                </button>
                            @empty
                                <p class="px-4 py-6 text-center text-sm text-ink-muted">Belum ada notifikasi.</p>
                            @endforelse
                        </div>
                    </x-slot>
                </x-dropdown>

                <!-- Settings Dropdown (desktop) -->
                <div class="hidden sm:flex sm:items-center">
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-ink-muted bg-surface hover:text-ink focus:outline-none transition ease-in-out duration-150">
                                <div x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile')" wire:navigate>
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <button wire:click="logout" class="w-full text-start">
                                <x-dropdown-link>
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </button>
                        </x-slot>
                    </x-dropdown>
                </div>

                <!-- Hamburger (mobile) -->
                <div class="flex items-center sm:hidden">
                    <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-ink-disabled hover:text-ink-muted hover:bg-canvas focus:outline-none focus:bg-canvas focus:text-ink-muted transition duration-150 ease-in-out">
                        <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                            <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                            <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')" wire:navigate>
                {{ __('Dashboard') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('goals.index')" :active="request()->routeIs('goals.*')" wire:navigate>
                {{ __('Goals') }}
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('reports.monthly')" :active="request()->routeIs('reports.*')" wire:navigate>
                {{ __('Laporan') }}
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-hairline">
            <div class="px-4">
                <div class="font-medium text-base text-ink" x-data="{{ json_encode(['name' => auth()->user()->name]) }}" x-text="name" x-on:profile-updated.window="name = $event.detail.name"></div>
                <div class="font-medium text-sm text-ink-muted">{{ auth()->user()->email }}</div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile')" wire:navigate>
                    {{ __('Profile') }}
                </x-responsive-nav-link>

                <!-- Authentication -->
                <button wire:click="logout" class="w-full text-start">
                    <x-responsive-nav-link>
                        {{ __('Log Out') }}
                    </x-responsive-nav-link>
                </button>
            </div>
        </div>
    </div>
</nav>
