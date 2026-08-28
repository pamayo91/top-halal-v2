<?php

namespace Tests\Unit;

use App\Services\TaxonomyValueClassifier;
use Tests\TestCase;

class TaxonomyValueClassifierTest extends TestCase
{
    public function test_it_rejects_manifest_sql_injection_payloads_without_rejecting_real_place_names(): void
    {
        $classifier = app(TaxonomyValueClassifier::class);

        $this->assertSame('malicious', $classifier->classify("a' OR 1=(SELECT PG_SLEEP(15))--"));
        $this->assertSame('malicious', $classifier->classify('DBMS_PIPE.RECEIVE_MESSAGE(CHR(99),15)'));
        $this->assertSame('valid', $classifier->classify('Saint-Étienne-du-Rouvray'));
        $this->assertSame('valid', $classifier->classify("L'Haÿ-les-Roses"));
    }
}
