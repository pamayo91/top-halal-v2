<?php

namespace App\Services;

use App\Models\{Menu, MenuItem};
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MenuItemOrderer
{
    public function moveBy(Menu $menu, MenuItem $item, int $direction): void
    {
        if (! in_array($direction, [-1, 1], true)) throw new HttpException(422);
        if ($item->menu_id !== $menu->id) throw new HttpException(404);

        DB::transaction(function () use ($menu, $item, $direction): void {
            $siblings = MenuItem::query()
                ->where('menu_id', $menu->id)
                ->where('parent_id', $item->parent_id)
                ->orderBy('sort_order')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $index = $siblings->search(fn (MenuItem $sibling): bool => $sibling->is($item));

            if ($index === false || ! $siblings->has($index + $direction)) return;

            $ordered = $siblings->values();
            [$ordered[$index], $ordered[$index + $direction]] = [$ordered[$index + $direction], $ordered[$index]];
            foreach ($ordered as $order => $sibling) $sibling->updateQuietly(['sort_order' => $order + 1]);
        });

        app(PublicNavigation::class)->forget();
    }

    public function moveTo(Menu $menu, MenuItem $item, ?int $parentId, int $position): void
    {
        if ($item->menu_id !== $menu->id || $position < 0) throw new HttpException(404);

        DB::transaction(function () use ($menu, $item, $parentId, $position): void {
            $items = MenuItem::query()->where('menu_id', $menu->id)->orderBy('id')->lockForUpdate()->get();
            $item = $items->firstWhere('id', $item->id) ?? throw new HttpException(404);
            $parent = $parentId === null ? null : $items->firstWhere('id', $parentId);

            if ($parentId !== null && $parent === null) throw new HttpException(422, 'Le parent doit appartenir au même menu.');
            if ($parent?->id === $item->id || $parent?->parent_id !== null) throw new HttpException(422, 'Un sous-menu ne peut pas contenir de troisième niveau.');
            if ($parent !== null && $items->contains(fn (MenuItem $candidate): bool => $candidate->parent_id === $item->id)) {
                throw new HttpException(422, 'Un élément ayant des sous-menus doit rester à la racine.');
            }

            $oldParentId = $item->parent_id;
            $oldSiblings = $items->where('parent_id', $oldParentId)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();
            $oldSiblings = $oldSiblings->reject(fn (MenuItem $sibling): bool => $sibling->is($item))->values();
            $newSiblings = $parentId === $oldParentId
                ? $oldSiblings
                : $items->where('parent_id', $parentId)->sortBy([['sort_order', 'asc'], ['id', 'asc']])->values();

            $newSiblings->splice(min($position, $newSiblings->count()), 0, [$item]);
            $item->updateQuietly(['parent_id' => $parentId]);
            foreach ($oldSiblings as $order => $sibling) $sibling->updateQuietly(['sort_order' => $order + 1]);
            foreach ($newSiblings as $order => $sibling) $sibling->updateQuietly(['sort_order' => $order + 1]);
        });

        app(PublicNavigation::class)->forget();
    }
}
