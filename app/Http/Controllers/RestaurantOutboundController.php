<?php
namespace App\Http\Controllers;
use App\Models\RestaurantOutboundLink;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
class RestaurantOutboundController extends Controller { public function __invoke(Request $request): RedirectResponse { $token = (string) $request->input('token'); abort_unless(preg_match('/^[A-Za-z0-9_-]{20,64}$/', $token) === 1, 404); $link = RestaurantOutboundLink::where('token', $token)->where('is_active', true)->firstOrFail(); abort_unless(filter_var($link->destination_url, FILTER_VALIDATE_URL) && in_array(parse_url($link->destination_url, PHP_URL_SCHEME), ['http', 'https'], true), 404); $link->increment('click_count'); return redirect()->away($link->destination_url, 303); } }
