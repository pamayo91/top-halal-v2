<?php
namespace App\Filament\Resources;
use App\Models\RestaurantReview;
use App\Services\AdminAudit;
use Filament\Actions\{Action, BulkAction, BulkActionGroup, EditAction};
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
class RestaurantReviewResource extends AdminResource
{
 protected static ?string $model=RestaurantReview::class; protected static ?string $modelLabel='avis'; protected static ?string $pluralModelLabel='Avis'; protected static ?string $recordTitleAttribute='author_name'; protected static string|null|\BackedEnum $navigationIcon='heroicon-o-star'; protected static string|\UnitEnum|null $navigationGroup='Communauté'; protected static ?int $navigationSort=1; protected static ?string $navigationLabel='Avis';
 public static function canCreate():bool{return false;} public static function getEloquentQuery():Builder{return parent::getEloquentQuery()->with('restaurant');}
 public static function form(Schema $schema):Schema{return $schema->components([Textarea::make('content')->disabled()->columnSpanFull(),Textarea::make('moderation_note')->label('Note interne')->dehydrated(false)->columnSpanFull()]);}
 public static function table(Table $table):Table{return $table->columns([TextColumn::make('restaurant.name')->label('Restaurant')->searchable()->sortable(),TextColumn::make('author_name')->label('Auteur')->searchable()->description(fn(RestaurantReview $r)=>str($r->content)->limit(110)),TextColumn::make('rating')->label('Note')->suffix('/5')->sortable(),TextColumn::make('status')->badge()->color(fn(string $state)=>$state==='approved'?'success':($state==='pending'?'warning':'danger'))->sortable(),TextColumn::make('created_at')->label('Avis le')->dateTime('d/m/Y H:i')->sortable()->toggleable(),TextColumn::make('approved_at')->label('Modéré le')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable()])->filters([SelectFilter::make('status')->default('pending')->options(['pending'=>'En attente','approved'=>'Approuvé','rejected'=>'Refusé','spam'=>'Spam'])])->recordActions([Action::make('approve')->label('Approuver')->color('success')->visible(fn(RestaurantReview $r)=>$r->status==='pending')->action(fn(RestaurantReview $r)=>static::moderate($r,'approved')),Action::make('reject')->label('Refuser')->color('danger')->requiresConfirmation()->visible(fn(RestaurantReview $r)=>$r->status==='pending')->action(fn(RestaurantReview $r)=>static::moderate($r,'rejected')),EditAction::make()->label('Voir')])->toolbarActions([BulkActionGroup::make([BulkAction::make('approve')->label('Approuver la sélection')->requiresConfirmation()->action(fn($records)=>$records->each(fn(RestaurantReview $r)=>static::moderate($r,'approved'))),BulkAction::make('reject')->label('Refuser la sélection')->requiresConfirmation()->action(fn($records)=>$records->each(fn(RestaurantReview $r)=>static::moderate($r,'rejected')))])])->defaultSort('created_at','desc')->emptyStateHeading('Aucun avis dans cette file');}
 public static function moderate(RestaurantReview $review,string $status):void{$review->update(['status'=>$status,'approved_at'=>$status==='approved'?now():null]);app(AdminAudit::class)->record('review.'.$status,$review);}
 public static function getPages():array{return ['index'=>RestaurantReviewResource\Pages\ListRestaurantReviews::route('/')];}
}
