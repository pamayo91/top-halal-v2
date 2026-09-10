<?php

namespace App\Filament\Pages;

use App\Models\{Menu, Setting};
use App\Services\{AdminAudit, PublicNavigation};
use Filament\Forms\Components\{Select, TextInput, Toggle};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HeaderNavigationPage extends Page
{
    protected static ?string $slug = 'navigation/header'; protected static ?string $title = 'Header';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-window'; protected static string|\UnitEnum|null $navigationGroup = 'Navigation'; protected static ?string $navigationLabel = 'Header'; protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.settings-page'; public ?array $data = [];
    public function mount(): void { $this->form->fill((array) (Setting::where('key', 'header_navigation')->value('value') ?? [])); }
    public function form(Schema $schema): Schema { return $schema->components([
        Section::make('Navigation')->schema([Select::make('menu_id')->label('Menu principal')->options(Menu::where('location', 'header_main')->pluck('name', 'id'))->required()]),
        Section::make('CTA Mon compte')->columns(2)->schema([Toggle::make('account_visible')->label('Afficher')->default(true), TextInput::make('account_label')->label('Libellé')->required()->maxLength(80)->default('Mon compte')]),
        Section::make('CTA Ajouter un restaurant')->columns(2)->schema([Toggle::make('submission_visible')->label('Afficher')->default(true), TextInput::make('submission_label')->label('Libellé')->required()->maxLength(80)->default('Ajouter un restaurant')]),
    ])->statePath('data'); }
    public function save(): void { Setting::updateOrCreate(['key' => 'header_navigation'], ['group' => 'navigation', 'value' => $this->form->getState()]); app(PublicNavigation::class)->forget(); app(AdminAudit::class)->record('navigation.header.updated', 'header_navigation'); Notification::make()->title('Header enregistré')->success()->send(); }
}
