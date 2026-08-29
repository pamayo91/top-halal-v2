<?php

namespace App\Filament\Resources;

use App\Models\Comment;
use App\Services\AdminAudit;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, EditAction};
use Filament\Forms\Components\{Textarea, TextInput};
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommentResource extends AdminResource
{
    protected static ?string $model = Comment::class;
    protected static ?string $modelLabel = 'commentaire';
    protected static ?string $pluralModelLabel = 'Commentaires';
    protected static ?string $recordTitleAttribute = 'author_name';
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static string|\UnitEnum|null $navigationGroup = 'Communauté';
    protected static ?int $navigationSort = 2;
    protected static ?string $navigationLabel = 'Commentaires';

    public static function canCreate(): bool { return false; }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->with(['article', 'page', 'parent']); }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('created_at')->disabled()->dehydrated(false), Textarea::make('content')->disabled()->columnSpanFull()]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('created_at')->label('Date du commentaire')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('content_title')->label('Contenu')->state(fn (Comment $c) => $c->article?->title ?? $c->page?->title ?? 'Contenu supprimé')->searchable(query: fn (Builder $q, string $search) => $q->whereHas('article', fn ($x) => $x->where('title', 'like', "%{$search}%"))->orWhereHas('page', fn ($x) => $x->where('title', 'like', "%{$search}%"))),
            TextColumn::make('author_name')->label('Auteur')->searchable()->description(fn (Comment $c) => str($c->content)->limit(80))->wrap(),
            TextColumn::make('status')->badge()->color(fn (string $state) => $state === 'approved' ? 'success' : ($state === 'pending' ? 'warning' : 'danger'))->sortable(),
        ])->filters([SelectFilter::make('status')->options(['pending' => 'En attente', 'approved' => 'Approuvé', 'rejected' => 'Refusé', 'spam' => 'Spam'])])
            ->recordActions([static::viewOnSiteAction(), Action::make('approve')->label('Approuver')->color('success')->visible(fn (Comment $c) => $c->status === 'pending')->action(fn (Comment $c) => static::moderate($c, 'approved')), Action::make('reject')->label('Refuser')->color('danger')->requiresConfirmation()->visible(fn (Comment $c) => $c->status === 'pending')->action(fn (Comment $c) => static::moderate($c, 'rejected')), Action::make('spam')->label('Marquer comme spam')->color('danger')->requiresConfirmation()->visible(fn (Comment $c) => $c->status === 'pending')->action(fn (Comment $c) => static::moderate($c, 'spam')), EditAction::make()->label('Voir')])
            ->toolbarActions([BulkActionGroup::make([BulkAction::make('approve')->label('Approuver la sélection')->color('success')->requiresConfirmation()->action(fn ($records) => $records->each(fn (Comment $c) => static::moderate($c, 'approved'))), BulkAction::make('reject')->label('Refuser la sélection')->color('danger')->requiresConfirmation()->action(fn ($records) => $records->each(fn (Comment $c) => static::moderate($c, 'rejected'))), BulkAction::make('spam')->label('Marquer la sélection comme spam')->color('danger')->requiresConfirmation()->action(fn ($records) => $records->each(fn (Comment $c) => static::moderate($c, 'spam')))])])
            ->defaultSort('created_at', 'desc')->emptyStateHeading('Aucun commentaire dans cette file');
    }

    public static function moderate(Comment $comment, string $status): void { $comment->update(['status' => $status, 'approved_at' => $status === 'approved' ? now() : null]); app(AdminAudit::class)->record('comment.'.$status, $comment); }
    public static function getPages(): array { return ['index' => CommentResource\Pages\ListComments::route('/')]; }
}
