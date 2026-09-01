<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    /**
     * Handle an incoming authentication request.
     */
    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink">Masuk</h1>
        <p class="mt-1 text-sm text-ink-muted">Selamat datang kembali.</p>
    </div>

    <!-- Session Status -->
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form wire:submit="login" class="space-y-5">
        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-ink-muted">{{ __('Email') }}</label>
            <input wire:model="form.email" id="email" name="email" type="email" required autofocus autocomplete="username"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('form.email')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-xs font-medium text-ink-muted">{{ __('Password') }}</label>
            <input wire:model="form.password" id="password" name="password" type="password" required autocomplete="current-password"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('form.password')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <!-- Remember Me -->
        <label for="remember" class="inline-flex items-center">
            <input wire:model="form.remember" id="remember" type="checkbox" name="remember"
                class="rounded border-hairline text-primary shadow-sm focus:ring-primary">
            <span class="ms-2 text-sm text-ink-muted">{{ __('Remember me') }}</span>
        </label>

        <button type="submit"
            class="flex h-[52px] w-full items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark">
            {{ __('Masuk') }}
        </button>

        <div class="flex items-center justify-between text-sm">
            <a class="text-ink-muted transition hover:text-primary" href="{{ route('register') }}" wire:navigate>
                {{ __('Belum punya akun?') }}
            </a>
            @if (Route::has('password.request'))
                <a class="text-ink-muted transition hover:text-primary" href="{{ route('password.request') }}" wire:navigate>
                    {{ __('Lupa password?') }}
                </a>
            @endif
        </div>
    </form>
</div>
