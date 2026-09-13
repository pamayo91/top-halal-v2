<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Pages\EditAuditedRecord;
use App\Filament\Resources\UserResource;
use Illuminate\Validation\ValidationException;

class EditUser extends EditAuditedRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->is(auth()->user()) && ($data['status'] ?? null) !== 'active') {
            throw ValidationException::withMessages([
                'status' => 'Vous ne pouvez pas désactiver votre propre compte.',
            ]);
        }

        unset($data['role']);

        return $data;
    }
}
