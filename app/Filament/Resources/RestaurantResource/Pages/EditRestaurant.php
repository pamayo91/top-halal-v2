<?php
namespace App\Filament\Resources\RestaurantResource\Pages;
use App\Filament\Pages\EditAuditedRecord;
use App\Filament\Resources\RestaurantResource;
use App\Models\Restaurant;
use App\Services\Location\RestaurantLocationService;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
class EditRestaurant extends EditAuditedRecord {
    protected static string $resource = RestaurantResource::class;
    protected function handleRecordUpdate(Model $record, array $data): Model {
        if ($record instanceof Restaurant) return app(RestaurantLocationService::class)->update($record, $data);
        return parent::handleRecordUpdate($record, $data);
    }
    protected function getHeaderActions(): array { return [RestaurantResource::viewOnSiteAction(), RestaurantResource::previewAction(), RestaurantResource::trashAction()]; }
}
