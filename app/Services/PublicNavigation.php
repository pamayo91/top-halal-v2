<?php

namespace App\Services;

use App\Models\{Article, Category, Feature, Menu, MenuItem, Page, Setting};
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class PublicNavigation
{
    private const HEADER_KEY = 'public-navigation.header';
    private const FOOTER_KEY = 'public-navigation.footer';

    public function __construct(private readonly CityPageResolver $cities) {}

    public function header(): array
    {
        return Cache::rememberForever(self::HEADER_KEY, function (): array {
            $config = (array) (Setting::where('key', 'header_navigation')->value('value') ?? []);
            $menu = $this->menu((int) ($config['menu_id'] ?? 0), 'header_main');
            return [
                'menu' => $this->tree($menu, 'desktop'),
                'mobile_menu' => $this->tree($menu, 'mobile'),
                'account_visible' => $config['account_visible'] ?? true,
                'account_label' => $config['account_label'] ?? 'Mon compte',
                'submission_visible' => $config['submission_visible'] ?? true,
                'submission_label' => $config['submission_label'] ?? 'Ajouter un restaurant',
            ];
        });
    }

    public function footer(): array
    {
        return Cache::rememberForever(self::FOOTER_KEY, function (): array {
            $config = (array) (Setting::where('key', 'footer_navigation')->value('value') ?? []);
            $columns = collect((array) ($config['column_menu_ids'] ?? []))
                ->map(fn ($id) => $this->tree($this->menu((int) $id), 'desktop'))
                ->filter(fn (array $menu) => $menu['items'] !== [])->values()->all();
            $legal = $this->tree($this->menu((int) ($config['legal_menu_id'] ?? 0), 'footer_legal'), 'desktop');
            $socialLinks = collect((array) ($config['social_links'] ?? []))->filter(fn ($link) => ($link['is_active'] ?? false) && $this->validExternalUrl((string) ($link['url'] ?? '')))->sortBy('sort_order')->values()->all();
            return [
                'introduction' => $config['introduction'] ?? 'Le guide indépendant pour trouver un restaurant halal.',
                'show_logo' => $config['show_logo'] ?? true,
                'columns' => $columns,
                'legal' => $legal,
                'copyright' => $config['copyright'] ?? '© Top-Halal',
                'social_links' => $socialLinks,
            ];
        });
    }

    public function forget(): void { Cache::forget(self::HEADER_KEY); Cache::forget(self::FOOTER_KEY); }

    private function menu(int $id, ?string $fallbackLocation = null): ?Menu
    {
        return Menu::query()->where('is_active', true)->when($id > 0, fn ($query) => $query->whereKey($id), fn ($query) => $query->where('location', $fallbackLocation))->with(['rootItems.linkable', 'rootItems.children.linkable'])->first();
    }

    private function tree(?Menu $menu, string $surface): array
    {
        if ($menu === null) return ['name' => null, 'items' => []];
        $items = $menu->rootItems->map(fn (MenuItem $item) => $this->item($item, $surface))->filter()->values()->all();
        return ['name' => $menu->name, 'items' => $items];
    }

    private function item(MenuItem $item, string $surface): ?array
    {
        if (! $item->is_active || ! ($surface === 'desktop' ? $item->visible_desktop : $item->visible_mobile)) return null;
        $children = $item->children->map(fn (MenuItem $child) => $this->item($child, $surface))->filter()->values()->all();
        $url = $this->urlFor($item);
        if ($item->link_type !== 'none' && $url === null && $children === []) return null;
        return ['id' => $item->id, 'label' => $item->label, 'url' => $url, 'children' => $children, 'target_blank' => $item->target_blank, 'nofollow' => $item->nofollow];
    }

    private function urlFor(MenuItem $item): ?string
    {
        return match ($item->link_type) {
            'none' => null,
            'internal_url' => $this->validInternalUrl((string) $item->url) ? $item->url : null,
            'external_url' => $this->validExternalUrl((string) $item->url) ? $item->url : null,
            'page', 'article' => $this->publishedUrl($item),
            'category' => $this->modelUrl($item, Category::class, 'categories.show'),
            'feature' => $this->modelUrl($item, Feature::class, 'features.show'),
            'city' => $this->cityUrl($item->destination_key),
            default => null,
        };
    }

    private function publishedUrl(MenuItem $item): ?string
    {
        $record = $item->linkable;
        if (! $record instanceof Page && ! $record instanceof Article) return null;
        if ($record->status !== 'published') return null;
        return $record ? route('editorial.show', $record->slug) : null;
    }

    private function modelUrl(MenuItem $item, string $model, string $route): ?string
    {
        $record = $item->linkable;
        if (! $record instanceof $model) return null;
        return $record ? route($route, $record->slug) : null;
    }

    private function cityUrl(?string $slug): ?string
    {
        if (! $slug || ! ($city = $this->cities->cityForSlug($slug))) return null;
        return route('cities.show', $city->slug);
    }

    public function validInternalUrl(string $url): bool { return str_starts_with($url, '/') && ! str_starts_with($url, '//') && ! str_contains($url, '\\') && ! preg_match('/[\x00-\x1F]/', $url); }
    public function validExternalUrl(string $url): bool { $parts = parse_url($url); return filter_var($url, FILTER_VALIDATE_URL) !== false && in_array($parts['scheme'] ?? null, ['http', 'https'], true); }
}
