<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title>{{ config('app.name', 'Nabung Tracking') }}</title>

        @include('partials.pwa-head')

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased">
        <div class="min-h-screen bg-canvas flex flex-col items-center justify-center px-6 py-12">
            <div class="w-full max-w-md">
                <div class="text-center">
                    <span class="inline-flex h-14 w-14 items-center justify-center rounded-card-sm bg-primary text-xl font-bold text-white">
                        NT
                    </span>
                    <h1 class="mt-5 text-2xl font-bold text-ink">Nabung Tracking</h1>
                    <p class="mt-2 text-sm text-ink-muted">
                        Catat dan pantau tabunganmu &mdash; sendiri atau bareng pasangan.
                    </p>
                </div>

                <div class="mt-8 bg-surface rounded-card shadow-card border border-hairline p-6 sm:p-7">
                    <a href="{{ route('register') }}" wire:navigate
                        class="flex h-[52px] w-full items-center justify-center rounded-btn bg-primary px-6 font-semibold text-white transition hover:bg-primary-dark">
                        Buat Akun
                    </a>

                    <a href="{{ route('login') }}" wire:navigate
                        class="mt-3 flex h-[52px] w-full items-center justify-center rounded-btn border-[1.5px] border-primary px-6 font-semibold text-primary transition hover:bg-primary-light">
                        Masuk
                    </a>
                </div>

                <p class="mt-6 text-center text-xs text-ink-disabled">
                    Aplikasi personal untuk mencatat tabungan &mdash; solo atau berdua.
                </p>
            </div>
        </div>
    </body>
</html>
