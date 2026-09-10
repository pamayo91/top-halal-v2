<?php

namespace App\Filament\Pages;

use App\Models\{Menu, Setting};
use App\Services\{AdminAudit, PublicNavigation};
use Filament\Forms\Components\{Repeater, Select, Textarea, TextInput, Toggle};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class FooterNavigationPage extends Page
{
    protected static ?string $slug = 'navigation/footer'; protected static ?string $title = 'Footer';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack'; protected static string|\UnitEnum|null $navigationGroup = 'Navigation'; protected static ?string $navigationLabel = 'Footer'; protected static ?int $navigationSort = 3;
    protected string $view = 'filament.pages.settings-page'; public ?array $data = [];
    public function mount(): void { $this->form->fill((array) (Setting::where('key', 'footer_navigation')->value('value') ?? [])); }
    public function form(Schema $schema): Schema { $menus = Menu::where('is_active', true)->pluck('name', 'id')->all(); return $schema->components([
        Section::make('Présentation')->schema([Textarea::make('introduction')->label('Texte de présentation')->maxLength(1000), Toggle::make('show_logo')->label('Afficher le logo')->default(true), TextInput::make('copyright')->label('Copyright')->maxLength(200)]),
        Section::make('Colonnes')->columns(2)->schema([Select::make('column_menu_ids.0')->label('Colonne 1')->options($menus)->nullable(), Select::make('column_menu_ids.1')->label('Colonne 2')->options($menus)->nullable(), Select::make('column_menu_ids.2')->label('Colonne 3')->options($menus)->nullable(), Select::make('column_menu_ids.3')->label('Colonne 4')->options($menus)->nullable(), Select::make('legal_menu_id')->label('Menu légal')->options($menus)->nullable()]),
        Section::make('Réseaux sociaux')->description('Seuls les réseaux actifs avec une URL http(s) valide sont affichés.')->schema([Repeater::make('social_links')->reorderableWithButtons()->schema([TextInput::make('name')->label('Réseau')->required()->maxLength(80), TextInput::make('url')->label('URL')->maxLength(2048), Toggle::make('is_active')->label('Actif')->default(true)])->addActionLabel('Ajouter un réseau')]),
    ])->statePath('data'); }
    public function save(): void { Setting::updateOrCreate(['key' => 'footer_navigation'], ['group' => 'navigation', 'value' => $this->form->getState()]); app(PublicNavigation::class)->forget(); app(AdminAudit::class)->record('navigation.footer.updated', 'footer_navigation'); Notification::make()->title('Footer enregistré')->success()->send(); }
}
