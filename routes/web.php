<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::view('profile', 'profile')
    ->middleware(['auth'])
    ->name('profile');

Route::middleware('auth')->group(function () {
    Volt::route('goals', 'goals.index')->name('goals.index');
    Volt::route('goals/create', 'goals.create')->name('goals.create');
    Volt::route('goals/{goal}', 'goals.show')->name('goals.show');
});

require __DIR__.'/auth.php';
