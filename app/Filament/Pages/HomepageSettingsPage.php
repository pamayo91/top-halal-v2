<?php

namespace App\Filament\Pages;

use App\Services\{AdminAudit, HomepageSettings};
use Filament\Forms\Components\{Textarea, TextInput, Toggle};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HomepageSettingsPage extends Page
{
    protected static ?string $slug = 'homepage'; protected static ?string $title = "Page d'accueil"; protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home'; protected static string|\UnitEnum|null $navigationGroup = 'Contenu'; protected static ?string $navigationLabel = "Page d'accueil"; protected static ?int $navigationSort = 1; protected string $view = 'filament.pages.homepage-settings-page'; public ?array $data = [];
    public function mount(HomepageSettings $settings): void { $this->form->fill($settings->get()); }
    public function form(Schema $schema): Schema { return $schema->components([
        Section::make('SEO')->description("Emplacement : balises SEO de la page d'accueil.")->collapsible()->schema([TextInput::make('seo.title')->label('Title SEO')->required()->maxLength(255),Textarea::make('seo.description')->label('Meta description')->required()->rows(3)->maxLength(500)]),
        Section::make('Hero')->description('Emplacement : en haut de la page, autour du moteur de recherche.')->collapsible()->schema([TextInput::make('hero.eyebrow')->label('Surtitre')->maxLength(120),TextInput::make('hero.title')->label('Titre principal')->required()->maxLength(160),Textarea::make('hero.text')->label("Texte d'introduction")->required()->rows(3)->maxLength(500)]),
        $this->block('restaurant_block','Contenu restaurants halal','Emplacement : après Explorer par ville et spécialité'),
        Section::make('Le guide Top Halal')->description('Emplacement : introduction de la section des articles automatiques.')->collapsible()->schema([TextInput::make('guide.eyebrow')->label('Surtitre')->maxLength(120),TextInput::make('guide.title')->label('Titre')->required()->maxLength(160)]),
        $this->block('editorial_block','Contenu éditorial halal / Islam / actualités','Emplacement : après Le guide Top Halal'),
        Section::make('Pourquoi Top Halal ?')->description('Emplacement : après le contenu éditorial. Les quatre cartes, leur ordre et leurs icônes restent fixés par le code.')->collapsible()->schema([TextInput::make('why.title')->label('Titre')->required()->maxLength(160),...collect(range(0,3))->map(fn(int $i)=>Section::make('Carte '.($i+1))->compact()->columns(2)->schema([TextInput::make("why.cards.$i.title")->label('Titre')->required()->maxLength(160),Textarea::make("why.cards.$i.text")->label('Texte')->required()->rows(3)->maxLength(500)]))->all()]),
        $this->block('transparency_block','Informations et transparence','Emplacement : avant le CTA final « Vous connaissez une bonne adresse ? »'),
        Section::make('CTA Ajouter un restaurant')->description('Emplacement : CTA final. Le bouton et sa destination restent contrôlés par la route applicative.')->collapsible()->schema([TextInput::make('submission_cta.title')->label('Titre')->required()->maxLength(160),Textarea::make('submission_cta.text')->label('Texte')->required()->rows(3)->maxLength(500)]),
    ])->statePath('data'); }
    private function block(string $key,string $title,string $description): Section { return Section::make($title)->description($description)->collapsible()->schema([Toggle::make("$key.enabled")->label('Afficher ce bloc'),TextInput::make("$key.eyebrow")->label('Surtitre (facultatif)')->maxLength(120),TextInput::make("$key.title")->label('Titre')->required()->maxLength(160),Textarea::make("$key.text")->label('Texte')->required()->rows(4)->maxLength(1000)]); }
    public function save(HomepageSettings $settings): void { $settings->save($this->form->getState()); app(AdminAudit::class)->record('homepage.updated', HomepageSettings::SETTINGS_KEY, ['sections'=>array_keys($this->data ?? [])]); Notification::make()->title("Page d'accueil enregistrée")->success()->send(); }
}
