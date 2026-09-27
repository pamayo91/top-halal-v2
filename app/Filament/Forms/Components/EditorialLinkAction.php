<?php

namespace App\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;

class EditorialLinkAction
{
    private const SEO_REL_TOKENS = ['nofollow', 'sponsored', 'ugc'];

    public static function make(): Action
    {
        return Action::make('link')
            ->label('Lien')
            ->modalHeading('Modifier le lien')
            ->modalWidth(Width::Large)
            ->fillForm(function (array $arguments): array {
                $tokens = self::tokens($arguments['rel'] ?? null);

                return [
                    'url' => $arguments['url'] ?? null,
                    'shouldOpenInNewTab' => ($arguments['shouldOpenInNewTab'] ?? false) === true,
                    'nofollow' => in_array('nofollow', $tokens, true),
                    'sponsored' => in_array('sponsored', $tokens, true),
                    'ugc' => in_array('ugc', $tokens, true),
                    // Keep unrelated tokens (for example noopener) untouched when
                    // an administrator only changes the URL or one SEO option.
                    'existingRel' => $arguments['rel'] ?? null,
                ];
            })
            ->schema([
                TextInput::make('url')->label('URL')->inputMode('url'),
                Checkbox::make('nofollow')->label('Nofollow'),
                Checkbox::make('sponsored')->label('Sponsored'),
                Checkbox::make('ugc')->label('UGC'),
                Checkbox::make('shouldOpenInNewTab')->label('Ouvrir dans un nouvel onglet'),
                Hidden::make('existingRel'),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $isSingleCharacterSelection = ($arguments['editorSelection']['head'] ?? null) === ($arguments['editorSelection']['anchor'] ?? null);

                if (blank($data['url'] ?? null)) {
                    $component->runCommands([
                        ...($isSingleCharacterSelection ? [EditorCommand::make('extendMarkRange', arguments: ['link'])] : []),
                        EditorCommand::make('unsetLink'),
                    ], editorSelection: $arguments['editorSelection']);

                    return;
                }

                $existingTokens = array_values(array_diff(self::tokens($data['existingRel'] ?? null), self::SEO_REL_TOKENS));
                $selectedTokens = array_keys(array_filter([
                    'nofollow' => (bool) ($data['nofollow'] ?? false),
                    'sponsored' => (bool) ($data['sponsored'] ?? false),
                    'ugc' => (bool) ($data['ugc'] ?? false),
                ]));
                $rel = array_values(array_unique([...$existingTokens, ...$selectedTokens]));

                $component->runCommands([
                    ...($isSingleCharacterSelection ? [EditorCommand::make('extendMarkRange', arguments: ['link'])] : []),
                    EditorCommand::make('setLink', arguments: [[
                        'href' => $data['url'],
                        'rel' => $rel === [] ? null : implode(' ', $rel),
                        'target' => ($data['shouldOpenInNewTab'] ?? false) ? '_blank' : null,
                    ]]),
                ], editorSelection: $arguments['editorSelection']);
            });
    }

    /** @return array<int, string> */
    private static function tokens(mixed $rel): array
    {
        return array_values(array_unique(array_filter(
            preg_split('/\s+/', strtolower(trim((string) $rel))) ?: [],
            static fn (string $token): bool => $token !== '',
        )));
    }
}
