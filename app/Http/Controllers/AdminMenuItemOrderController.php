<?php

namespace App\Http\Controllers;

use App\Models\{Menu, MenuItem};
use App\Services\MenuItemOrderer;
use Illuminate\Http\Request;

class AdminMenuItemOrderController extends Controller
{
    public function __invoke(Request $request, Menu $menu, MenuItem $item, int $direction, MenuItemOrderer $orderer)
    {
        if ($request->has('position')) {
            $data = $request->validate(['parent_id' => ['nullable', 'integer'], 'position' => ['required', 'integer', 'min:0']]);
            $orderer->moveTo($menu, $item, isset($data['parent_id']) ? (int) $data['parent_id'] : null, $data['position']);
        } else {
            $orderer->moveBy($menu, $item, $direction);
        }

        return back()->with('status', 'Ordre du menu enregistré.');
    }
}
