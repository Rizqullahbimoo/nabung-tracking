@props(['name' => '', 'id' => 0, 'size' => 'md'])

@php
    $initials = collect(explode(' ', trim((string) $name)))
        ->filter()
        ->take(2)
        ->map(fn ($word) => mb_strtoupper(mb_substr($word, 0, 1)))
        ->implode('');

    // Accent colour rotated per user id (design.md §8).
    $palette = ['bg-accent-green', 'bg-accent-orange', 'bg-accent-purple', 'bg-primary'];
    $bg = $palette[((int) $id) % count($palette)];

    $sizes = [
        'sm' => 'h-9 w-9 text-xs',
        'md' => 'h-10 w-10 text-sm',
        'lg' => 'h-14 w-14 text-lg',
    ];
    $sizeClass = $sizes[$size] ?? $sizes['md'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex shrink-0 items-center justify-center rounded-full font-semibold text-white {$bg} {$sizeClass}"]) }}>
    {{ $initials !== '' ? $initials : '?' }}
</span>
