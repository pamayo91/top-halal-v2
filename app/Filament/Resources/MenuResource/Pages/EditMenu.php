<?php

namespace App\Filament\Resources\MenuResource\Pages;

use App\Filament\Resources\MenuResource;
use App\Models\{Article, Category, Feature, MenuItem, Page};
use App\Services\{AdminAudit, CityPageResolver, PublicNavigation};
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EditMenu extends EditRecord
{
    protected static string $resource = MenuResource::class;
    protected string $view = 'filament.resources.menu-resource.pages.edit-menu';
    public ?array $itemData = null;
    public ?int $editingItemId = null;

    protected function getHeaderActions(): array { return []; }

    public function tree(): array
    {
        return $this->getRecord()->load(['rootItems.linkable', 'rootItems.children.linkable'])->rootItems
            ->map(fn (MenuItem $item): array => $this->treeItem($item))->all();
    }

    public function openCreate(?int $parentId = null): void
    {
        $parent = $parentId === null ? null : $this->item($parentId);
        if ($parent?->parent_id !== null) abort(422, 'Un sous-menu ne peut pas contenir de troisième niveau.');
        $this->editingItemId = null;
        $this->itemData = ['parent_id' => $parent?->id, 'label' => '', 'link_type' => 'none', 'linkable_id' => null, 'destination_key' => null, 'url' => null, 'is_active' => true, 'visible_desktop' => true, 'visible_mobile' => true, 'target_blank' => false, 'nofollow' => false];
        $this->resetValidation();
    }

    public function editItem(int $itemId): void
    {
        $item = $this->item($itemId);
        $this->editingItemId = $item->id;
        $this->itemData = $item->only(['parent_id', 'label', 'link_type', 'linkable_id', 'destination_key', 'url', 'is_active', 'visible_desktop', 'visible_mobile', 'target_blank', 'nofollow']);
        $this->resetValidation();
    }

    public function closeItemEditor(): void { $this->editingItemId = null; $this->itemData = null; $this->resetValidation(); }

    public function saveItem(): void
    {
        $data = validator($this->itemData ?? [], $this->itemRules())->validate();
        $data['parent_id'] = $data['parent_id'] ? (int) $data['parent_id'] : null;
        $data['linkable_id'] = in_array($data['link_type'], ['page', 'article', 'category', 'feature'], true) ? (int) $data['linkable_id'] : null;
        $data['destination_key'] = $data['link_type'] === 'city' ? $data['destination_key'] : null;
        $data['url'] = in_array($data['link_type'], ['internal_url', 'external_url'], true) ? $data['url'] : null;
        if ($data['link_type'] === 'internal_url') $data['url'] = $this->normalizeInternalUrl($data['url']);
        if ($data['link_type'] === 'none') { $data['target_blank'] = false; $data['nofollow'] = false; }
        if ($data['link_type'] === 'internal_url') $data['target_blank'] = false;
        $item = $this->editingItemId === null ? new MenuItem(['menu_id' => $this->getRecord()->id, 'sort_order' => $this->nextSortOrder($data['parent_id'])]) : $this->item($this->editingItemId);
        $item->fill($data)->save();
        app(AdminAudit::class)->record($this->editingItemId === null ? 'navigation.item.created' : 'navigation.item.updated', $item, ['label' => $item->label, 'link_type' => $item->link_type]);
        app(PublicNavigation::class)->forget(); $this->closeItemEditor(); Notification::make()->title('Élément enregistré')->success()->send();
    }

    public function deleteItem(int $itemId): void
    {
        $item = $this->item($itemId);
        DB::transaction(function () use ($item): void { $item->children()->update(['parent_id' => null]); $item->delete(); });
        app(AdminAudit::class)->record('navigation.item.deleted', $item, ['label' => $item->label]);
        app(PublicNavigation::class)->forget(); Notification::make()->title('Élément supprimé ; ses sous-menus sont conservés à la racine.')->success()->send();
    }

    public function toggleItem(int $itemId): void { $item = $this->item($itemId); $item->update(['is_active' => ! $item->is_active]); app(AdminAudit::class)->record('navigation.item.toggled', $item, ['is_active' => $item->is_active]); }

    public function moveItem(int $itemId, ?int $parentId, int $position): void
    {
        $item = $this->item($itemId); $parent = $parentId === null ? null : $this->item($parentId);
        if ($parent?->parent_id !== null || $parent?->id === $item->id) abort(422, 'Un sous-menu ne peut pas contenir de troisième niveau.');
        DB::transaction(function () use ($item, $parent, $position): void {
            $oldParentId = $item->parent_id; $item->update(['parent_id' => $parent?->id]); $this->renumber($oldParentId);
            $siblings = MenuItem::query()->where('menu_id', $this->getRecord()->id)->where('parent_id', $parent?->id)->whereKeyNot($item->id)->orderBy('sort_order')->get();
            $siblings->splice(max(0, min($position, $siblings->count())), 0, [$item]);
            foreach ($siblings as $index => $sibling) $sibling->updateQuietly(['sort_order' => $index + 1]);
        });
        app(PublicNavigation::class)->forget();
    }

    public function moveBy(int $itemId, int $direction): void
    {
        $item = $this->item($itemId); $siblings = MenuItem::query()->where('menu_id', $this->getRecord()->id)->where('parent_id', $item->parent_id)->orderBy('sort_order')->pluck('id')->all(); $index = array_search($item->id, $siblings, true);
        if ($index === false || ! isset($siblings[$index + $direction])) return;
        [$siblings[$index], $siblings[$index + $direction]] = [$siblings[$index + $direction], $siblings[$index]];
        foreach ($siblings as $order => $id) MenuItem::whereKey($id)->update(['sort_order' => $order + 1]); app(PublicNavigation::class)->forget();
    }

    public function destinationOptions(): array { return ['page' => Page::query()->orderBy('title')->pluck('title', 'id')->all(), 'article' => Article::query()->orderBy('title')->pluck('title', 'id')->all(), 'category' => Category::query()->orderBy('name')->pluck('name', 'id')->all(), 'feature' => Feature::query()->orderBy('name')->pluck('name', 'id')->all(), 'city' => app(CityPageResolver::class)->cities()->mapWithKeys(fn (object $city): array => [$city->slug => $city->city_name.($city->is_ambiguous ? ' — '.$city->department['name'] : '')])->all()]; }
    private function normalizeInternalUrl(?string $url): ?string
    {
        $url = trim((string) $url);
        if ($url === '' || str_starts_with($url, '/')) return $url;
        $parts = parse_url($url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $configuredHost = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $allowedHosts = array_filter([strtolower(request()->getHost()), $configuredHost]);
        if (! in_array($host, $allowedHosts, true) || ! in_array(strtolower((string) ($parts['scheme'] ?? '')), ['http', 'https'], true)) return $url;
        $path = '/'.ltrim((string) ($parts['path'] ?? ''), '/');
        if (isset($parts['query'])) $path .= '?'.$parts['query'];
        if (isset($parts['fragment'])) $path .= '#'.$parts['fragment'];
        return $path;
    }
    private function itemRules(): array { return ['parent_id' => ['nullable', 'integer'], 'label' => ['required', 'string', 'max:160'], 'link_type' => ['required', Rule::in(array_keys(MenuItem::LINK_TYPES))], 'linkable_id' => ['nullable', 'integer', Rule::requiredIf(fn () => in_array($this->itemData['link_type'] ?? null, ['page', 'article', 'category', 'feature'], true))], 'destination_key' => ['nullable', 'string', Rule::requiredIf(fn () => ($this->itemData['link_type'] ?? null) === 'city')], 'url' => ['nullable', 'string', 'max:2048', Rule::requiredIf(fn () => in_array($this->itemData['link_type'] ?? null, ['internal_url', 'external_url'], true))], 'is_active' => ['boolean'], 'visible_desktop' => ['boolean'], 'visible_mobile' => ['boolean'], 'target_blank' => ['boolean'], 'nofollow' => ['boolean']]; }
    private function treeItem(MenuItem $item): array { return ['id' => $item->id, 'label' => $item->label, 'summary' => $this->summary($item), 'is_active' => $item->is_active, 'desktop' => $item->visible_desktop, 'mobile' => $item->visible_mobile, 'children' => $item->children->map(fn (MenuItem $child): array => $this->treeItem($child))->all()]; }
    private function summary(MenuItem $item): string { if ($item->link_type === 'none') return 'Aucun lien'; if (in_array($item->link_type, ['internal_url', 'external_url'], true)) return (string) $item->url; if ($item->link_type === 'city') return 'Ville · '.($this->destinationOptions()['city'][$item->destination_key] ?? 'Cible indisponible'); $label = MenuItem::LINK_TYPES[$item->link_type] ?? $item->link_type; return $label.' · '.($item->linkable?->title ?? $item->linkable?->name ?? 'Cible indisponible'); }
    private function item(int $id): MenuItem { return MenuItem::query()->where('menu_id', $this->getRecord()->id)->findOrFail($id); }
    private function nextSortOrder(?int $parentId): int { return (int) MenuItem::query()->where('menu_id', $this->getRecord()->id)->where('parent_id', $parentId)->max('sort_order') + 1; }
    private function renumber(?int $parentId): void { MenuItem::query()->where('menu_id', $this->getRecord()->id)->where('parent_id', $parentId)->orderBy('sort_order')->get()->values()->each(fn (MenuItem $item, int $index) => $item->updateQuietly(['sort_order' => $index + 1])); }
}
