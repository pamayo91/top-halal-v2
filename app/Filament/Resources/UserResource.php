<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\Comment;
use App\Models\RestaurantReview;
use App\Models\User;
use App\Services\AdminAudit;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, EditAction};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{Filter, SelectFilter};
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
        return parent::getEloquentQuery()->withCount([
            'ownedRestaurants',
            'submittedRestaurants',
            'ownerSubmissions',
            'legacyRestaurantAuthorships',
            'manageableSubmittedRestaurants',
            'claims',
            'claims as pending_claims_count' => fn (Builder $query) => $query->where('status', 'pending'),
            'claims as approved_claims_count' => fn (Builder $query) => $query->where('status', 'approved'),
            'claims as rejected_claims_count' => fn (Builder $query) => $query->where('status', 'rejected'),
        ])->selectSub(
            Comment::query()->selectRaw('count(*)')->where(fn (Builder $query) => $query
                ->whereColumn('comments.legacy_user_id', 'users.legacy_wp_user_id')
                ->orWhereColumn('comments.author_email', 'users.email')),
            'comments_count',
        )->selectSub(
            RestaurantReview::query()->selectRaw('count(*)')->where(fn (Builder $query) => $query
                ->whereColumn('restaurant_reviews.legacy_user_id', 'users.legacy_wp_user_id')
                ->orWhereColumn('restaurant_reviews.author_email', 'users.email')),
            'reviews_count',
        );
    }

    public static function originLabel(User $user): string
    {
        return $user->legacy_wp_user_id === null ? 'Inscription V2' : 'Migré WordPress';
    }

    /** Primary business profile. Rights remain relationship- and policy-based. */
    public static function profileLabel(User $user): string
    {
        if ($user->role === 'admin') return 'Administrateur';
        if (static::count($user, 'owned_restaurants') > 0 || static::count($user, 'legacy_restaurant_authorships') > 0) return 'Restaurateur';
        if (static::count($user, 'submitted_restaurants') > 0) {
            return static::count($user, 'owner_submissions') > 0 ? 'Déposant — gérant déclaré' : 'Déposant';
        }

        return 'Utilisateur';
    }

    public static function profileColor(User $user): string
    {
        return match (static::profileLabel($user)) {
            'Administrateur' => 'danger',
            'Restaurateur' => 'success',
            default => static::count($user, 'submitted_restaurants') > 0 ? 'warning' : 'gray',
        };
    }

    public static function connectionLabel(User $user): string
    {
        return $user->canLogIn() ? 'Possible' : 'Désactivée';
    }

    public static function managementLabel(User $user): string
    {
        if ($user->role === 'admin') return 'Accès global';
        if (static::count($user, 'owned_restaurants') > 0) return 'Propriétaire · '.static::count($user, 'owned_restaurants');
        if (static::count($user, 'manageable_submitted_restaurants') > 0) return 'Déposant · '.static::count($user, 'manageable_submitted_restaurants');

        return 'Aucun droit';
    }

    public static function restaurantLinkCount(User $user): int
    {
        return static::count($user, 'owned_restaurants')
            + static::count($user, 'submitted_restaurants')
            + static::count($user, 'legacy_restaurant_authorships');
    }

    public static function restaurantLinkSummary(User $user): string
    {
        return collect([
            ['count' => static::count($user, 'owned_restaurants'), 'label' => 'propriété'],
            ['count' => static::count($user, 'submitted_restaurants'), 'label' => 'dépôt'],
            ['count' => static::count($user, 'legacy_restaurant_authorships'), 'label' => 'historique'],
        ])->filter(fn (array $link): bool => $link['count'] > 0)
            ->map(fn (array $link): string => $link['count'].' '.$link['label'])
            ->implode(' · ') ?: 'Aucune relation';
    }

    public static function claimSummary(User $user): string
    {
        return collect([
            ['count' => $user->pending_claims_count, 'label' => 'en attente'],
            ['count' => $user->approved_claims_count, 'label' => 'approuvée(s)'],
            ['count' => $user->rejected_claims_count, 'label' => 'refusée(s)'],
        ])->filter(fn (array $claim): bool => $claim['count'] > 0)
            ->map(fn (array $claim): string => $claim['count'].' '.$claim['label'])
            ->implode(' · ') ?: 'Aucune revendication';
    }

    public static function moveToTrash(User $user): void
    {
        if (static::isProtectedFromDeletion($user) || $user->trashed()) return;

        $user->delete();
        app(AdminAudit::class)->record('user.trashed', $user, ['deleted_at' => $user->deleted_at]);
    }

    public static function moveManyToTrash(iterable $users): void
    {
        foreach ($users as $user) {
            if ($user instanceof User) static::moveToTrash($user);
        }
    }

    public static function restore(User $user): void
    {
        if (! $user->trashed()) return;

        $user->restore();
        app(AdminAudit::class)->record('user.restored', $user, ['deleted_at' => null]);
    }

    public static function forceDelete(User $user): void
    {
        if (static::isProtectedFromDeletion($user) || ! $user->trashed()) return;

        $changes = ['claims_deleted' => $user->claims()->count(), 'deleted_at' => $user->deleted_at];
        $user->forceDelete();
        app(AdminAudit::class)->record('user.force_deleted', $user, $changes);
    }

    private static function isProtectedFromDeletion(User $user): bool
    {
        return $user->role === 'admin' || $user->is(auth()->user());
    }

    private static function count(User $user, string $relation): int
    {
        $attribute = $relation.'_count';

        return $user->getAttribute($attribute) !== null
            ? (int) $user->getAttribute($attribute)
            : $user->{$relation}()->count();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Nom')->required()->maxLength(100),
            TextInput::make('email')->label('E-mail')->email()->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('password')->label('Mot de passe initial')->password()->revealable()->required()->confirmed()->minLength(12)->visibleOn('create'),
            TextInput::make('password_confirmation')->label('Confirmation du mot de passe')->password()->revealable()->required()->dehydrated(false)->visibleOn('create'),
            Select::make('role')->label('Accès technique')->options(['user' => 'Utilisateur', 'admin' => 'Administrateur'])->default('user')->required(),
            Select::make('status')->label('Statut')->options(['active' => 'Actif', 'disabled' => 'Désactivé'])->default('active')->required(),
            Toggle::make('must_change_password')->label('Forcer le changement de mot de passe')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable(['name', 'email'])->description(fn (User $user) => $user->email),
                TextColumn::make('origin')->label('Origine')->state(fn (User $user) => static::originLabel($user))->badge(),
                TextColumn::make('profile')->label('Profil')->state(fn (User $user) => static::profileLabel($user))->badge()->color(fn (User $user) => static::profileColor($user)),
                TextColumn::make('login_enabled')->label('Connexion')->state(fn (User $user) => static::connectionLabel($user))->badge()->color(fn (User $user) => $user->canLogIn() ? 'success' : 'gray'),
                TextColumn::make('management')->label('Gestion de fiches')->state(fn (User $user) => static::managementLabel($user))->badge(),
                TextColumn::make('restaurants_linked_count')->label('Relations restaurants')->state(fn (User $user) => static::restaurantLinkCount($user))->numeric()->description(fn (User $user) => static::restaurantLinkSummary($user)),
                TextColumn::make('reviews_count')->label('Avis')->numeric()->sortable(),
                TextColumn::make('comments_count')->label('Commentaires')->numeric()->sortable(),
                TextColumn::make('claims_count')->label('Revendications')->numeric()->description(fn (User $user) => static::claimSummary($user)),
                TextColumn::make('status')->badge(),
                TextColumn::make('must_change_password')->label('MDP à changer')->badge()->formatStateUsing(fn ($state) => $state ? 'Oui' : 'Non'),
                TextColumn::make('created_at')->label('Inscription')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('updated_at')->label('Modifié')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
            ])
            ->filters([
                Filter::make('profile')->label('Profil')->form([
                    Select::make('value')->label('Profil')->options([
                        'user' => 'Utilisateur',
                        'depositor' => 'Déposant',
                        'restaurateur' => 'Restaurateur',
                        'admin' => 'Administrateur',
                    ]),
                ])->query(function (Builder $query, array $data): Builder {
                    return match ($data['value'] ?? null) {
                        'admin' => $query->where('role', 'admin'),
                        'restaurateur' => $query->where('role', '!=', 'admin')->where(fn (Builder $profiles) => $profiles
                            ->whereHas('ownedRestaurants')
                            ->orWhereHas('legacyRestaurantAuthorships')),
                        'depositor' => $query->where('role', '!=', 'admin')
                            ->whereDoesntHave('ownedRestaurants')
                            ->whereDoesntHave('legacyRestaurantAuthorships')
                            ->whereHas('submittedRestaurants'),
                        'user' => $query->where('role', '!=', 'admin')
                            ->whereDoesntHave('ownedRestaurants')
                            ->whereDoesntHave('legacyRestaurantAuthorships')
                            ->whereDoesntHave('submittedRestaurants'),
                        default => $query,
                    };
                }),
                SelectFilter::make('status')->options(['active' => 'Actif', 'disabled' => 'Désactivé']),
                SelectFilter::make('login_enabled')->label('Connexion')->options(['1' => 'Possible', '0' => 'Désactivée']),
                Filter::make('without_business_activity')->label('Sans relation restaurant ni revendication')->query(fn (Builder $query) => $query->doesntHave('claims')->doesntHave('submittedRestaurants')->doesntHave('legacyRestaurantAuthorships')),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (User $user) => ! $user->trashed()),
                Action::make('trash')->label('Supprimer')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->modalHeading('Supprimer ce compte ?')
                    ->modalDescription('Le compte sera désactivé et placé dans la Corbeille. Ses demandes et son historique resteront conservés, et vous pourrez le restaurer.')
                    ->modalSubmitActionLabel('Mettre à la corbeille')
                    ->visible(fn (User $user) => ! $user->trashed() && ! static::isProtectedFromDeletion($user))
                    ->action(fn (User $user) => static::moveToTrash($user)),
                Action::make('restore')->label('Restaurer')->icon('heroicon-o-arrow-uturn-left')->color('success')
                    ->visible(fn (User $user) => $user->trashed())
                    ->action(fn (User $user) => static::restore($user)),
                Action::make('force_delete')->label('Supprimer définitivement')->icon('heroicon-o-trash')->color('danger')->requiresConfirmation()
                    ->modalHeading('Supprimer définitivement ce compte ?')
                    ->modalDescription('Cette action est irréversible. Le compte et ses demandes de revendication associées seront définitivement effacés.')
                    ->modalSubmitActionLabel('Supprimer définitivement')
                    ->visible(fn (User $user) => $user->trashed() && ! static::isProtectedFromDeletion($user))
                    ->action(fn (User $user) => static::forceDelete($user)),
                Action::make('reset')->label('Réinitialiser MDP')->requiresConfirmation()->visible(fn (User $user) => ! $user->trashed())->action(function (User $user): void {
                    if ($user->canLogIn()) {
                        Password::sendResetLink(['email' => $user->email]);
                        app(AdminAudit::class)->record('user.password_reset_sent', $user);
                    }
                }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('trash')
                        ->label('Supprimer la sélection')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer les comptes sélectionnés ?')
                        ->modalDescription('Les comptes sélectionnés seront désactivés et placés dans la Corbeille. Leurs demandes et leur historique resteront conservés. Les administrateurs ne peuvent pas être supprimés.')
                        ->modalSubmitActionLabel('Mettre à la corbeille')
                        ->visible(fn ($livewire): bool => $livewire->activeTab !== 'trash')
                        ->action(fn ($records) => static::moveManyToTrash($records)),
                    BulkAction::make('restore')
                        ->label('Restaurer la sélection')
                        ->icon('heroicon-o-arrow-uturn-left')
                        ->color('success')
                        ->visible(fn ($livewire): bool => $livewire->activeTab === 'trash')
                        ->action(fn ($records) => $records->each(fn (User $user) => static::restore($user))),
                    BulkAction::make('force_delete')
                        ->label('Supprimer définitivement la sélection')
                        ->icon('heroicon-o-trash')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->modalHeading('Supprimer définitivement les comptes ?')
                        ->modalDescription('Cette action est irréversible. Les comptes et leurs demandes de revendication associées seront définitivement effacés. Les administrateurs restent protégés.')
                        ->modalSubmitActionLabel('Supprimer définitivement')
                        ->visible(fn ($livewire): bool => $livewire->activeTab === 'trash')
                        ->action(fn ($records) => $records->each(fn (User $user) => static::forceDelete($user))),
                ]),
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
