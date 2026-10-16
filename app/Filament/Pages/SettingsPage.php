<?php

namespace App\Filament\Pages;

use App\Models\{MediaAsset, Setting};
use App\Filament\Support\EditorialSidebarFields;
use App\Services\EditorialSidebar;
use App\Services\{AdminAudit, AnalyticsSettings, ErrorPageSettings, NearbyCityService};
use Closure;
use Illuminate\Validation\ValidationException;
use Filament\Forms\Components\{Select, Textarea, TextInput, Toggle};
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
        $analytics = app(AnalyticsSettings::class)->values();

        $this->form->fill([
            'site_name' => $settings['site_name']['text'] ?? 'Top Halal',
            'moderate_reviews' => $settings['moderate_reviews']['enabled'] ?? true,
            'moderate_comments' => $settings['moderate_comments']['enabled'] ?? true,
            'ai_disclosure_default' => $settings['ai_disclosure_default']['enabled'] ?? false,
            'city_nearby_radius_km' => $settings['city_nearby_radius_km']['value'] ?? NearbyCityService::DEFAULT_RADIUS_KM,
            'city_nearby_maximum' => $settings['city_nearby_maximum']['value'] ?? NearbyCityService::DEFAULT_LIMIT,
            'editorial_sidebar_articles' => $settings['editorial_sidebar_articles'] ?? ['blocks' => app(EditorialSidebar::class)->global('articles')],
            'editorial_sidebar_pages' => $settings['editorial_sidebar_pages'] ?? ['blocks' => app(EditorialSidebar::class)->global('pages')],
            'error_404' => $settings[ErrorPageSettings::SETTINGS_KEY] ?? [],
            'analytics_ga4_enabled' => $analytics['enabled'],
            'analytics_ga4_measurement_id' => $analytics['measurement_id'],
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
            Section::make('Google Analytics')
                ->description('Prépare uniquement la configuration GA4. Aucun script, cookie ou suivi n’est encore chargé sur le site public.')
                ->schema([
                    Toggle::make('analytics_ga4_enabled')
                        ->label('Activer Google Analytics'),
                    TextInput::make('analytics_ga4_measurement_id')
                        ->label('ID de mesure GA4')
                        ->placeholder('G-XXXXXXXXXX')
                        ->helperText('Exemple : G-XXXXXXXXXX. Cet identifiant n’est pas un secret.')
                        ->nullable()
                        ->maxLength(34)
                        ->requiredIf('analytics_ga4_enabled', true)
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! AnalyticsSettings::isValidMeasurementId(AnalyticsSettings::normalizeMeasurementId($value))) {
                                    $fail('Saisissez un ID de mesure GA4 valide, au format G-XXXXXXXXXX.');
                                }
                            },
                        ])
                        ->dehydrateStateUsing(fn (?string $state): ?string => AnalyticsSettings::normalizeMeasurementId($state))
                        ->validationMessages([
                            'required' => 'Un ID de mesure GA4 valide est requis pour activer Google Analytics.',
                        ]),
                ]),
            EditorialSidebarFields::section('editorial_sidebar_articles', 'Sidebar éditoriale — Articles')->description('Configuration par défaut des Articles. Les Articles affichent la sidebar sauf override explicite.'),
            EditorialSidebarFields::section('editorial_sidebar_pages', 'Sidebar éditoriale — Pages')->description('Configuration utilisée seulement lorsqu’une Page active explicitement sa sidebar.'),
            Section::make('Page 404')
                ->description('Le code HTTP, les liens et les repères de confiance restent fixes. Laissez l’illustration vide pour utiliser l’illustration validée par défaut.')
                ->schema([
                    Select::make('error_404.illustration_media_asset_id')
                        ->label('Illustration')
                        ->options(fn () => MediaAsset::query()->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)->orderByDesc('created_at')->limit(500)->get()->mapWithKeys(fn (MediaAsset $asset) => [$asset->id => trim(($asset->alt_text ?: 'Sans texte alternatif').' — '.$asset->width.' × '.$asset->height.' px')]))
                        ->searchable()
                        ->nullable(),
                    TextInput::make('error_404.title')->label('Titre')->maxLength(160)->placeholder(ErrorPageSettings::DEFAULT_TITLE),
                    Textarea::make('error_404.text')->label('Texte')->maxLength(300)->rows(3)->placeholder(ErrorPageSettings::DEFAULT_TEXT),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();
        $analyticsEnabled = (bool) ($data['analytics_ga4_enabled'] ?? false);
        $measurementId = AnalyticsSettings::normalizeMeasurementId($data['analytics_ga4_measurement_id'] ?? null);

        if ($analyticsEnabled && ! AnalyticsSettings::isValidMeasurementId($measurementId)) {
            throw ValidationException::withMessages([
                'data.analytics_ga4_measurement_id' => 'Un ID de mesure GA4 valide est requis pour activer Google Analytics.',
            ]);
        }

        unset($data['analytics_ga4_enabled'], $data['analytics_ga4_measurement_id']);

        foreach ($data as $key => $value) {
            $nearbySetting = in_array($key, ['city_nearby_radius_km', 'city_nearby_maximum'], true);
            $sidebarSetting = in_array($key, ['editorial_sidebar_articles', 'editorial_sidebar_pages'], true);
            $errorPageSetting = $key === ErrorPageSettings::SETTINGS_KEY;
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $sidebarSetting ? ['blocks' => app(EditorialSidebar::class)->normalize((array) ($value['blocks'] ?? []))] : ($errorPageSetting ? array_filter((array) $value, fn ($item) => $item !== null && $item !== '') : ($nearbySetting ? ['value' => (int) $value] : (is_bool($value) ? ['enabled' => $value] : ['text' => $value]))),
                    'group' => $sidebarSetting ? 'editorial' : ($errorPageSetting ? 'error_pages' : ($nearbySetting ? 'seo' : 'general')),
                ],
            );

            if ($errorPageSetting && ! empty($value['illustration_media_asset_id']) && ! Setting::query()->where('key', ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY)->exists()) {
                Setting::create([
                    'key' => ErrorPageSettings::DEFAULT_ILLUSTRATION_KEY,
                    'group' => 'error_pages',
                    'value' => ['media_asset_id' => (int) $value['illustration_media_asset_id']],
                ]);
            }
        }

        Setting::updateOrCreate(
            ['key' => AnalyticsSettings::ENABLED_KEY],
            ['value' => ['enabled' => $analyticsEnabled], 'group' => 'analytics'],
        );
        Setting::updateOrCreate(
            ['key' => AnalyticsSettings::MEASUREMENT_ID_KEY],
            ['value' => ['value' => $measurementId], 'group' => 'analytics'],
        );

        app(AdminAudit::class)->record('settings.updated', 'settings', [...array_keys($data), 'analytics_ga4_enabled', 'analytics_ga4_measurement_id']);
        Notification::make()->title('Réglages enregistrés')->success()->send();
    }
}
