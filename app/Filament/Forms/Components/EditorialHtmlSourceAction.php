<?php

namespace App\Filament\Forms\Components;

use App\Services\ContentSanitizer;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Textarea;
use Filament\Support\Enums\Width;

final class EditorialHtmlSourceAction
{
    public static function make(): Action
    {
        return Action::make('sourceCode')
            ->label('Code source')
            ->modalHeading('Modifier le code HTML')
            ->modalWidth(Width::SevenExtraLarge)
            ->modalSubmitActionLabel('Appliquer')
            ->modalCancelActionLabel('Annuler')
            ->fillForm(static fn (array $arguments): array => [
                'html' => (string) ($arguments['html'] ?? ''),
            ])
            ->schema([
                Textarea::make('html')
                    ->label('HTML')
                    ->rows(24)
                    ->extraInputAttributes(['class' => 'font-mono']),
            ])
            ->action(function (array $data, RichEditor $component): void {
                $html = app(ContentSanitizer::class)->sanitize((string) ($data['html'] ?? ''))['html'];

                $component->runCommands([
                    EditorCommand::make('setContent', arguments: [$html, true]),
                ]);
            });
    }
}
