<?php
namespace Tests\Feature;
use Tests\TestCase;
class AdminRouteIsolationTest extends TestCase
{
    public function test_back_office_prefix_is_never_resolved_as_a_legacy_redirect(): void
    {
        $this->get('/bo')->assertRedirect('/login');
    }

    public function test_login_route_is_never_resolved_as_a_legacy_redirect(): void
    {
        $this->get('/login')->assertOk();
    }
}
