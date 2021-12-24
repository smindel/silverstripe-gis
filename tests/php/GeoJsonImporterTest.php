<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;
use Smindel\GIS\Service\GeoJsonImporter;

// @phpcs:disable Generic.Files.LineLength.TooLong
class GeoJsonImporterTest extends SapphireTest
{
    public static function getExtraDataObjects()
    {
        return [TestLocation::class];
    }

    public function setUp()
    {
        // reset GIS environment
        Config::modify()->set(GIS::class, 'default_srid', 4326);
        parent::setUp();
    }

    public function testImport()
    {
        $json = json_encode([
            'type' => 'FeatureCollection',
            'features' => [
                [
                    'type' => 'Feature',
                    'geometry' => [
                        'type' => 'Point',
                        'coordinates' => [91,54]
                    ],
                    'properties' => [
                        'Name' => 'Abakan'
                    ]
                ]
            ]
        ]);

        GeoJsonImporter::import(TestLocation::class, $json);

        $this->assertEquals(['Abakan' => 'SRID=4326;POINT(91 54)'], TestLocation::get()->map('Name', 'GeoLocation')->toArray());
    }
}
