<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class GeometryTest extends SapphireTest
{
    protected $usesDatabase = true;

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

    public function testDbRoundTrip()
    {
        $wkt = GIS::array_to_ewkt([10,53.5]);
        $address = TestGeometry::create();
        $address->GeoLocation = $wkt;
        $id = $address->write();

        $address1 = TestGeometry::get()->byID($id);
        $this->assertEquals($wkt, $address1->GeoLocation);

        $wkt = GIS::array_to_ewkt([174.5,-41.3]);
        $address->GeoLocation = $wkt;
        $address->write();
        $this->assertEquals($wkt, TestGeometry::get()->byID($id)->GeoLocation);

        $address1->GeoLocation = $wkt;
        $address1->write();
        $this->assertEquals($wkt, TestGeometry::get()->byID($id)->GeoLocation);
    }

    public function testWithinFilter()
    {
        $address = TestGeometry::create(['Name' => 'Lisbon']);
        $address->GeoLocation = GIS::array_to_ewkt([-9.1,38.7]);
        $address->write();

        $box = GIS::array_to_ewkt([
            'srid' => 4326,
            'type' => 'Polygon',
            'coordinates' => [[
                [-10,40],
                [ -8,40],
                [ -8,35],
                [-10,35],
                [-10,40],
        ]]]);
        $lisbon = TestGeometry::get()->filter('GeoLocation:WithinGeo', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestGeometry::get()->filter('GeoLocation:WithinGeo:not', $box)->count());

        $box = GIS::array_to_ewkt([
            'srid' => 4326,
            'type' => 'Polygon',
            'coordinates' => [[
                [10,40],
                [ 8,40],
                [ 8,35],
                [10,35],
                [10,40],
        ]]]);
        $lisbon = TestGeometry::get()->filter('GeoLocation:WithinGeo:not', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestGeometry::get()->filter('GeoLocation:WithinGeo', $box)->count());
    }

    public function testTypeFilter()
    {
        $address = TestGeometry::create();
        $address->GeoLocation = GIS::array_to_ewkt([10,53.5]);
        $address->write();

        $this->assertEquals(1, TestGeometry::get()->filter('GeoLocation:TypeGeo', 'Point')->count());
        $this->assertEquals(0, TestGeometry::get()->filter('GeoLocation:TypeGeo:not', 'Point')->count());
    }

    public function testDWithinFilter()
    {
        if (preg_match('/MariaDB/', DB::get_conn()->getVersion(), $matches)) {
            $this->markTestSkipped('ST_Distance_Sphere currently not implemented in MariaDB.');
        }

        $address = TestGeometry::create();
        $address->GeoLocation = GIS::array_to_ewkt([10,53.5]);
        $address->write();

        $this->assertEquals(0, TestGeometry::get()->filter('GeoLocation:DWithinGeo', [GIS::array_to_ewkt([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(1, TestGeometry::get()->filter('GeoLocation:DWithinGeo', [GIS::array_to_ewkt([-9.1,38.7]), 2200000])->count());
        $this->assertEquals(1, TestGeometry::get()->filter('GeoLocation:DWithinGeo:not', [GIS::array_to_ewkt([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(0, TestGeometry::get()->filter('GeoLocation:DWithinGeo:not', [GIS::array_to_ewkt([-9.1,38.7]), 2200000])->count());
    }

    public function testDistance()
    {
        if (preg_match('/MariaDB/', DB::get_conn()->getVersion(), $matches)) {
            $this->markTestSkipped('ST_Distance_Sphere currently not implemented in MariaDB.');
        }

        $geo1 = GIS::array_to_ewkt([10,53.5]);
        $geo2 = GIS::array_to_ewkt([-9.1,38.7]);
        $distance = GIS::distance($geo1, $geo2);

        $this->assertTrue($distance > 2190000 && $distance < 2200000);
    }
}
