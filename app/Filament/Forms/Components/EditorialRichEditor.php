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
            RichEditorTool::make('sourceCode')
                ->label('Code source')
                ->action(arguments: '{ html: $getEditor()?.getHTML() ?? "" }')
                ->icon('heroicon-o-code-bracket')
                ->iconAlias('forms:components.rich-editor.toolbar.source-code'),
        ]);
    }

    /** @return array<int, array<int, string>> */
    public function getDefaultToolbarButtons(): array
    {
        return [
            ...parent::getDefaultToolbarButtons(),
            ['sourceCode'],
        ];
    }

    /** @return array<Action> */
    public function getDefaultActions(): array
    {
        return [
            ...array_filter(parent::getDefaultActions(), static fn (Action $action): bool => $action->getName() !== 'link'),
            EditorialLinkAction::make(),
            EditorialHtmlSourceAction::make(),
        ];
    }
}
