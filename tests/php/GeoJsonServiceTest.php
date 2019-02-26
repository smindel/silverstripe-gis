<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;

class GeoJsonServiceTest extends FunctionalTest
{
    protected static $fixture_file = 'TestLocation.yml';

    public function setUp()
    {
        // reset GIS environment
        Config::modify()->set(GIS::class, 'default_srid', 4326);
        parent::setUp();
    }

    public static function getExtraDataObjects()
    {
        return [TestLocation::class];
    }

    public function testService()
    {
        $response = $this->get('geojsonservice/Smindel-GIS-Tests-TestLocation.GeoJson');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/geo+json', $response->getHeaders()['content-type']);

        $array = json_decode($response->getBody(), true);

        $this->assertEquals(6, count($array['features']));

        $feature = $array['features'][0];
        $this->assertEquals(['Name'], array_keys($feature['properties']));

        $record = TestLocation::get()->filter('Name', $feature['properties']['Name'])->first();
        $this->assertEquals($feature['geometry']['coordinates'], GIS::ewkt_to_array($record->GeoLocation)['coordinates']);
    }

    public function testServiceFilter()
    {
        $response = $this->get('geojsonservice/Smindel-GIS-Tests-TestLocation.GeoJson?Name=Hamburg');

        $array = json_decode($response->getBody(), true);

        $this->assertEquals(1, count($array['features']));

        $feature = $array['features'][0];
        $this->assertEquals(['Name' => 'Hamburg'], $feature['properties']);
    }
}
