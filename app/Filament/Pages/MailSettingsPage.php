<?php
namespace App\Filament\Pages;

use App\Services\{AdminAudit, MailSettings, SmtpConfigurationTestException, SmtpConfigurationTester};
use Filament\Actions\Action;
use Filament\Forms\Components\{Select, TextInput};
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class MailSettingsPage extends Page
{
    protected static ?string $slug = 'email-settings';
    protected static ?string $title = 'Configuration e-mail';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|\UnitEnum|null $navigationGroup = 'Emails';
    protected static ?string $navigationLabel = 'Configuration';
    protected static ?int $navigationSort = 1;
    protected string $view = 'filament.pages.settings-page';
    public ?array $data = [];

    public function mount(): void
    {
        $values = app(MailSettings::class)->values();
        $values['password'] = '';
        $this->form->fill($values + ['port' => 587, 'encryption' => 'tls', 'timeout' => 10, 'tries' => 3]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->schema([
                TextInput::make('host')->label('Hôte SMTP'),
                TextInput::make('port')->numeric()->minValue(1)->maxValue(65535),
                Select::make('encryption')->options(['tls' => 'TLS', 'ssl' => 'SSL', 'none' => 'Aucun']),
                TextInput::make('username')->label('Identifiant SMTP'),
                TextInput::make('password')->label('Mot de passe SMTP')->password()->revealable(false)->helperText(app(MailSettings::class)->hasPassword() ? 'Un mot de passe est déjà enregistré. Laissez vide pour le conserver.' : null),
                TextInput::make('from_address')->label('Adresse expéditeur')->email(),
                TextInput::make('from_name')->label('Nom expéditeur'),
                TextInput::make('reply_to')->label('Reply-To')->email(),
                TextInput::make('timeout')->numeric()->minValue(1)->maxValue(60),
                TextInput::make('tries')->numeric()->minValue(1)->maxValue(10),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        app(MailSettings::class)->update($this->form->getState());
        app(AdminAudit::class)->record('mail.settings.updated', 'mail_settings', ['password_changed' => filled($this->data['password'] ?? null)]);
        Notification::make()->title('Configuration enregistrée')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label('Envoyer un e-mail de test')
                ->form([TextInput::make('email')->email()->required()])
                ->action(function (array $data): void {
                    try {
                        app(SmtpConfigurationTester::class)->send($data['email']);
                        app(AdminAudit::class)->record('mail.settings.smtp_test_succeeded', 'mail_settings');
                        Notification::make()->title('E-mail de test envoyé avec succès')->success()->send();
                    } catch (SmtpConfigurationTestException $exception) {
                        app(AdminAudit::class)->record('mail.settings.smtp_test_failed', 'mail_settings');
                        Notification::make()
                            ->title('Échec de l’envoi SMTP')
                            ->body($exception->getMessage())
                            ->danger()
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
