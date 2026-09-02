<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public string $name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    /**
     * Handle an incoming registration request.
     */
    public function register(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'string', 'confirmed', Rules\Password::defaults()],
        ]);

        $validated['password'] = Hash::make($validated['password']);

        event(new Registered($user = User::create($validated)));

        // Start every account in solo mode so they can save right away.
        $user->createSoloPair();

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<div>
    <div class="mb-6">
        <h1 class="text-xl font-bold text-ink">Buat akun</h1>
        <p class="mt-1 text-sm text-ink-muted">Mulai catat tabunganmu &mdash; sendiri atau bareng pasangan.</p>
    </div>

    <form wire:submit="register" class="space-y-5">
        <!-- Name -->
        <div>
            <label for="name" class="block text-xs font-medium text-ink-muted">{{ __('Nama') }}</label>
            <input wire:model="name" id="name" name="name" type="text" required autofocus autocomplete="name"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('name')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <!-- Email Address -->
        <div>
            <label for="email" class="block text-xs font-medium text-ink-muted">{{ __('Email') }}</label>
            <input wire:model="email" id="email" name="email" type="email" required autocomplete="username"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('email')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <!-- Password -->
        <div>
            <label for="password" class="block text-xs font-medium text-ink-muted">{{ __('Password') }}</label>
            <input wire:model="password" id="password" name="password" type="password" required autocomplete="new-password"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('password')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <!-- Confirm Password -->
        <div>
            <label for="password_confirmation" class="block text-xs font-medium text-ink-muted">{{ __('Konfirmasi Password') }}</label>
            <input wire:model="password_confirmation" id="password_confirmation" name="password_confirmation" type="password" required autocomplete="new-password"
                class="mt-1 w-full border-0 border-b border-hairline bg-transparent px-0 py-2 text-ink placeholder:text-ink-disabled focus:border-primary focus:ring-0" />
            @error('password_confirmation')
                <p class="mt-2 text-sm text-accent-red">{{ $message }}</p>
            @enderror
        </div>

        <button type="submit"
            class="flex h-[52px] w-full items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark">
            {{ __('Buat Akun') }}
        </button>

        <p class="text-center text-sm text-ink-muted">
            {{ __('Sudah punya akun?') }}
            <a class="font-medium text-primary transition hover:text-primary-dark" href="{{ route('login') }}" wire:navigate>
                {{ __('Masuk') }}
            </a>
        </p>
    </form>
</div>
