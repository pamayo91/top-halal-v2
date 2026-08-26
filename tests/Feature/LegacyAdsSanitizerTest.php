<?php

namespace Tests\Feature;

use App\Services\ContentSanitizer;
use Tests\TestCase;

class LegacyAdsSanitizerTest extends TestCase
{
    public function test_it_removes_direct_and_encoded_legacy_adsense_payloads(): void
    {
        $ad = '<p><script async src="//pagead2.googlesyndication.com/pagead/js/adsbygoogle.js"></script><ins class="adsbygoogle" data-ad-client="ca-pub-2607759447209855"></ins></p>';
        $encoded = base64_encode(urlencode($ad));

        $result = app(ContentSanitizer::class)->sanitize($ad.$encoded);

        $this->assertStringNotContainsString('adsbygoogle', $result['html']);
        $this->assertStringNotContainsString('ca-pub-', $result['html']);
        $this->assertContains('legacy_adsense', $result['removed']);
        $this->assertContains('encoded_legacy_adsense', $result['removed']);
    }
}
