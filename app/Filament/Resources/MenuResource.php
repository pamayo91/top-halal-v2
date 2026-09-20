<?php

namespace App\Filament\Resources;

use App\Filament\Resources\MenuResource\Pages;
use App\Models\Menu;
use Filament\Actions\{CreateAction, EditAction};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Schemas\Components\Section;
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
        ]);
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
