<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;
use Smindel\GIS\Service\Tile;

// @phpcs:disable Generic.Files.LineLength.TooLong
class TileTest extends SapphireTest
{
    use RenderingAssertion;

    protected static $fixture_file = 'TestLocation.yml';

    protected $defaultStyle = [
        'gd' => [
            'backgroundcolor' => [0, 0, 0, 127],
            'strokecolor' => [60, 60, 210, 0],
            'fillcolor' => [60, 60, 210, 80],
            'setthickness' => [2],
            'pointradius' => 5,
        ],
        'imagick' => [
            'StrokeOpacity' => 1,
            'StrokeWidth' => 2,
            'StrokeColor' => 'rgb(60,60,210)',
            'FillColor' => 'rgba(60,60,210,.25)',
            'PointRadius' => 5,
        ],
    ];

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
        return [TestLocation::class];
    }

    public function testWrapAtDateline()
    {
        $z = 6;
        $x = 63;
        $y = 31;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 255, 255);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 255, 255);

        $x = 64;
        $y = 32;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 0, 0);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 0, 0);

        $x = 0;
        $y = 32;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 0, 0);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 0, 0);

        $x = -1;
        $y = 31;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 255, 255);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, true);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 255, 255);
    }

    public function testDontWrapAtDateline()
    {
        $z = 6;
        $x = -1;
        $y = 31;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertNotRenders($tile->render($list), 255, 255);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 255, 255);

        $x = 0;
        $y = 32;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertNotRenders($tile->render($list), 0, 0);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertRenders($tile->render($list), 0, 0);

        $x = 63;
        $y = 31;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 255, 255);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertNotRenders($tile->render($list), 255, 255);

        $x = 64;
        $y = 32;
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Eastern Dateline');
        $this->assertRenders($tile->render($list), 0, 0);
        $tile = Tile::create($z, $x, $y, $this->defaultStyle, false);
        $list = TestLocation::get()->filter('Name', 'Western Dateline');
        $this->assertNotRenders($tile->render($list), 0, 0);
    }
}
