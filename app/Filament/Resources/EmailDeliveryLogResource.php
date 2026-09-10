<?php

namespace App\Filament\Resources;

use App\Exceptions\EmailDeliveryUnavailableException;
use App\Models\EmailDeliveryLog;
use App\Services\TransactionalMailService;
use Filament\Actions\{Action, BulkAction, BulkActionGroup};
use Filament\Forms\Components\{DatePicker, TextInput};
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\{Filter, SelectFilter};
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class EmailDeliveryLogResource extends AdminResource
{
    protected static ?string $model = EmailDeliveryLog::class;
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-envelope';
    protected static string|\UnitEnum|null $navigationGroup = 'Emails';
    protected static ?string $navigationLabel = 'Historique';
    protected static ?string $pluralModelLabel = 'Historique des e-mails';
    protected static ?string $modelLabel = 'e-mail';
    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool { return false; }
    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool { return false; }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Créé le')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('template_key')->label('Type')->state(fn (EmailDeliveryLog $record): string => $record->templateLabel())->searchable(),
                TextColumn::make('recipient')->label('Destinataire')->searchable(),
                TextColumn::make('subject')->label('Objet')->searchable()->limit(45)->toggleable(),
                TextColumn::make('status')->label('Statut')->badge()->formatStateUsing(fn (string $state): string => EmailDeliveryLog::statusLabels()[$state] ?? $state)->color(fn (string $state): string => match ($state) {
                    EmailDeliveryLog::STATUS_SENT => 'success', EmailDeliveryLog::STATUS_FAILED => 'danger', EmailDeliveryLog::STATUS_QUEUED, EmailDeliveryLog::STATUS_PROCESSING => 'warning', default => 'gray',
                })->sortable(),
                TextColumn::make('attempts')->label('Tentatives')->numeric()->sortable(),
                TextColumn::make('last_attempt_at')->label('Dernier essai')->dateTime('d/m/Y H:i')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('status')->label('Statut')->options(EmailDeliveryLog::statusLabels()),
                SelectFilter::make('template_key')->label('Type')->options(EmailDeliveryLog::templateLabels()),
                Filter::make('recipient')->schema([TextInput::make('recipient')->label('Destinataire')->email()])->query(fn (Builder $query, array $data): Builder => $query->when(filled($data['recipient'] ?? null), fn (Builder $query) => $query->where('recipient', 'like', '%'.$data['recipient'].'%'))),
                Filter::make('period')->label('Période')->schema([DatePicker::make('from')->label('Du'), DatePicker::make('until')->label('Au')])->query(fn (Builder $query, array $data): Builder => $query
                    ->when(filled($data['from'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '>=', $data['from']))
                    ->when(filled($data['until'] ?? null), fn (Builder $query) => $query->whereDate('created_at', '<=', $data['until']))),
            ])
            ->recordActions([
                Action::make('details')->label('Voir les détails')->icon('heroicon-o-eye')->modalHeading('Détail de l’e-mail')->modalContent(fn (EmailDeliveryLog $record) => view('filament.email-delivery-log-details', compact('record')))->modalSubmitAction(false),
                Action::make('send_now')->label('Envoyer maintenant')->icon('heroicon-o-play')->color('warning')->visible(fn (EmailDeliveryLog $record): bool => $record->status === EmailDeliveryLog::STATUS_QUEUED)->requiresConfirmation()->modalDescription('Le job sera rendu disponible immédiatement. Le Cron l’enverra au prochain passage.')->action(fn (EmailDeliveryLog $record) => static::perform($record, 'sendNow', 'L’e-mail sera traité au prochain passage du Cron.')),
                Action::make('cancel')->label('Annuler l’envoi')->icon('heroicon-o-x-circle')->color('danger')->visible(fn (EmailDeliveryLog $record): bool => $record->status === EmailDeliveryLog::STATUS_QUEUED)->requiresConfirmation()->action(fn (EmailDeliveryLog $record) => static::perform($record, 'cancel', 'L’envoi a été annulé.')),
                Action::make('retry')->label('Réessayer')->icon('heroicon-o-arrow-path')->color('warning')->visible(fn (EmailDeliveryLog $record): bool => $record->status === EmailDeliveryLog::STATUS_FAILED)->requiresConfirmation()->action(fn (EmailDeliveryLog $record) => static::perform($record, 'retry', 'L’e-mail a été remis dans la queue.')),
            ])
            ->toolbarActions([BulkActionGroup::make([
                BulkAction::make('retry_selected')->label('Réessayer les échecs sélectionnés')->requiresConfirmation()->action(fn ($records) => $records->where('status', EmailDeliveryLog::STATUS_FAILED)->each(fn (EmailDeliveryLog $record) => static::performSilently($record, 'retry'))),
                BulkAction::make('cancel_selected')->label('Annuler les envois sélectionnés')->color('danger')->requiresConfirmation()->action(fn ($records) => $records->where('status', EmailDeliveryLog::STATUS_QUEUED)->each(fn (EmailDeliveryLog $record) => static::performSilently($record, 'cancel'))),
            ])])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading('Aucun e-mail dans l’historique');
    }

    private static function perform(EmailDeliveryLog $record, string $method, string $success): void
    {
        try {
            app(TransactionalMailService::class)->{$method}($record);
            Notification::make()->success()->title($success)->send();
        } catch (EmailDeliveryUnavailableException $exception) {
            Notification::make()->danger()->title('Action impossible')->body($exception->getMessage())->send();
        }
    }

    private static function performSilently(EmailDeliveryLog $record, string $method): void
    {
        try { app(TransactionalMailService::class)->{$method}($record); } catch (EmailDeliveryUnavailableException) { }
    }

    public static function getPages(): array
    {
        return ['index' => EmailDeliveryLogResource\Pages\ListEmailDeliveryLogs::route('/')];
    }
}
