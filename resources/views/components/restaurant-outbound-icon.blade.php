@props(['label'])
<svg {{ $attributes->class('restaurant-outbound-icon') }} aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" focusable="false">
@switch($label)
    @case('Instagram')
        <rect x="3" y="3" width="18" height="18" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r=".75" fill="currentColor" stroke="none"/>
        @break
    @case('Facebook')
        <path d="M14 21v-8h3l.5-3H14V8.5c0-.9.3-1.5 1.6-1.5H18V4.3c-.4-.1-1.2-.3-2.3-.3-2.3 0-3.7 1.4-3.7 4V10H9v3h3v8"/>
        @break
    @case('TikTok')
        <path d="M14 4v10.2a3.3 3.3 0 1 1-2.7-3.2"/><path d="M14 4c.7 2.3 2.1 3.6 4.5 3.9"/>
        @break
    @default
        <circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3c2.2 2.4 3.3 5.4 3.3 9S14.2 18.6 12 21c-2.2-2.4-3.3-5.4-3.3-9S9.8 5.4 12 3"/>
@endswitch
</svg>
