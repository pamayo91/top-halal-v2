<?php

namespace Tests\Feature;

use App\Models\RedirectRule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_exact_rules_win_over_regex_and_record_a_hit(): void
    {
        RedirectRule::create(['source_path' => '^old/.*$', 'match_type' => 'regex', 'destination' => '/', 'priority' => 1]);
        $exact = RedirectRule::create(['source_path' => '/old/page', 'match_type' => 'exact', 'destination' => '/new-page', 'priority' => 999]);
        $this->get('/old/page')->assertRedirect('/new-page')->assertStatus(301);
        $this->assertSame(1, $exact->fresh()->hit_count);
    }

    public function test_regex_capture_and_query_policy_are_applied(): void
    {
        RedirectRule::create(['source_path' => '^old/(.*)$', 'match_type' => 'regex', 'destination' => '/new/$1', 'priority' => 1, 'preserve_query' => false]);
        $this->get('/old/example?utm_source=test')->assertRedirect('/new/example');
    }

    public function test_query_condition_can_build_destination_from_capture(): void
    {
        RedirectRule::create(['source_path' => '^.*$', 'match_type' => 'regex', 'query_pattern' => '^location=([a-z-]+)(&.*)?$', 'destination' => '/restos/%1', 'priority' => 1]);
        $this->get('/ancienne-page?location=paris&foo=bar')->assertRedirect('/restos/paris');
    }

    public function test_trailing_slash_is_canonicalised(): void
    {
        $this->get('/unknown/')->assertRedirect('/unknown');
    }

    public function test_410_is_supported(): void
    {
        RedirectRule::create(['source_path' => '/technical-gone', 'match_type' => 'exact', 'destination' => '/', 'status_code' => 410, 'priority' => 1]);
        $this->get('/technical-gone')->assertStatus(410);
    }
}
