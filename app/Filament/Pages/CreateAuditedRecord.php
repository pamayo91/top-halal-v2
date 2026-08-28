<?php
namespace App\Filament\Pages;
use App\Services\AdminAudit;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
abstract class CreateAuditedRecord extends CreateRecord
{
    protected function handleRecordCreation(array $data): Model { $record = parent::handleRecordCreation($data); app(AdminAudit::class)->record(static::getResource()::auditAction('created'), $record); return $record; }
}
