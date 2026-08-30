<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use Filament\Actions\{Action, CreateAction};
use Filament\Resources\Pages\ListRecords;

class ListRestaurants extends ListRecords
{
    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('empty_trash')
                ->label('Vider la Corbeille')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vider la Corbeille ?')
                ->modalDescription('Tous les restaurants présents dans la Corbeille seront supprimés définitivement. Cette action est irréversible.')
                ->modalSubmitActionLabel('Vider définitivement la Corbeille')
                ->visible(fn (): bool => Restaurant::onlyTrashed()->exists())
                ->action(fn () => RestaurantResource::emptyTrash()),
        ];
    }
}
