<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ContentReportResource\Pages;
use App\Models\EditorialContentReport;
use Filament\Actions\Action;
use Filament\Forms\Components\{Placeholder, Select, Textarea, TextInput};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\{IconColumn, TextColumn};
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ContentReportResource extends AdminResource
{
    protected static ?string $model = EditorialContentReport::class;
    protected static ?string $recordTitleAttribute = 'content_title';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-flag';
    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';
    protected static ?string $navigationLabel = 'Signalements';
    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool { return false; }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('content_type')->label('Type')->disabled(),
            TextInput::make('content_title')->label('Contenu concerné')->disabled(),
            TextInput::make('content_url')->label('URL publique')->disabled()->columnSpanFull(),
            Textarea::make('message')->label('Signalement')->disabled()->rows(8)->columnSpanFull(),
            TextInput::make('reporter_name')->label('Signalant')->disabled(),
            TextInput::make('reporter_email')->label('E-mail')->disabled(),
            Placeholder::make('identity')->label('Identité')->content(fn (EditorialContentReport $record): string => $record->is_authenticated ? 'Compte connecté' : 'E-mail vérifié par lien'),
            Select::make('status')->label('Statut')->options(self::statuses())->required(),
            Placeholder::make('created_at')->label('Reçu le')->content(fn (EditorialContentReport $record): string => $record->created_at->format('d/m/Y H:i')),
            Placeholder::make('status_changed_at')->label('Dernier changement')->content(fn (EditorialContentReport $record): string => $record->status_changed_at?->format('d/m/Y H:i') ?? '—'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Reçu le')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('content_type')->label('Type')->formatStateUsing(fn (string $state) => ['restaurant' => 'Restaurant', 'article' => 'Article', 'page' => 'Page'][$state] ?? $state),
            TextColumn::make('content_title')->label('Contenu')->searchable()->limit(45),
            TextColumn::make('reporter_email')->label('Signalant')->searchable(),
            IconColumn::make('is_authenticated')->label('Connecté')->boolean(),
            TextColumn::make('status')->label('Statut')->badge()->formatStateUsing(fn (string $state) => self::statuses()[$state] ?? $state),
        ])->filters([SelectFilter::make('status')->options(self::statuses())])
            ->recordActions([
                Action::make('public')->label('Voir le contenu')->url(fn (EditorialContentReport $record) => $record->content_url)->openUrlInNewTab(),
                Action::make('in_progress')->label('Prendre en traitement')->color('warning')->visible(fn (EditorialContentReport $record) => $record->status === 'new')->action(fn (EditorialContentReport $record) => $record->update(['status' => 'in_progress', 'status_changed_at' => now()])),
                Action::make('resolved')->label('Marquer résolu')->color('success')->visible(fn (EditorialContentReport $record) => in_array($record->status, ['new', 'in_progress'], true))->action(fn (EditorialContentReport $record) => $record->update(['status' => 'resolved', 'status_changed_at' => now()])),
                Action::make('dismissed')->label('Classer sans suite')->color('gray')->visible(fn (EditorialContentReport $record) => in_array($record->status, ['new', 'in_progress'], true))->action(fn (EditorialContentReport $record) => $record->update(['status' => 'dismissed', 'status_changed_at' => now()])),
            ])->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array { return ['index' => Pages\ListContentReports::route('/'), 'edit' => Pages\EditContentReport::route('/{record}/edit')]; }
    private static function statuses(): array { return ['new' => 'Nouveau', 'in_progress' => 'En traitement', 'resolved' => 'Résolu', 'dismissed' => 'Classé sans suite']; }
}
