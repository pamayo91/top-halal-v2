<?php

namespace Tests\Feature;

use Tests\TestCase;

class FilamentFrenchTranslationsTest extends TestCase
{
    public function test_missing_filament_french_labels_are_overridden_by_the_application(): void
    {
        app()->setLocale('fr');

        $this->assertSame('Aller au contenu', __('filament-panels::layout.skip_to_content.label'));
        $this->assertSame('Navigation latérale', __('filament-panels::layout.navigation.label'));
        $this->assertSame('Barre supérieure', __('filament-panels::layout.topbar.label'));
        $this->assertSame('Chargement…', __('filament::components.loading-section.label'));
    }
}
