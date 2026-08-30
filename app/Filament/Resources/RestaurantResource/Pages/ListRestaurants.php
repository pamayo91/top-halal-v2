<?php

namespace App\Filament\Resources\RestaurantResource\Pages;

use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use Filament\Actions\{Action, CreateAction};
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListRestaurants extends ListRecords
{
    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->visible(fn (): bool => $this->activeTab !== 'trash'),
            Action::make('empty_trash')
                ->label('Vider la Corbeille')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Vider la Corbeille ?')
                ->modalDescription('Tous les restaurants présents dans la Corbeille seront supprimés définitivement. Cette action est irréversible.')
                ->modalSubmitActionLabel('Vider définitivement la Corbeille')
                ->visible(fn (): bool => $this->activeTab === 'trash' && Restaurant::onlyTrashed()->exists())
                ->action(fn () => RestaurantResource::emptyTrash()),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Restaurants')->badge(Restaurant::query()->count()),
            'trash' => Tab::make('Corbeille')->badge(Restaurant::onlyTrashed()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed()),
        ];
    }
}
