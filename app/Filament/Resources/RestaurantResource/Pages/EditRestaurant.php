<?php
namespace App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Pages\EditAuditedRecord;
use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\AdminAudit;
use App\Services\Location\RestaurantLocationService;
use App\Services\RestaurantMediaOrderer;
use App\Services\RestaurantMediaManager;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
class EditRestaurant extends EditAuditedRecord {
    protected static string $resource = RestaurantResource::class;
    protected function handleRecordUpdate(Model $record, array $data): Model {
        if ($record instanceof Restaurant) return app(RestaurantLocationService::class)->update($record, $data);
        return parent::handleRecordUpdate($record, $data);
    }
    protected function getHeaderActions(): array { return [
        RestaurantResource::viewOnSiteAction(),
        RestaurantResource::previewAction(),
        Action::make('add_photos')
            ->label('Ajouter des photos')
            ->icon('heroicon-o-photo')
            ->form([
                FileUpload::make('photos')
                    ->label('Photos')
                    ->multiple()
                    ->required()
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->helperText('JPEG, PNG ou WebP, 10 Mo maximum par image. Les photos sont ajoutées à la fin de la galerie.'),
            ])
            ->action(function (array $data): void {
                /** @var Restaurant $restaurant */
                $restaurant = $this->getRecord();
                $result = app(RestaurantMediaManager::class)->attachUploads($restaurant, $data['photos']);

                app(AdminAudit::class)->record('restaurant.media_added', $restaurant, $result);
            }),
        RestaurantResource::trashAction(),
    ]; }

    public function moveRestaurantMedia(int $mediaId, string $direction): void
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->getRecord();
        $move = app(RestaurantMediaOrderer::class)->move($restaurant, $mediaId, $direction);

        if ($move !== null) {
            app(AdminAudit::class)->record('restaurant.media_reordered', $restaurant, [
                'media_id' => $mediaId,
                'from_position' => $move['from'] + 1,
                'to_position' => $move['to'] + 1,
            ]);
        }
    }

    public function detachRestaurantMedia(int $mediaId): void
    {
        /** @var Restaurant $restaurant */
        $restaurant = $this->getRecord();

        if (app(RestaurantMediaManager::class)->detach($restaurant, $mediaId)) {
            app(AdminAudit::class)->record('restaurant.media_detached', $restaurant, ['media_id' => $mediaId]);
        }
    }
}
