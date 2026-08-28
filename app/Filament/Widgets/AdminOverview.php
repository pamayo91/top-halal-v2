<?php
namespace App\Filament\Widgets;
use App\Models\{Article, Comment, Page, Restaurant, RestaurantClaim, RestaurantReview, User};
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
class AdminOverview extends StatsOverviewWidget
{
    protected function getStats(): array { return [Stat::make('Restaurants', Restaurant::count())->description(Restaurant::where('status', 'pending')->count().' en attente')->url('/admin/restaurants'), Stat::make('Avis à modérer', RestaurantReview::where('status', 'pending')->count())->url('/admin/reviews'), Stat::make('Commentaires à modérer', Comment::where('status', 'pending')->count())->url('/admin/comments'), Stat::make('Claims à traiter', RestaurantClaim::where('status', 'pending')->count())->url('/admin/claims'), Stat::make('Utilisateurs', User::count())->url('/admin/users'), Stat::make('Contenus', Article::count() + Page::count())->description(Article::count().' articles · '.Page::count().' pages')]; }
}
