<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;
use Smindel\GIS\Service\Tile;

class TileTest extends SapphireTest
{
    protected static $fixture_file = 'TestGeometry.yml';

    public function setUp()
    {
        Config::modify()->set(GIS::class, 'default_srid', 4326);
        Config::modify()->set(GIS::class, 'projections', [
            2193 => '+proj=tmerc +lat_0=0 +lon_0=173 +k=0.9996 +x_0=1600000 +y_0=10000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
        ]);
        parent::setUp();
    }

    public static function getExtraDataObjects()
    {
        return [TestGeometry::class];
    }

    public function testWrapAtDateline()
    {
        $z = 6;
        $x = 63;
        $y = 31;
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 255, 255);
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 255, 255);

        $x = 64;
        $y = 32;
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 0, 0);
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 0, 0);

        $x = 0;
        $y = 32;
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 0, 0);
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 0, 0);

        $x = -1;
        $y = 31;
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 255, 255);
        $tile = Tile::create($z, $x, $y, true);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 255, 255);
    }

    public function testDontWrapAtDateline()
    {
        $z = 6;
        $x = -1;
        $y = 31;
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertNotRenders($list, $tile, 255, 255);
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 255, 255);

        $x = 0;
        $y = 32;
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertNotRenders($list, $tile, 0, 0);
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($list, $tile, 0, 0);

        $x = 63;
        $y = 31;
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 255, 255);
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertNotRenders($list, $tile, 255, 255);

        $x = 64;
        $y = 32;
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($list, $tile, 0, 0);
        $tile = Tile::create($z, $x, $y, false);
        $list = TestGeometry::get()->filter('Name', 'Western Dateline');
        $this->assertNotRenders($list, $tile, 0, 0);
    }

    public function assertRenders($list, $tile, $x, $y, $msg = null)
    {
        $image = imagecreatefromstring($tile->render($list));
        // $tile->debug();
        // file_put_contents('public/assets/tile.png', $tile->render($list));
        $this->assertNotEquals(0, imagecolorat($image, $x, $y), $msg);
    }

    public function assertNotRenders($list, $tile, $x, $y, $msg = null)
    {
        $image = imagecreatefromstring($tile->render($list));
        // $tile->debug();
        // file_put_contents('public/assets/tile.png', $tile->render($list));
        $this->assertEquals(0, imagecolorat($image, $x, $y), $msg);
    }
}
