<?php

namespace App\Http\Controllers;

use App\Models\{Menu, MenuItem};
use App\Services\MenuItemOrderer;

class AdminMenuItemOrderController extends Controller
{
    public function __invoke(Menu $menu, MenuItem $item, int $direction, MenuItemOrderer $orderer)
    {
        $orderer->moveBy($menu, $item, $direction);

        return back()->with('status', 'Ordre du menu enregistré.');
    }
}
