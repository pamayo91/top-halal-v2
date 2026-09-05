<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Services\AdminAudit;
use Filament\Actions\{Action, EditAction};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Password;

class UserResource extends AdminResource
{
    protected static ?string $model = User::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-users';
    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';
    protected static ?int $navigationSort = 4;
    protected static ?string $navigationLabel = 'Utilisateurs';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('ownedRestaurants');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(100),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->label('Mot de passe initial')->password()->revealable()->required()->confirmed()->minLength(12)->visibleOn('create'),
            TextInput::make('password_confirmation')->label('Confirmation du mot de passe')->password()->revealable()->required()->dehydrated(false)->visibleOn('create'),
            Select::make('role')->label('Rôle')->options(['user' => 'Utilisateur', 'restaurant_owner' => 'Restaurateur', 'admin' => 'Administrateur'])->default('user')->required(),
            Select::make('status')->label('Statut')->options(['active' => 'Actif', 'disabled' => 'Désactivé'])->default('active')->required(),
            Toggle::make('must_change_password')->label('Forcer le changement de mot de passe')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->description(fn (User $user) => $user->email),
                TextColumn::make('role')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('email_verified_at')->label('E-mail vérifié')->dateTime('d/m/Y')->placeholder('Non'),
                TextColumn::make('owned_restaurants_count')->label('Restaurants')->numeric(),
                TextColumn::make('must_change_password')->label('MDP à changer')->badge()->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non'),
                TextColumn::make('created_at')->label('Inscription')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Modifié')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                SelectFilter::make('role')->options(['user' => 'Utilisateur', 'restaurant_owner' => 'Restaurateur', 'admin' => 'Administrateur']),
                SelectFilter::make('status')->options(['active' => 'Actif', 'disabled' => 'Désactivé']),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('reset')->label('Réinitialiser MDP')->requiresConfirmation()->action(function (User $user): void {
                    Password::sendResetLink(['email' => $user->email]);
                    app(AdminAudit::class)->record('user.password_reset_sent', $user);
                }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
