<?php

namespace Tests\Feature;

use Tests\TestCase;

class LivewirePayloadConfigurationTest extends TestCase
{
    public function test_editorial_content_can_use_the_supported_livewire_nesting_depth(): void
    {
        $this->assertSame(20, config('livewire.payload.max_nesting_depth'));
    }
}
