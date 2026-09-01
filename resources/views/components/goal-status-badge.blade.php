@props(['status'])

@php
    // Status badge styles per design.md §6.6.
    $map = [
        'pending' => ['label' => 'Pending Approval', 'class' => 'bg-accent-orange/10 text-accent-orange'],
        'active' => ['label' => 'Aktif', 'class' => 'bg-accent-green/10 text-accent-green'],
        'achieved' => ['label' => 'Tercapai', 'class' => 'bg-primary/10 text-primary'],
        'rejected' => ['label' => 'Ditolak', 'class' => 'bg-accent-red/10 text-accent-red'],
        'archived' => ['label' => 'Diarsipkan', 'class' => 'bg-ink-disabled/15 text-ink-muted'],
    ];
    $style = $map[$status] ?? $map['pending'];
@endphp

<span {{ $attributes->merge(['class' => "inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {$style['class']}"]) }}>
    {{ $style['label'] }}
</span>
