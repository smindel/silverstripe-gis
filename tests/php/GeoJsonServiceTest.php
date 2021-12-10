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
        $this->expectOutputString('{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[175,5],[185,5],[185,-5],[175,-5],[175,5]]]},"properties":{"Name":"Eastern Dateline"}},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-20,30],[60,30],[60,70],[-20,70],[-20,30]]]},"properties":{"Name":"Europe"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[10,53.5]},"properties":{"Name":"Hamburg"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[-9.1,38.7]},"properties":{"Name":"Lisbon"}},{"type":"Feature","geometry":{"type":"Point","coordinates":[174.78,-41.29]},"properties":{"Name":"Wellington"}},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[-182.5,2.5],[-177.5,2.5],[-177.5,-2.5],[-182.5,-2.5],[-182.5,2.5]]]},"properties":{"Name":"Western Dateline"}}]}');

        $this->get('geojsonservice/Smindel-GIS-Tests-TestLocation.GeoJson');
    }


    public function testServiceFilter()
    {
        $this->expectOutputString('{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[10,53.5]},"properties":{"Name":"Hamburg"}}]}');

        $this->get('geojsonservice/Smindel-GIS-Tests-TestLocation.GeoJson?Name=Hamburg');
    }
}
