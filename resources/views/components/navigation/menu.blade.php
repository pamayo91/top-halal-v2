@props(['menu', 'mobile' => false, 'label' => 'Navigation'])
@if($menu['items'] !== [])
<ul {{ $attributes->merge(['class' => $mobile ? 'mobile-menu-list' : 'nav-menu-list']) }} aria-label="{{ $label }}">
@foreach($menu['items'] as $item)
    @php($hasChildren = $item['children'] !== [])
    @php($panelId = ($mobile ? 'mobile' : 'desktop').'-submenu-'.$item['id'])
    <li class="{{ $hasChildren ? 'has-submenu' : '' }}">
        @if($item['url'])
            <a href="{{ $item['url'] }}" @if($item['target_blank']) target="_blank" rel="noopener noreferrer{{ $item['nofollow'] ? ' nofollow' : '' }}" @elseif($item['nofollow']) rel="nofollow" @endif>{{ $item['label'] }}</a>
        @elseif($hasChildren)
            <button class="nav-parent" type="button" data-submenu-toggle aria-expanded="false" aria-controls="{{ $panelId }}">{{ $item['label'] }}</button>
        @else
            <span>{{ $item['label'] }}</span>
        @endif
        @if($hasChildren)
            @if($item['url'])<button class="submenu-toggle" type="button" data-submenu-toggle aria-label="Ouvrir le sous-menu {{ $item['label'] }}" aria-expanded="false" aria-controls="{{ $panelId }}">⌄</button>@endif
            <ul id="{{ $panelId }}" class="submenu" hidden>
                @foreach($item['children'] as $child)
                    <li>@if($child['url'])<a href="{{ $child['url'] }}" @if($child['target_blank']) target="_blank" rel="noopener noreferrer{{ $child['nofollow'] ? ' nofollow' : '' }}" @elseif($child['nofollow']) rel="nofollow" @endif>{{ $child['label'] }}</a>@else<span>{{ $child['label'] }}</span>@endif</li>
                @endforeach
            </ul>
        @endif
    </li>
@endforeach
</ul>
@endif
