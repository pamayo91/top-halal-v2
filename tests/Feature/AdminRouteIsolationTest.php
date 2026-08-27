<?php
namespace Tests\Feature;
use Tests\TestCase;
class AdminRouteIsolationTest extends TestCase
{
    public function test_admin_prefix_is_never_resolved_as_a_legacy_redirect(): void
    {
        $this->get('/admin')->assertRedirect('/login');
    }
}
