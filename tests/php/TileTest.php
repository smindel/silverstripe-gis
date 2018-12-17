<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeography;

class TileTest extends SapphireTest
{
    protected static $fixture_file = 'TestGeography.yml';

    public function setUp()
    {
        Config::modify()->set(DBGeography::class, 'default_projection', 4326);
        Config::modify()->set(DBGeography::class, 'projections', [
            2193 => '+proj=tmerc +lat_0=0 +lon_0=173 +k=0.9996 +x_0=1600000 +y_0=10000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
        ]);
        parent::setUp();
    }

    public static function getExtraDataObjects()
    {
        return [TestGeography::class];
    }

    public function testFunction()
    {
        $point = $this->objFromFixture(TestGeography::class, 'hamburg');
        var_dump($point);
        $this->assertEquals(1,2);
    }
}
