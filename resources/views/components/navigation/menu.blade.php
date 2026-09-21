@props(['menu', 'mobile' => false, 'label' => 'Navigation'])
@if($menu['items'] !== [])
<ul {{ $attributes->merge(['class' => $mobile ? 'mobile-menu-list' : 'nav-menu-list']) }} aria-label="{{ $label }}">
@foreach($menu['items'] as $item)
    @php($hasChildren = $item['children'] !== [])
    @php($panelId = ($mobile ? 'mobile' : 'desktop').'-submenu-'.$item['id'])
    <li class="{{ $hasChildren ? 'has-submenu' : '' }}">
        @if($item['url'])
            <a @class(['nav-item', 'is-active' => $item['is_active']]) href="{{ $item['url'] }}" @if($item['target_blank']) target="_blank" rel="noopener noreferrer{{ $item['nofollow'] ? ' nofollow' : '' }}" @elseif($item['nofollow']) rel="nofollow" @endif>{{ $item['label'] }}</a>
        @elseif($hasChildren)
            <button @class(['nav-item', 'nav-parent', 'is-active' => $item['is_active']]) type="button" data-submenu-toggle aria-expanded="false" aria-controls="{{ $panelId }}"><span>{{ $item['label'] }}</span><svg class="nav-chevron" aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="m3.5 6 4.5 4.5L12.5 6"/></svg></button>
        @else
            <span @class(['nav-item', 'nav-item-static', 'is-active' => $item['is_active']])>{{ $item['label'] }}</span>
        @endif
        @if($hasChildren)
            @if($item['url'])<button class="submenu-toggle" type="button" data-submenu-toggle aria-label="Ouvrir le sous-menu {{ $item['label'] }}" aria-expanded="false" aria-controls="{{ $panelId }}"><svg class="nav-chevron" aria-hidden="true" viewBox="0 0 16 16" focusable="false"><path d="m3.5 6 4.5 4.5L12.5 6"/></svg></button>@endif
            <ul id="{{ $panelId }}" class="submenu" hidden>
                @foreach($item['children'] as $child)
                    <li>@if($child['url'])<a @class(['is-active' => $child['is_active']]) href="{{ $child['url'] }}" @if($child['target_blank']) target="_blank" rel="noopener noreferrer{{ $child['nofollow'] ? ' nofollow' : '' }}" @elseif($child['nofollow']) rel="nofollow" @endif>{{ $child['label'] }}</a>@else<span @class(['is-active' => $child['is_active'])>{{ $child['label'] }}</span>@endif</li>
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
</ul>
@endif
