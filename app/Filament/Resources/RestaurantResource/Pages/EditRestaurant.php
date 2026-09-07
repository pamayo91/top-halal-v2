<?php
namespace App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Pages\EditAuditedRecord;
use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\AdminAudit;
use App\Services\Location\RestaurantLocationService;
use App\Services\RestaurantMediaOrderer;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
class EditRestaurant extends EditAuditedRecord {
    protected static string $resource = RestaurantResource::class;
    protected function handleRecordUpdate(Model $record, array $data): Model {
        if ($record instanceof Restaurant) return app(RestaurantLocationService::class)->update($record, $data);
        return parent::handleRecordUpdate($record, $data);
    }
    protected function getHeaderActions(): array { return [RestaurantResource::viewOnSiteAction(), RestaurantResource::previewAction(), RestaurantResource::trashAction()]; }

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
}
