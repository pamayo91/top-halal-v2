<x-filament-panels::page>
    @php($tree = $this->tree())
    <div class="menu-editor" x-data="{ dragging: null, submitMove(url, data = {}) { const form = document.createElement('form'); form.method = 'post'; form.action = url; const values = { _token: '{{ csrf_token() }}', ...data }; Object.entries(values).forEach(([name, value]) => { const input = document.createElement('input'); input.type = 'hidden'; input.name = name; input.value = value ?? ''; form.appendChild(input); }); document.body.appendChild(form); form.submit(); } }">
        <div class="menu-editor__intro"><div><h2>{{ $this->getRecord()->name }}</h2><p>Vue synthétique : glissez un élément sur un parent pour créer un sous-menu, ou dans la zone principale pour le remettre à la racine. Deux niveaux maximum.</p></div><x-filament::button wire:click="openCreate">+ Ajouter un élément</x-filament::button></div>
        <div class="menu-editor__tree" x-on:dragover.prevent x-on:drop.prevent="if (dragging) submitMove('{{ route('admin.menu-items.move', ['menu' => $this->getRecord(), 'item' => '__ITEM__', 'direction' => 1]) }}'.replace('__ITEM__', dragging), { parent_id: null, position: {{ count($tree) }} })">
            @forelse($tree as $position => $item)
                @include('filament.resources.menu-resource.partials.item-row', ['item' => $item, 'position' => $position, 'parentId' => null, 'depth' => 0])
            @empty <p class="menu-editor__empty">Ce menu est vide. Ajoutez son premier élément.</p> @endforelse
        </div>
    </div>
    @if($itemData !== null)
        @php($options = $this->destinationOptions())
        <div class="menu-editor__backdrop" role="presentation"></div>
        <section class="menu-editor__modal" role="dialog" aria-modal="true" aria-labelledby="menu-item-title" x-data x-init="$nextTick(() => $refs.label.focus())" x-on:keydown.escape.window="$wire.closeItemEditor()">
            <form wire:submit="saveItem">
                <header><div><h2 id="menu-item-title">{{ $editingItemId ? 'Modifier l’élément' : 'Ajouter un élément' }}</h2><p>{{ $itemData['parent_id'] ? 'Sous-menu' : 'Élément principal' }}</p></div><button type="button" class="menu-editor__close" wire:click="closeItemEditor" aria-label="Fermer">×</button></header>
                <label>Libellé <input x-ref="label" type="text" wire:model="itemData.label" maxlength="160" required></label>@error('label')<p class="menu-editor__error">{{ $message }}</p>@enderror
                <label>Type de destination <select wire:model.live="itemData.link_type">@foreach(\App\Models\MenuItem::LINK_TYPES as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></label>
                @if(in_array($itemData['link_type'], ['page', 'article', 'category', 'feature'], true))<label>Destination <select wire:model="itemData.linkable_id"><option value="">Choisir…</option>@foreach($options[$itemData['link_type']] as $id => $label)<option value="{{ $id }}">{{ $label }}</option>@endforeach</select></label>@endif
                @if($itemData['link_type'] === 'city')<label>Ville <select wire:model="itemData.destination_key"><option value="">Choisir…</option>@foreach($options['city'] as $slug => $label)<option value="{{ $slug }}">{{ $label }}</option>@endforeach</select></label>@endif
                @if(in_array($itemData['link_type'], ['internal_url', 'external_url'], true))<label>URL <input type="{{ $itemData['link_type'] === 'internal_url' ? 'text' : 'url' }}" inputmode="url" wire:model="itemData.url" placeholder="{{ $itemData['link_type'] === 'internal_url' ? '/restaurants ou https://dev.top-halal.fr/restaurants' : 'https://…' }}"></label>@endif
                @error('linkable_id')<p class="menu-editor__error">{{ $message }}</p>@enderror @error('destination_key')<p class="menu-editor__error">{{ $message }}</p>@enderror @error('url')<p class="menu-editor__error">{{ $message }}</p>@enderror
                <div class="menu-editor__toggles"><label><input type="checkbox" wire:model="itemData.is_active"> Actif</label><label><input type="checkbox" wire:model="itemData.visible_desktop"> Desktop</label><label><input type="checkbox" wire:model="itemData.visible_mobile"> Mobile</label></div>
                @if($itemData['link_type'] !== 'none')<details><summary>Options avancées</summary><div class="menu-editor__toggles">@if($itemData['link_type'] === 'external_url')<label><input type="checkbox" wire:model="itemData.target_blank"> Ouvrir dans un nouvel onglet</label>@endif<label><input type="checkbox" wire:model="itemData.nofollow"> nofollow</label></div></details>@endif
                <footer><x-filament::button color="gray" type="button" wire:click="closeItemEditor">Annuler</x-filament::button><x-filament::button type="submit">Enregistrer</x-filament::button></footer>
            </form>
        </section>
    @endif
</x-filament-panels::page>
