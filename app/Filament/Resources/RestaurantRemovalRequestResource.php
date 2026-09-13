<?php
namespace App\Filament\Resources;
use App\Filament\Resources\RestaurantRemovalRequestResource\Pages;
use App\Models\RestaurantRemovalRequest;
use App\Services\RestaurantRemovalModeration;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
class RestaurantRemovalRequestResource extends AdminResource { protected static ?string $model=RestaurantRemovalRequest::class; protected static string|null|\BackedEnum $navigationIcon='heroicon-o-trash'; protected static string|\UnitEnum|null $navigationGroup='Communauté'; protected static ?string $navigationLabel='Demandes de suppression'; public static function table(Table $table):Table{return $table->columns([TextColumn::make('restaurant.name')->label('Restaurant')->searchable(),TextColumn::make('user.name')->label('Demandeur')->searchable(),TextColumn::make('reason')->label('Motif'),TextColumn::make('comment')->limit(80),TextColumn::make('admin_note')->label('Motif du refus')->limit(80)->toggleable(isToggledHiddenByDefault:true),TextColumn::make('status')->badge(),TextColumn::make('submitted_at')->label('Demandée le')->dateTime('d/m/Y H:i'),TextColumn::make('reviewed_at')->label('Traitée le')->dateTime('d/m/Y H:i')->toggleable(isToggledHiddenByDefault:true)])->filters([SelectFilter::make('status')->options(['pending'=>'En attente','approved'=>'Acceptée','rejected'=>'Refusée'])->default('pending')])->recordActions([Action::make('approve')->label('Accepter')->color('success')->requiresConfirmation()->visible(fn($r)=>$r->status==='pending')->action(fn($r)=>app(RestaurantRemovalModeration::class)->approve($r)),Action::make('reject')->label('Refuser')->color('danger')->form([Textarea::make('admin_note')->label('Motif du refus')->maxLength(2000)])->requiresConfirmation()->visible(fn($r)=>$r->status==='pending')->action(fn($r,array $data)=>app(RestaurantRemovalModeration::class)->reject($r,$data['admin_note'] ?? null))])->defaultSort('submitted_at','desc');} public static function getPages():array{return ['index'=>Pages\ListRestaurantRemovalRequests::route('/')];} }
