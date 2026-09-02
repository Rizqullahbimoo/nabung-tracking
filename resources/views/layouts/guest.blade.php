<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'Nabung Tracking') }}</title>

        @include('partials.pwa-head')

        <!-- Fonts -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-ink antialiased">
        <div class="min-h-screen bg-canvas flex flex-col justify-center items-center px-6 py-12">
            <a href="/" wire:navigate class="flex items-center gap-3">
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-card-sm bg-primary text-base font-bold text-white">
                    NT
                </span>
                <span class="text-lg font-bold text-ink">Nabung Tracking</span>
            </a>

            <div class="w-full sm:max-w-md mt-6 bg-surface rounded-card shadow-card border border-hairline p-6 sm:p-8">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
