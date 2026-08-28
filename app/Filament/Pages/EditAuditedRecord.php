<?php
namespace App\Filament\Pages;
use App\Services\AdminAudit;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
abstract class EditAuditedRecord extends EditRecord
{
    protected function handleRecordUpdate(Model $record, array $data): Model { $record = parent::handleRecordUpdate($record, $data); app(AdminAudit::class)->record(static::getResource()::auditAction('updated'), $record, $record->getChanges()); return $record; }
}
