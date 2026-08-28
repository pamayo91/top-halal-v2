<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RestaurantResource\Pages;
use App\Models\{Category, Feature, Location, Restaurant};
use App\Services\AdminAudit;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, DeleteAction, EditAction};
use Filament\Forms\Components\{MarkdownEditor, Select, Textarea, TextInput};
use Filament\Schemas\Components\{Section, Tabs};
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
            Tabs\Tab::make('Localisation')->schema([Section::make()->columns(2)->schema([
                TextInput::make('address')->label('Adresse')->maxLength(255), TextInput::make('postal_code')->label('Code postal')->maxLength(20),
                TextInput::make('city_name')->label('Ville affichée')->maxLength(255)->helperText('Utilisée en priorité ; les zones V2 restent associées ci-dessous.'),
                Select::make('locations')->label('Zones / villes V2')->relationship('locations', 'name')->multiple()->searchable()->preload(),
                TextInput::make('latitude')->numeric()->minValue(-90)->maxValue(90), TextInput::make('longitude')->numeric()->minValue(-180)->maxValue(180),
            ])]),
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
            TextColumn::make('name')->label('Restaurant')->searchable()->sortable()->description(fn (Restaurant $r) => $r->slug),
            TextColumn::make('city')->label('Ville')->state(fn (Restaurant $r) => $r->city_name ?: $r->locations->pluck('name')->join(', ') ?: '—')->searchable(query: fn (Builder $q, string $search) => $q->where('city_name', 'like', "%{$search}%")->orWhereHas('locations', fn (Builder $x) => $x->where('name', 'like', "%{$search}%"))),
            TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {'published'=>'success','pending'=>'warning','reported'=>'danger','archived'=>'gray',default=>'info'})->sortable(),
            TextColumn::make('categories.name')->label('Catégories')->badge()->separator(',')->limitList(2),
            TextColumn::make('reviews_count')->label('Avis')->numeric()->sortable()->toggleable(),
            TextColumn::make('legacy_published_at')->label('Créé (legacy)')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(),TextColumn::make('legacy_modified_at')->label('Modifié (legacy)')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
        ])->filters([
            SelectFilter::make('status')->options(['draft'=>'Brouillon','pending'=>'En attente','published'=>'Publié','reported'=>'Signalé','archived'=>'Archivé']),
            SelectFilter::make('location')->label('Ville / zone')->relationship('locations', 'name')->searchable()->preload(),
            SelectFilter::make('category')->label('Catégorie')->relationship('categories', 'name')->searchable()->preload(),
            TernaryFilter::make('photo')->label('Photo')->queries(true: fn (Builder $q) => $q->whereHas('media.asset'), false: fn (Builder $q) => $q->whereDoesntHave('media.asset')),
            TernaryFilter::make('reviews')->label('Avis')->queries(true: fn (Builder $q) => $q->has('reviews'), false: fn (Builder $q) => $q->doesntHave('reviews')),
        ])->recordActions([EditAction::make(), Action::make('archive')->label('Archiver')->color('gray')->requiresConfirmation()->visible(fn (Restaurant $r) => $r->status !== 'archived')->action(function (Restaurant $r): void {$r->update(['status'=>'archived']); app(AdminAudit::class)->record('restaurant.archived', $r);})])
          ->toolbarActions([BulkActionGroup::make([BulkAction::make('publish')->label('Publier')->requiresConfirmation()->action(function ($records): void {$records->each(function (Restaurant $r): void {$r->update(['status'=>'published']); app(AdminAudit::class)->record('restaurant.published', $r);});}), BulkAction::make('pending')->label('Passer en attente')->requiresConfirmation()->action(function ($records): void {$records->each(function (Restaurant $r): void {$r->update(['status'=>'pending']); app(AdminAudit::class)->record('restaurant.pending', $r);});})])])
          ->emptyStateHeading('Aucun restaurant')->emptyStateDescription('Créez une première fiche ou ajustez les filtres.')->defaultSort('updated_at', 'desc');
    }
    public static function getPages(): array { return ['index'=>Pages\ListRestaurants::route('/'),'create'=>Pages\CreateRestaurant::route('/create'),'edit'=>Pages\EditRestaurant::route('/{record}/edit')]; }
}
