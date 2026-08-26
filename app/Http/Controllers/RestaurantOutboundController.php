<?php
namespace App\Http\Controllers;
use App\Models\RestaurantOutboundLink;
use Illuminate\Http\RedirectResponse;
class RestaurantOutboundController extends Controller { public function __invoke(string $token): RedirectResponse { $link = RestaurantOutboundLink::where('token', $token)->where('is_active', true)->firstOrFail(); abort_unless(filter_var($link->destination_url, FILTER_VALIDATE_URL) && in_array(parse_url($link->destination_url, PHP_URL_SCHEME), ['http', 'https'], true), 404); $link->increment('click_count'); return redirect()->away($link->destination_url, 302); } }
