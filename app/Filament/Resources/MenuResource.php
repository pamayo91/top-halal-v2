<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\{Article, Category, Feature, Menu, MenuItem, Page};
use App\Services\CityPageResolver;
use Filament\Actions\{CreateAction, EditAction};
use Filament\Forms\Components\{Hidden, Repeater, Select, TextInput, Toggle};
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MenuResource extends AdminResource
{
    protected static ?string $model = Menu::class;
    protected static ?string $recordTitleAttribute = 'name';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-bars-3';
    protected static string|\UnitEnum|null $navigationGroup = 'Navigation';
    protected static ?string $navigationLabel = 'Menus';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Menu')->columns(2)->schema([
                TextInput::make('name')->label('Nom')->required()->maxLength(120)->live(onBlur: true)->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug((string) $state))),
                TextInput::make('slug')->label('Identifiant technique')->required()->alphaDash()->maxLength(100)->unique(ignoreRecord: true),
                Select::make('location')->label('Emplacement')->required()->options([
                    'header_main' => 'Header principal', 'footer_1' => 'Footer — Colonne 1', 'footer_2' => 'Footer — Colonne 2',
                    'footer_3' => 'Footer — Colonne 3', 'footer_4' => 'Footer — Colonne 4', 'footer_legal' => 'Footer — Légal',
                ])->unique(ignoreRecord: true),
                Toggle::make('is_active')->label('Actif')->default(true),
            ]),
            Section::make('Éléments')->description('Glissez-déposez les éléments. Un sous-menu est limité à ce second niveau. Une destination dépubliée ou supprimée est ignorée publiquement.')->schema([
                Repeater::make('rootItems')->relationship()->orderColumn('sort_order')->reorderableWithButtons()->collapsible()->itemLabel(fn (array $state): ?string => $state['label'] ?? null)->schema([
                    ...static::itemFields(),
                    Repeater::make('children')->relationship()->orderColumn('sort_order')->reorderableWithButtons()->collapsible()->itemLabel(fn (array $state): ?string => $state['label'] ?? null)->schema(static::itemFields()),
                ])->addActionLabel('Ajouter un élément'),
            ])->visibleOn('edit'),
        ]);
    }

    /** @return array<int, mixed> */
    private static function itemFields(): array
    {
        return [
            TextInput::make('label')->label('Libellé')->required()->maxLength(160),
            Select::make('link_type')->label('Type de destination')->options(MenuItem::LINK_TYPES)->required()->default('none')->live(),
            Hidden::make('linkable_type')->dehydrateStateUsing(fn ($state, Get $get): ?string => MenuItem::linkableClassFor($get('link_type'))),
            Select::make('linkable_id')->label('Destination')->searchable()->options(fn (Get $get): array => match ($get('link_type')) {
                'page' => Page::query()->orderBy('title')->pluck('title', 'id')->all(),
                'article' => Article::query()->orderBy('title')->pluck('title', 'id')->all(),
                'category' => Category::query()->orderBy('name')->pluck('name', 'id')->all(),
                'feature' => Feature::query()->orderBy('name')->pluck('name', 'id')->all(),
                default => [],
            })->visible(fn (Get $get): bool => in_array($get('link_type'), ['page', 'article', 'category', 'feature'], true))->required(fn (Get $get): bool => in_array($get('link_type'), ['page', 'article', 'category', 'feature'], true)),
            Select::make('destination_key')->label('Ville')->searchable()->options(fn (): array => app(CityPageResolver::class)->cities()->mapWithKeys(fn (object $city): array => [$city->slug => $city->city_name.($city->is_ambiguous ? ' — '.$city->department['name'] : '')])->all())->visible(fn (Get $get): bool => $get('link_type') === 'city')->required(fn (Get $get): bool => $get('link_type') === 'city'),
            TextInput::make('url')->label('URL')->maxLength(2048)->visible(fn (Get $get): bool => in_array($get('link_type'), ['internal_url', 'external_url'], true))->required(fn (Get $get): bool => in_array($get('link_type'), ['internal_url', 'external_url'], true))->helperText('Interne : chemin commençant par /. Externe : http:// ou https://.'),
            Toggle::make('is_active')->label('Actif')->default(true),
            Toggle::make('visible_desktop')->label('Visible desktop')->default(true),
            Toggle::make('visible_mobile')->label('Visible mobile')->default(true),
            Toggle::make('target_blank')->label('Ouvrir dans un nouvel onglet')->default(false),
            Toggle::make('nofollow')->label('nofollow')->default(false),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->label('Nom')->searchable()->sortable(),
            TextColumn::make('location')->label('Emplacement')->badge(),
            IconColumn::make('is_active')->label('Actif')->boolean(),
            TextColumn::make('updated_at')->label('Modifié')->dateTime('d/m/Y H:i')->sortable(),
        ])->recordActions([EditAction::make()])->headerActions([CreateAction::make()])->defaultSort('location');
    }

    public static function getPages(): array { return ['index' => Pages\ListMenus::route('/'), 'create' => Pages\CreateMenu::route('/create'), 'edit' => Pages\EditMenu::route('/{record}/edit')]; }
}
