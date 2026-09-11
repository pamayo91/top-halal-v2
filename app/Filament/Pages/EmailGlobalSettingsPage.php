<?php

namespace App\Filament\Pages;

use App\Models\MediaAsset;
use App\Services\{AdminAudit, EmailGlobalSettings};
use Filament\Forms\Components\{Select, Textarea, TextInput};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EmailGlobalSettingsPage extends Page
{
    protected static ?string $slug = 'email-global-settings';
    protected static ?string $title = 'Configuration globale';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-paint-brush';
    protected static string|\UnitEnum|null $navigationGroup = 'Emails';
    protected static ?string $navigationLabel = 'Configuration globale';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.settings-page';
    public ?array $data = [];

    public function mount(): void { $this->form->fill(app(EmailGlobalSettings::class)->values()); }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Layout commun des e-mails')->description('Appliqué automatiquement à tous les e-mails transactionnels. Les couleurs et le style reprennent le design du site.')
                ->columns(2)->schema([
                    TextInput::make('display_name')->label('Nom affiché')->required()->maxLength(120),
                    Select::make('logo_media_asset_id')->label('Logo e-mail')->options(fn () => MediaAsset::query()->whereIn('mime', MediaAsset::RESTAURANT_IMAGE_MIMES)->orderByDesc('id')->limit(200)->pluck('alt_text', 'id')->map(fn ($label, $id) => filled($label) ? $label.' (#'.$id.')' : 'Image #'.$id)->all())->searchable()->preload()->helperText('Optionnel. Le texte affiché sous le logo reprend automatiquement « Texte de présentation » dans Navigation > Footer.'),
                    Textarea::make('footer_text')->label('Texte du footer')->required()->rows(4)->maxLength(500)->helperText('Trois lignes par défaut. La dernière ligne est mise en évidence.'),
                ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        app(EmailGlobalSettings::class)->update($this->form->getState());
        app(AdminAudit::class)->record('email.global_settings.updated', EmailGlobalSettings::KEY);
        Notification::make()->title('Configuration globale enregistrée')->success()->send();
    }
}
