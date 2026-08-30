<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Restaurant;
use App\Models\RestaurantReview;
use App\Http\Controllers\PreviewCommentController;
use App\Http\Controllers\PreviewRestaurantReviewController;
use App\Http\Controllers\{AccountController, AuthController, EmailVerificationController, NewPasswordController, OwnerRestaurantController, PasswordChangeController, PasswordResetLinkController, RegisteredUserController, RestaurantClaimController};
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RestaurantOutboundController;
use App\Http\Controllers\AdminAddressAutocompleteController;
use App\Http\Controllers\{PublicContentController, RobotsController, SitemapController};

Route::get('/', [PublicContentController::class, 'home'])->name('home');

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'service' => 'top-halal-v2',
    ]);
})->name('health');
Route::get('/robots.txt', RobotsController::class)->name('robots');
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/media/{asset}/{width?}', [MediaController::class, 'show'])->whereNumber('asset')->whereNumber('width')->name('media.show');
Route::get('/sortie/{token}', RestaurantOutboundController::class)->where('token', '[A-Za-z0-9_-]{20,64}')->name('restaurants.outbound');
Route::get('/admin/location/autocomplete', AdminAddressAutocompleteController::class)->middleware(['auth', 'admin', 'throttle:address-autocomplete'])->name('admin.location.autocomplete');

Route::get('/_preview/{type}/{legacyId}', function (string $type, int $legacyId) {
    $model = $type === 'post' ? Article::class : ($type === 'page' ? Page::class : abort(404));
    $content = $model::where('legacy_wp_id', $legacyId)->firstOrFail();
    $comments = Comment::query()
        ->where($type === 'post' ? 'article_id' : 'page_id', $content->id)
        ->where('status', 'approved')
        ->orderBy('created_at')
        ->get();
    return view('content-preview', compact('content', 'comments', 'type', 'legacyId'));
})->whereIn('type', ['post', 'page']);

Route::post('/_preview/{type}/{legacyId}/comments', [PreviewCommentController::class, 'store'])
    ->middleware('throttle:10,1')
    ->whereIn('type', ['post', 'page']);

Route::get('/_preview/restaurant/{legacyId}', function (int $legacyId) {
    $restaurant = Restaurant::where('legacy_wp_id', $legacyId)->firstOrFail();
    $reviews = $restaurant->reviews()->where('status', 'approved')->orderBy('created_at')->get();
    return view('restaurant-preview', ['restaurant' => $restaurant, 'reviews' => $reviews, 'aggregate' => $restaurant->approvedReviewAggregate()]);
});
Route::post('/_preview/restaurant/{legacyId}/reviews', [PreviewRestaurantReviewController::class, 'store'])->middleware('throttle:10,1');

// The former Blade back-office has been removed. Keep its reserved prefix
// outside public editorial routing and legacy redirects.
Route::any('/bo/{path?}', fn () => abort(404))->where('path', '.*');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:authentication')->name('login.store');
    Route::get('/register', [RegisteredUserController::class, 'create'])->name('register');
    Route::post('/register', [RegisteredUserController::class, 'store'])->middleware('throttle:authentication')->name('register.store');
    Route::get('/forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetLinkController::class, 'store'])->middleware('throttle:authentication')->name('password.email');
    Route::get('/reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('/reset-password', [NewPasswordController::class, 'store'])->middleware('throttle:authentication')->name('password.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/change-password', [PasswordChangeController::class, 'edit'])->name('password.change');
    Route::put('/change-password', [PasswordChangeController::class, 'update'])->name('password.change.store');
    Route::get('/verify-email', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/verify-email/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:6,1')->name('verification.send');

    Route::middleware('password.change.required')->group(function (): void {
        Route::get('/account', [AccountController::class, 'dashboard'])->name('account.dashboard');
        Route::get('/restaurants/{restaurant}/claim', [RestaurantClaimController::class, 'create'])->name('claims.create');
        Route::post('/restaurants/{restaurant}/claim', [RestaurantClaimController::class, 'store'])->middleware('throttle:5,1')->name('claims.store');
        Route::get('/claims/{claim}', [RestaurantClaimController::class, 'show'])->name('claims.show');
        Route::get('/account/restaurants/{restaurant}/edit', [OwnerRestaurantController::class, 'edit'])->name('owner.restaurants.edit');
        Route::put('/account/restaurants/{restaurant}', [OwnerRestaurantController::class, 'update'])->name('owner.restaurants.update');
    });
});


Route::get('/blog', [PublicContentController::class, 'blog'])->name('blog.index');
Route::get('/muslim-gourmet', [PublicContentController::class, 'muslimGourmet'])->name('blog.muslim-gourmet');
Route::get('/restaurants', [PublicContentController::class, 'index'])->name('restaurants.index');
Route::post('/restaurants/autour-de-moi', [PublicContentController::class, 'nearMe'])->middleware('throttle:20,1')->name('restaurants.near-me');
Route::get('/resto/{slug}', [PublicContentController::class, 'restaurant'])->name('restaurants.show');
Route::post('/resto/{slug}/avis', [PublicContentController::class, 'storeReview'])->middleware('throttle:10,1')->name('restaurants.reviews.store');
Route::get('/restos/{slug}', [PublicContentController::class, 'location'])->name('locations.show');
Route::get('/specialites/{slug}', [PublicContentController::class, 'category'])->name('categories.show');
Route::get('/service/{slug}', [PublicContentController::class, 'feature'])->name('features.show');
Route::post('/{slug}/commentaires', [PublicContentController::class, 'storeComment'])->middleware('throttle:10,1')->where('slug', '[a-z0-9-]+')->name('editorial.comments.store');
Route::get('/{slug}', [PublicContentController::class, 'editorial'])->where('slug', '[a-z0-9-]+')->name('editorial.show');
