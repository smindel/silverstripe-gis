<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\FunctionalTest;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\Model\Raster;
use Smindel\GIS\GIS;

class RasterTest extends FunctionalTest
{
    use RenderingAssertion;

    public function testSrid()
    {
        $filename = __DIR__ . DIRECTORY_SEPARATOR . 'RasterTest.tif';
        $srid = Raster::create($filename)->getSrid();
        $this->assertEquals(4326, $srid);
    }

    public function testLocationInfo()
    {
        $filename = __DIR__ . DIRECTORY_SEPARATOR . 'RasterTest.tif';
        $bandValues = Raster::create($filename)->getLocationInfo([174.777, -41.2955]);
        $this->assertEquals([
            1 => '195',
            2 => '195',
            3 => '195',
            4 => '255',
        ], $bandValues);
    }

    public function testRasterTile()
    {
        $this->markTestSkipped();
        $response = $this->get('webmaptileservice/Smindel-GIS-Tests-TestRaster/18/258340/164145.png');
        $this->assertRenders($response->getBody(), 255, 1, [195, 195, 195]);
    }

    public function testSearchableFields()
    {
        $raster = new Raster();
        $this->assertEquals(['Band' => 'Band'], $raster->searchableFields());
    }
}
