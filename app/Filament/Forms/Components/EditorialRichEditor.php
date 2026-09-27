<?php

namespace App\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\RichEditorTool;
use Filament\Support\Icons\Heroicon;

class EditorialRichEditor extends RichEditor
{
    protected function setUp(): void
    {
        parent::setUp();

        // This replaces only Filament's link trigger; all other editor tools,
        // extensions and stored HTML behaviour remain native.
        $this->tools([
            RichEditorTool::make('link')
                ->label('Lien')
                ->action(arguments: '{ url: $getEditor().getAttributes(\'link\')?.href, shouldOpenInNewTab: $getEditor().getAttributes(\'link\')?.target === \'_blank\', rel: $getEditor().getAttributes(\'link\')?.rel }')
                ->toggle()
                ->icon(Heroicon::Link)
                ->iconAlias('forms:components.rich-editor.toolbar.link'),
        ]);
    }

    /** @return array<Action> */
    public function getDefaultActions(): array
    {
        return [
            ...array_filter(parent::getDefaultActions(), static fn (Action $action): bool => $action->getName() !== 'link'),
            EditorialLinkAction::make(),
        ];
    }
}
