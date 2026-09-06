<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create')
                ->label('Ajouter un utilisateur')
                ->url(UserResource::getUrl('create')),
        ];
    }

    public function getTabs(): array
    {
        return [
            'active' => Tab::make('Utilisateurs')->badge(User::query()->count()),
            'trash' => Tab::make('Corbeille')->badge(User::onlyTrashed()->count())
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->onlyTrashed()),
        ];
    }
}
