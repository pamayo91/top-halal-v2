<?php

namespace App\Filament\Resources\EmailDeliveryLogResource\Pages;

use App\Filament\Resources\EmailDeliveryLogResource;
use App\Filament\Widgets\EmailDeliveryLogOverview;
use Filament\Resources\Pages\ListRecords;

class ListEmailDeliveryLogs extends ListRecords
{
    protected static string $resource = EmailDeliveryLogResource::class;
    protected static ?string $title = 'Historique des e-mails';

    protected function getHeaderWidgets(): array
    {
        return [EmailDeliveryLogOverview::class];
    }
}
