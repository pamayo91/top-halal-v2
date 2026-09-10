<?php

namespace App\Filament\Widgets;

use App\Models\EmailDeliveryLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class EmailDeliveryLogOverview extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('En attente', EmailDeliveryLog::where('status', EmailDeliveryLog::STATUS_QUEUED)->count()),
            Stat::make('Envoyés', EmailDeliveryLog::where('status', EmailDeliveryLog::STATUS_SENT)->count()),
            Stat::make('Échecs', EmailDeliveryLog::where('status', EmailDeliveryLog::STATUS_FAILED)->count()),
            Stat::make('Total aujourd’hui', EmailDeliveryLog::whereDate('created_at', today())->count()),
        ];
    }
}
