@props(['name'])
@php($icon = \Illuminate\Support\Str::slug($name))
@once
<style>.service-list,.service-icons{margin:.75rem 0 0;padding:0;list-style:none;display:flex;flex-wrap:wrap;gap:.5rem}.restaurant-information h3{font-size:1rem;margin:1.25rem 0 0}.service-list li{display:inline-flex;align-items:center;gap:.4rem;border:1px solid var(--line);border-radius:100px;padding:.35rem .65rem;font-size:.9rem}.service-icon{width:1.05rem;height:1.05rem;flex:none;color:var(--green)}.service-icons li{display:grid;place-items:center;width:2rem;height:2rem;border-radius:50%;background:#edf4ef}.service-icons .service-icon{width:1.15rem;height:1.15rem}</style>
@endonce
<svg {{ $attributes->class('service-icon') }} viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
    @switch($icon)
        @case('acces-handicape')
            <circle cx="10" cy="15" r="6"/><circle cx="13" cy="4" r="2"/><path d="M11 8v5h4l3 7M11 13H8M11 13l-2 5"/>
            @break
        @case('ambiance-musicale')
            <path d="M9 18V5l10-2v13"/><circle cx="6" cy="18" r="3"/><circle cx="16" cy="16" r="3"/>
            @break
        @case('beau-decor')
            <circle cx="12" cy="11" r="2"/><circle cx="12" cy="6" r="3"/><circle cx="8" cy="13" r="3"/><circle cx="16" cy="13" r="3"/><path d="M12 15v6M9 21h6"/>
            @break
        @case('branche')
            <path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9z"/>
            @break
        @case('certifie-halal')
            <path d="M12 3 14 5.2l3-.2.8 2.8 2.5 1.6-1.1 2.8 1.1 2.8-2.5 1.6-.8 2.8-3-.2L12 21l-2-2.2-3 .2-.8-2.8-2.5-1.6 1.1-2.8-1.1-2.8 2.5-1.6L7 5l3 .2z"/><path d="M14.8 8.2a4.1 4.1 0 1 0 0 7.6 3.2 3.2 0 1 1 0-7.6Z"/>
            @break
        @case('original')
            <path d="M9 18h6M10 22h4M8.5 14.5A6 6 0 1 1 15.5 14.5c-.9.8-1.5 1.7-1.5 3.5h-4c0-1.8-.6-2.7-1.5-3.5Z"/>
            @break
        @case('romantique')
            <path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.7l-1.1-1.1a5.5 5.5 0 0 0-7.8 7.8L12 21l8.8-8.6a5.5 5.5 0 0 0 0-7.8Z"/>
            @break
        @case('salle-de-priere')
            <path d="M5 21V9a7 7 0 0 1 14 0v12M3 21h18M9 21v-6a3 3 0 0 1 6 0v6M12 2v2"/>
            @break
        @case('sans-alcool')
            <path stroke-width="2.5" d="M8 3h8v8a4 4 0 0 1-4 4h0a4 4 0 0 1-4-4V3ZM12 15v6M8 21h8M3 3l18 18"/>
            @break
        @case('terrasse')
            <path d="M3 11h18M12 11v10M6 21h12M4 11a8 8 0 0 1 16 0"/>
            @break
        @case('traiteur')
            <path d="M3 17h18M5 17a7 7 0 0 1 14 0M12 7V5M10 5h4M3 20h18"/>
            @break
        @case('vente-a-emporter')
            <path d="M6 8h12l1 13H5L6 8ZM9 8a3 3 0 0 1 6 0"/>
            @break
        @case('wi-fi')
        @case('wifi')
            <path d="M2 8.8a16 16 0 0 1 20 0M5 12.5a11 11 0 0 1 14 0M8.5 16a6 6 0 0 1 7 0"/><path d="M12 20h.01"/>
            @break
        @default
            <circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/>
    @endswitch
</svg>
