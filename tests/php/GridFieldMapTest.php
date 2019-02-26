<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\GridField\GridField;
use Smindel\GIS\GIS;
use Smindel\GIS\Forms\MapField;
use Smindel\GIS\Forms\GridFieldMap;

class GridFieldMapTest extends SapphireTest
{
    protected static $fixture_file = 'TestLocation.yml';

    public static function getExtraDataObjects()
    {
        return [TestLocation::class];
    }

    public function setUp()
    {
        // reset GIS environment
        Config::modify()->set(GIS::class, 'default_srid', 4326);
        Config::modify()->set(MapField::class, 'default_location', ['lon' => 174, 'lat' => -41]);
        parent::setUp();
    }

    public function testGridFieldMap()
    {
        $gridField = Gridfield::create('Locations', null, TestLocation::get());
        $map = GridFieldMap::create();

        $html = $map->getHTMLFragments($gridField)['before'];

        $this->assertRegExp('/\Wclass="grid-field-map"\W/', $html);
        $this->assertRegExp('/\Wdata-map-center="SRID=4326;POINT\(174 -41\)"\W/', $html);
        $this->assertRegExp('/\Wdata-list="\{&quot;/', $html);
    }
}
