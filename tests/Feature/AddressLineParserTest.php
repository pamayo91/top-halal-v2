<?php

namespace Tests\Feature;

use App\Services\Location\AddressLineParser;
use Tests\TestCase;

class AddressLineParserTest extends TestCase
{
    public function test_it_strictly_extracts_a_street_from_a_matching_postcode_and_city(): void
    {
        $parser = app(AddressLineParser::class);

        $this->assertSame('2 Rue Paquet', $parser->repair('2 Rue Paquet 78260 Achères', '2 Rue Paquet 78260 Achères', '78260', 'Achères'));
        $this->assertSame('54 Boulevard de La libération', $parser->repair('54 Boulevard de La libération 13001 Marseille', '54 Boulevard de La libération 13001 Marseille', '13001', 'Marseille'));
    }

    public function test_it_refuses_postcode_or_city_conflicts_and_leaves_clean_lines_alone(): void
    {
        $parser = app(AddressLineParser::class);

        $this->assertNull($parser->repair('1 Place de la Mairie 13100 Aix-en-Provence', '1 Place de la Mairie 13100 Aix-en-Provence', '13290', 'Aix-en-Provence'));
        $this->assertNull($parser->repair('1 Rue des Fleurs 93300 Auvervilliers', '1 Rue des Fleurs 93300 Auvervilliers', '93300', 'Aubervilliers'));
        $this->assertNull($parser->repair('54 Boulevard de La libération', '54 Boulevard de La libération', '13001', 'Marseille'));
    }
}
