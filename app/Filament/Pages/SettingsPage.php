<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Filament\Support\EditorialSidebarFields;
use App\Services\EditorialSidebar;
use App\Services\{AdminAudit, NearbyCityService};
use Filament\Forms\Components\{TextInput, Toggle};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class SettingsPage extends Page
{
    protected static ?string $slug = 'settings';
    protected static ?string $title = 'Réglages';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Système';
    protected static ?string $navigationLabel = 'Réglages';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.settings-page';
    public ?array $data = [];

    public function mount(): void
    {
        $settings = Setting::pluck('value', 'key');

        $this->form->fill([
            'site_name' => $settings['site_name']['text'] ?? 'Top Halal',
            'moderate_reviews' => $settings['moderate_reviews']['enabled'] ?? true,
            'moderate_comments' => $settings['moderate_comments']['enabled'] ?? true,
            'ai_disclosure_default' => $settings['ai_disclosure_default']['enabled'] ?? false,
            'city_nearby_radius_km' => $settings['city_nearby_radius_km']['value'] ?? NearbyCityService::DEFAULT_RADIUS_KM,
            'city_nearby_maximum' => $settings['city_nearby_maximum']['value'] ?? NearbyCityService::DEFAULT_LIMIT,
            'editorial_sidebar_articles' => $settings['editorial_sidebar_articles'] ?? ['blocks' => app(EditorialSidebar::class)->global('articles')],
            'editorial_sidebar_pages' => $settings['editorial_sidebar_pages'] ?? ['blocks' => app(EditorialSidebar::class)->global('pages')],
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Général')->schema([
                TextInput::make('site_name')->required()->maxLength(120),
                Toggle::make('moderate_reviews')->label('Modérer les avis'),
                Toggle::make('moderate_comments')->label('Modérer les commentaires'),
                Toggle::make('ai_disclosure_default')->label('Afficher par défaut la provenance IA'),
            ]),
            Section::make('SEO local — pages villes')
                ->description('Ces réglages s’appliquent à toutes les pages villes et invalident le cache de maillage géographique à l’enregistrement.')
                ->columns(2)
                ->schema([
                    TextInput::make('city_nearby_radius_km')
                        ->label('Rayon des villes aux alentours (km)')
                        ->integer()->minValue(1)->maxValue(NearbyCityService::MAX_RADIUS_KM)->required(),
                    TextInput::make('city_nearby_maximum')
                        ->label('Nombre maximum de villes proches')
                        ->integer()->minValue(1)->maxValue(NearbyCityService::MAX_LIMIT)->required(),
                ]),
            EditorialSidebarFields::section('editorial_sidebar_articles', 'Sidebar éditoriale — Articles')->description('Configuration par défaut des Articles. Les Articles affichent la sidebar sauf override explicite.'),
            EditorialSidebarFields::section('editorial_sidebar_pages', 'Sidebar éditoriale — Pages')->description('Configuration utilisée seulement lorsqu’une Page active explicitement sa sidebar.'),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        foreach ($data as $key => $value) {
            $nearbySetting = in_array($key, ['city_nearby_radius_km', 'city_nearby_maximum'], true);
            $sidebarSetting = in_array($key, ['editorial_sidebar_articles', 'editorial_sidebar_pages'], true);
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $sidebarSetting ? ['blocks' => app(EditorialSidebar::class)->normalize((array) ($value['blocks'] ?? []))] : ($nearbySetting ? ['value' => (int) $value] : (is_bool($value) ? ['enabled' => $value] : ['text' => $value])),
                    'group' => $sidebarSetting ? 'editorial' : ($nearbySetting ? 'seo' : 'general'),
                ],
            );
        }

        app(AdminAudit::class)->record('settings.updated', 'settings', array_keys($data));
        Notification::make()->title('Réglages enregistrés')->success()->send();
    }
}
