<?php
namespace App\Filament\Resources;
use Filament\Resources\Resource;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use App\Services\PublicUrl;
abstract class AdminResource extends Resource
{
    public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && auth()->user()?->status === 'active'; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }
    public static function auditAction(string $event): string { return strtolower(class_basename(static::getModel())).'.'.$event; }
    public static function viewOnSiteAction(): Action
    {
        return Action::make('viewOnSite')->label('Voir sur le site')->icon('heroicon-o-arrow-top-right-on-square')
            ->url(fn (Model $record): ?string => app(PublicUrl::class)->for($record))
            ->openUrlInNewTab()->visible(fn (Model $record): bool => app(PublicUrl::class)->for($record) !== null);
    }
}
