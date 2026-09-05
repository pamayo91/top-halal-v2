<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Pages\CreateAuditedRecord;
use App\Filament\Resources\UserResource;

class CreateUser extends CreateAuditedRecord
{
    protected static string $resource = UserResource::class;
}
