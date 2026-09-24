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
}
