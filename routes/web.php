<?php

use Illuminate\Support\Facades\Route;
use App\Models\Article;
use App\Models\Comment;
use App\Models\Page;
use App\Models\Restaurant;
use App\Models\RestaurantReview;
use App\Http\Controllers\PreviewCommentController;
use App\Http\Controllers\PreviewRestaurantReviewController;
use App\Http\Controllers\{AccountController, AuthController, ClaimModerationController, EmailVerificationController, NewPasswordController, OwnerRestaurantController, PasswordChangeController, PasswordResetLinkController, RegisteredUserController, RestaurantClaimController};
use App\Http\Controllers\MediaController;
use App\Http\Controllers\RestaurantOutboundController;
use App\Http\Controllers\{PublicContentController, RobotsController, SitemapController};
use App\Http\Controllers\Admin\{ContentController as AdminContentController, DashboardController, MediaLibraryController, ModerationController, RedirectController, RestaurantController as AdminRestaurantController, SettingsController, TaxonomyController, UserController as AdminUserController};

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

Route::prefix('bo')->middleware(['auth', 'password.change.required', 'admin'])->name('admin.')->group(function (): void {
    Route::get('/', DashboardController::class)->name('dashboard');
    Route::get('restaurants', [AdminRestaurantController::class, 'index'])->name('restaurants.index');
    Route::get('restaurants/create', [AdminRestaurantController::class, 'create'])->name('restaurants.create');
    Route::post('restaurants', [AdminRestaurantController::class, 'store'])->name('restaurants.store');
    Route::get('restaurants/{restaurant}/edit', [AdminRestaurantController::class, 'edit'])->name('restaurants.edit');
    Route::put('restaurants/{restaurant}', [AdminRestaurantController::class, 'update'])->name('restaurants.update');
    Route::patch('restaurants/{restaurant}/archive', [AdminRestaurantController::class, 'archive'])->name('restaurants.archive');
    Route::get('reviews', [ModerationController::class, 'reviews'])->name('reviews.index');
    Route::patch('reviews/{review}', [ModerationController::class, 'reviewStatus'])->name('reviews.status');
    Route::get('comments', [ModerationController::class, 'comments'])->name('comments.index');
    Route::patch('comments/{comment}', [ModerationController::class, 'commentStatus'])->name('comments.status');
    Route::get('claims', [ClaimModerationController::class, 'index'])->name('claims.index');
    Route::patch('claims/{claim}/approve', [ClaimModerationController::class, 'approve'])->name('claims.approve');
    Route::patch('claims/{claim}/reject', [ClaimModerationController::class, 'reject'])->name('claims.reject');
    Route::get('users', [AdminUserController::class, 'index'])->name('users.index'); Route::patch('users/{user}', [AdminUserController::class, 'update'])->name('users.update'); Route::post('users/{user}/reset-password', [AdminUserController::class, 'reset'])->name('users.reset');
    Route::get('content/{type}', [AdminContentController::class, 'index'])->name('content.index'); Route::get('content/{type}/create', [AdminContentController::class, 'create'])->name('content.create'); Route::post('content/{type}', [AdminContentController::class, 'store'])->name('content.store'); Route::get('content/{type}/{id}/edit', [AdminContentController::class, 'edit'])->name('content.edit'); Route::put('content/{type}/{id}', [AdminContentController::class, 'update'])->name('content.update');
    Route::get('media', [MediaLibraryController::class, 'index'])->name('media.index'); Route::post('media', [MediaLibraryController::class, 'store'])->name('media.store'); Route::put('media/{asset}', [MediaLibraryController::class, 'update'])->name('media.update');
    Route::get('redirects', [RedirectController::class, 'index'])->name('redirects.index'); Route::get('redirects/create', [RedirectController::class, 'create'])->name('redirects.create'); Route::post('redirects', [RedirectController::class, 'store'])->name('redirects.store'); Route::get('redirects/{rule}/edit', [RedirectController::class, 'edit'])->name('redirects.edit'); Route::put('redirects/{rule}', [RedirectController::class, 'update'])->name('redirects.update'); Route::delete('redirects/{rule}', [RedirectController::class, 'destroy'])->name('redirects.destroy'); Route::post('redirects/test', [RedirectController::class, 'test'])->name('redirects.test');
    Route::get('taxonomies/{type}', [TaxonomyController::class, 'index'])->name('taxonomies.index'); Route::post('taxonomies/{type}', [TaxonomyController::class, 'store'])->name('taxonomies.store'); Route::put('taxonomies/{type}/{id}', [TaxonomyController::class, 'update'])->name('taxonomies.update'); Route::delete('taxonomies/{type}/{id}', [TaxonomyController::class, 'destroy'])->name('taxonomies.destroy');
    Route::get('settings', [SettingsController::class, 'edit'])->name('settings.edit'); Route::put('settings', [SettingsController::class, 'update'])->name('settings.update');
});

Route::get('/blog', [PublicContentController::class, 'blog'])->name('blog.index');
Route::get('/restaurants', [PublicContentController::class, 'index'])->name('restaurants.index');
Route::post('/restaurants/autour-de-moi', [PublicContentController::class, 'nearMe'])->middleware('throttle:20,1')->name('restaurants.near-me');
Route::get('/resto/{slug}', [PublicContentController::class, 'restaurant'])->name('restaurants.show');
Route::post('/resto/{slug}/avis', [PublicContentController::class, 'storeReview'])->middleware('throttle:10,1')->name('restaurants.reviews.store');
Route::get('/restos/{slug}', [PublicContentController::class, 'location'])->name('locations.show');
Route::get('/specialites/{slug}', [PublicContentController::class, 'category'])->name('categories.show');
Route::get('/service/{slug}', [PublicContentController::class, 'feature'])->name('features.show');
Route::post('/{slug}/commentaires', [PublicContentController::class, 'storeComment'])->middleware('throttle:10,1')->where('slug', '[a-z0-9-]+')->name('editorial.comments.store');
Route::get('/{slug}', [PublicContentController::class, 'editorial'])->where('slug', '[a-z0-9-]+')->name('editorial.show');
