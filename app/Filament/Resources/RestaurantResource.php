<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Models\{Category, Feature, Location, Restaurant};
use App\Services\AdminAudit;
use App\Services\Location\AddressSuggestionService;
use App\Services\Location\DuplicateRestaurantDetector;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, DeleteAction, EditAction};
use Filament\Forms\Components\{Hidden, MarkdownEditor, Select, Textarea, TextInput};
use Filament\Schemas\Components\{Section, Tabs, View};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{ImageColumn, TextColumn};
use Filament\Tables\Filters\{Filter, SelectFilter, TernaryFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class RestaurantResource extends AdminResource
{
    protected static ?string $model = Restaurant::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-building-storefront';
    protected static string|\UnitEnum|null $navigationGroup = 'Contenu';
    protected static ?int $navigationSort = 1;
    protected static ?string $navigationLabel = 'Restaurants';

    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->with(['categories', 'locations', 'media.asset'])->withCount('reviews'); }
    public static function getGloballySearchableAttributes(): array { return ['name', 'city_name', 'slug']; }
    public static function getGlobalSearchResultDetails(Model $record): array { return ['Ville' => $record->city_name ?: $record->locations->pluck('name')->join(', ') ?: 'Non renseignée', 'Statut' => $record->status]; }

    public static function archive(Restaurant $restaurant): void
    {
        $restaurant->update(['status' => 'archived']);
        app(AdminAudit::class)->record('restaurant.archived', $restaurant, ['status' => 'archived']);
    }

    public static function archiveAction(): Action
    {
        return Action::make('archive')
            ->label('Supprimer')
            ->icon('heroicon-o-archive-box-x-mark')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Supprimer ce restaurant ?')
            ->modalDescription('La fiche sera archivée et retirée des parcours publics. Ses données restent conservées et pourront être restaurées ultérieurement.')
            ->modalSubmitActionLabel('Archiver la fiche')
            ->visible(fn (Restaurant $restaurant) => $restaurant->status !== 'archived')
            ->action(fn (Restaurant $restaurant) => static::archive($restaurant));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([Tabs::make('Restaurant')->tabs([
            Tabs\Tab::make('Général')->schema([
                Section::make()->columns(2)->schema([
                    TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug((string) $state))),
                    TextInput::make('slug')->required()->alphaDash()->unique(ignoreRecord: true),
                    Select::make('status')->options(['draft'=>'Brouillon','pending'=>'En attente','published'=>'Publié','reported'=>'Signalé','archived'=>'Archivé'])->required()->default('draft'),
                    Textarea::make('description')->columnSpanFull()->rows(6)->maxLength(20000),
                ]),
            ]),
            Tabs\Tab::make('Localisation')->schema([
                Hidden::make('location_update_source')->default('manual'),
                Section::make('Données d’origine')->description("Donnée importée de l’ancien site, conservée pour référence.")->columns(2)->schema([
                    TextInput::make('address')->label("Adresse d’origine")->readOnly(),
                    TextInput::make('legacy_gps')->label('GPS historique / actuel')->readOnly()->dehydrated(false)->formatStateUsing(fn (Restaurant $r) => $r->latitude !== null ? $r->latitude.', '.$r->longitude : 'Non renseigné'),
                ]),
                Section::make('Adresse utilisée par Top-Halal')->columns(2)->schema([
                    Select::make('address_suggestion')->label('Rechercher une adresse')->placeholder('Commencez à saisir au moins 3 caractères')->searchable()->searchDebounce(350)->getSearchResultsUsing(function (string $search): array {
                        return collect(app(AddressSuggestionService::class)->suggest($search))->mapWithKeys(fn (array $item) => [$item['token'] => $item['label']])->all();
                    })->getOptionLabelUsing(fn (?string $value): ?string => ($feature = app(AddressSuggestionService::class)->resolve((string) $value)) ? app(AddressSuggestionService::class)->label($feature) : null)->live()->afterStateUpdated(function ($state, $set): void {
                        $service = app(AddressSuggestionService::class); $feature = $service->resolve((string) $state); if (!$feature) return;
                        foreach ($service->structured($feature) as $field => $value) $set($field, $value);
                        $set('location_update_source', 'autocomplete');
                    })->dehydrated(false)->columnSpanFull(),
                    TextInput::make('address_line1')->label('Adresse')->maxLength(255), TextInput::make('address_line2')->label('Complément')->maxLength(255), TextInput::make('postal_code')->label('Code postal')->maxLength(20),
                    TextInput::make('city_name')->label('Ville officielle')->maxLength(255), TextInput::make('city_code')->label('Code INSEE')->maxLength(10), TextInput::make('country_code')->label('Pays')->maxLength(2)->rule('nullable|size:2'),
                    Select::make('locations')->label('Zones associées Top-Halal')->helperText('Ces zones ne sont pas modifiées automatiquement lors d’un changement d’adresse.')->relationship('locations', 'name')->multiple()->searchable()->preload()->columnSpanFull(),
                ]),
                Section::make('Vérifiez la position de l’établissement')->schema([
                    View::make('filament.location-map')->viewData(['tileUrl' => config('location.map_tile_url'), 'tileAttribution' => config('location.map_tile_attribution')]),
                    TextInput::make('latitude')->id('location-latitude')->label('Latitude')->numeric()->readOnly()->minValue(-90)->maxValue(90), TextInput::make('longitude')->id('location-longitude')->label('Longitude')->numeric()->readOnly()->minValue(-180)->maxValue(180),
                ]),
                Section::make('Qualité de localisation')->columns(2)->schema([
                    TextInput::make('geocoding_status')->label('Adresse')->readOnly()->dehydrated(false), TextInput::make('geocoding_precision')->label('Précision GPS')->readOnly()->dehydrated(false),
                    TextInput::make('proximity_status')->label('Autour de moi')->readOnly()->dehydrated(false), TextInput::make('geocoding_provider')->label('Fournisseur')->readOnly()->dehydrated(false),
                    TextInput::make('geocoding_score')->label('Score')->readOnly()->dehydrated(false), TextInput::make('geocoded_at')->label('Dernier géocodage')->readOnly()->dehydrated(false)->formatStateUsing(fn ($state) => $state ? \Illuminate\Support\Carbon::parse($state)->locale('fr')->isoFormat('D MMMM YYYY à HH:mm') : 'Jamais'),
                    TextInput::make('manually_verified_at')->label('Position corrigée manuellement')->readOnly()->dehydrated(false)->formatStateUsing(fn ($state) => $state ? 'Oui, le '.\Illuminate\Support\Carbon::parse($state)->format('d/m/Y H:i') : 'Non'),
                    TextInput::make('duplicate_candidates')->label('Doublons potentiels')->readOnly()->dehydrated(false)->formatStateUsing(function (?Restaurant $record): string {
                        if (!$record) return 'La vérification sera disponible après création.';
                        $count = count(app(DuplicateRestaurantDetector::class)->candidates($record));
                        return $count ? $count.' établissement(s) similaire(s) trouvé(s) à proximité — vérifiez les fiches avant toute action.' : 'Aucun candidat proche.';
                    })->columnSpanFull(),
                ]),
            ]),
            Tabs\Tab::make('Catégories & caractéristiques')->schema([Section::make()->columns(2)->schema([
                Select::make('categories')->relationship('categories', 'name')->multiple()->searchable()->preload(),
                Select::make('features')->relationship('features', 'name')->multiple()->searchable()->preload(),
            ])]),
            Tabs\Tab::make('Contact')->schema([Section::make()->columns(2)->schema([TextInput::make('phone')->tel()->maxLength(100), TextInput::make('contact_email')->email()->maxLength(255)])]),
            Tabs\Tab::make('SEO')->schema([Section::make()->schema([TextInput::make('seo_title')->maxLength(255), Textarea::make('seo_description')->rows(3)->maxLength(500)])]),
            Tabs\Tab::make('Système')->schema([Section::make('Identifiants et historique')->columns(2)->schema([
                TextInput::make('legacy_wp_id')->disabled()->dehydrated(false)->label('ID WordPress'), TextInput::make('legacy_modified_at')->disabled()->dehydrated(false)->label('Dernière modification legacy'),
            ])->visibleOn('edit')]),
        ])->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('image')->label('')->getStateUsing(fn (Restaurant $r) => $r->media->first()?->asset ? route('media.show', $r->media->first()->asset) : null)->defaultImageUrl('/images/media-placeholder.svg')->circular(),
            TextColumn::make('name')->label('Restaurant')->searchable(['name', 'slug', 'address', 'phone', 'contact_email'])->sortable()->description(fn (Restaurant $r) => $r->slug),
            TextColumn::make('city')->label('Ville')->state(fn (Restaurant $r) => $r->city_name ?: $r->locations->pluck('name')->join(', ') ?: '—')->searchable(['city_name']),
            TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {'published'=>'success','pending'=>'warning','reported'=>'danger','archived'=>'gray',default=>'info'})->sortable(),
            TextColumn::make('geocoding_status')->label('Géo')->badge()->color(fn (?string $state) => match ($state) {'VERIFIED'=>'success','HIGH_CONFIDENCE'=>'info','APPROXIMATE'=>'warning','REVIEW_REQUIRED'=>'danger','MANUAL'=>'primary',default=>'gray'}),
            TextColumn::make('address_exception_reason')->label('Adresse à traiter')->state(function (Restaurant $r): string {
                if ($r->latitude === null || $r->longitude === null) return 'sans GPS';
                if (in_array($r->geocoding_status, ['APPROXIMATE', 'REVIEW_REQUIRED'], true)) return 'géocodage incomplet';
                if ($r->geocoding_status === 'MISSING') return 'données insuffisantes';
                return 'ambigu';
            })->badge()->color('warning')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('categories.name')->label('Catégories')->badge()->separator(',')->limitList(2),
            TextColumn::make('reviews_count')->label('Avis')->numeric()->sortable()->toggleable(),
            TextColumn::make('legacy_published_at')->label('Créé (legacy)')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(),TextColumn::make('legacy_modified_at')->label('Modifié (legacy)')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(['draft'=>'Brouillon','pending'=>'En attente','published'=>'Publié','reported'=>'Signalé','archived'=>'Archivé']),
            SelectFilter::make('location')->label('Ville / zone')->relationship('locations', 'name')->searchable()->preload(),
            SelectFilter::make('geocoding_status')->label('Qualité géographique')->options(['VERIFIED'=>'Vérifiée','HIGH_CONFIDENCE'=>'Confiance élevée','APPROXIMATE'=>'Approximative','REVIEW_REQUIRED'=>'À vérifier','MANUAL'=>'Manuelle','MISSING'=>'Manquante']),
            Filter::make('missing_gps')->label('GPS manquant')->query(fn (Builder $q) => $q->where(fn($x)=>$x->whereNull('latitude')->orWhereNull('longitude'))), Filter::make('without_city_code')->label('Sans code INSEE')->query(fn (Builder $q) => $q->whereNull('city_code')),
            Filter::make('address_to_process')->label('Adresse à traiter')->query(fn (Builder $q) => $q->where(function (Builder $missing): void { foreach (['address_line1', 'postal_code', 'city_name', 'city_code', 'country_code'] as $field) $missing->orWhereNull($field)->orWhere($field, ''); })),
            SelectFilter::make('category')->label('Catégorie')->relationship('categories', 'name')->searchable()->preload(),
            TernaryFilter::make('photo')->label('Photo')->queries(true: fn (Builder $q) => $q->whereHas('media.asset'), false: fn (Builder $q) => $q->whereDoesntHave('media.asset')),
            TernaryFilter::make('reviews')->label('Avis')->queries(true: fn (Builder $q) => $q->has('reviews'), false: fn (Builder $q) => $q->doesntHave('reviews')),
        ])->recordActions([static::viewOnSiteAction(), EditAction::make(), static::archiveAction()])
          ->toolbarActions([BulkActionGroup::make([BulkAction::make('publish')->label('Publier')->requiresConfirmation()->action(function ($records): void {$records->each(function (Restaurant $r): void {$r->update(['status'=>'published']); app(AdminAudit::class)->record('restaurant.published', $r);});}), BulkAction::make('pending')->label('Passer en attente')->requiresConfirmation()->action(function ($records): void {$records->each(function (Restaurant $r): void {$r->update(['status'=>'pending']); app(AdminAudit::class)->record('restaurant.pending', $r);});})])])
          ->emptyStateHeading('Aucun restaurant')->emptyStateDescription('Créez une première fiche ou ajustez les filtres.')->defaultSort('updated_at', 'desc');
    }
    public static function getPages(): array { return ['index'=>Pages\ListRestaurants::route('/'),'create'=>Pages\CreateRestaurant::route('/create'),'edit'=>Pages\EditRestaurant::route('/{record}/edit')]; }
}
