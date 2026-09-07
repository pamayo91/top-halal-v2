<?php
namespace App\Filament\Support;
use App\Services\EditorialSidebar;
use Filament\Forms\Components\{Repeater, Select, Textarea, TextInput, Toggle};
use Filament\Schemas\Components\Section;
class EditorialSidebarFields
{
    public static function section(string $statePath = 'editorial_sidebar_overrides', string $title = 'Sidebar éditoriale'): Section
    {
        return Section::make($title)->description('Les valeurs ajoutées remplacent seulement le bloc global correspondant. Les identifiants manuels sont séparés par des virgules.')->schema([Repeater::make($statePath.'.blocks')->label('Overrides de blocs')->reorderable()->collapsed()->schema(self::blockFields())->defaultItems(0)]);
    }
    public static function blockFields(): array
    {
        return [Select::make('type')->options(array_combine(EditorialSidebar::TYPES, ['Sommaire','Recherche resto','Restaurants liés','Articles liés','Signaler une erreur','Autour de moi','Restos à la une','Explorer aussi','Partager','Nous contacter']))->required(), Toggle::make('enabled')->label('Actif')->default(true), TextInput::make('title')->label('Titre personnalisé')->maxLength(120), TextInput::make('order')->label('Ordre')->integer()->minValue(1)->maxValue(20), Select::make('mode')->label('Sélection')->options(['auto'=>'Automatique','manual'=>'Manuelle'])->default('auto'), TextInput::make('limit')->label('Maximum')->integer()->minValue(1)->maxValue(6)->default(3), Textarea::make('ids')->label('IDs manuels (restaurants ou articles)')->rows(2), Textarea::make('links')->label('Liens Explorer aussi (libellé | URL, un par ligne)')->rows(3)];
    }
}
