<?php
namespace App\Filament\Resources;
use Filament\Resources\Resource;
use Illuminate\Database\Eloquent\Model;
abstract class AdminResource extends Resource
{
    public static function canViewAny(): bool { return auth()->user()?->role === 'admin' && auth()->user()?->status === 'active'; }
    public static function canCreate(): bool { return static::canViewAny(); }
    public static function canEdit(Model $record): bool { return static::canViewAny(); }
    public static function canDelete(Model $record): bool { return static::canViewAny(); }
    public static function auditAction(string $event): string { return strtolower(class_basename(static::getModel())).'.'.$event; }
}
