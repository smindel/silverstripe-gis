<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeography;

class ORMTest extends SapphireTest
{
    protected $usesDatabase = true;

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

    public function testPointWktFromArray()
    {
        $wkt = DBGeography::from_array([174.5,-41.3]);
        $this->assertEquals($wkt, 'SRID=4326;POINT(174.5 -41.3)');
    }

    public function testLineWktFromArray()
    {
        $wkt = DBGeography::from_array([[174.5,-41.3], [175.5,-42.3]]);
        $this->assertEquals($wkt, 'SRID=4326;LINESTRING(174.5 -41.3,175.5 -42.3)');
    }

    public function testPolygonWktFromArray()
    {
        $wkt = DBGeography::from_array([[
            [-10,40],
            [ -8,40],
            [ -8,35],
            [-10,35],
            [-10,40],
        ]]);
        $this->assertEquals($wkt, 'SRID=4326;POLYGON((-10 40,-8 40,-8 35,-10 35,-10 40))');
    }

    public function testPointArrayFromWkt()
    {
        $array = DBGeography::to_array('SRID=4326;POINT(174.5 -41.3)');
        $this->assertEquals($array['coordinates'], [174.5,-41.3]);
    }

    public function testLineArrayFromWkt()
    {
        $array = DBGeography::to_array('SRID=4326;LINESTRING(174.5 -41.3,175.5 -42.3)');
        $this->assertEquals($array['coordinates'], [[174.5,-41.3], [175.5,-42.3]]);
    }

    public function testPolygonArrayFromWkt()
    {
        $array = DBGeography::to_array('SRID=4326;POLYGON((-10 40,-8 40,-8 35,-10 35,-10 40))');
        $this->assertEquals($array['coordinates'], [[
            [-10,40],
            [ -8,40],
            [ -8,35],
            [-10,35],
            [-10,40],
        ]]);
    }

    public function testReprojection()
    {
        $array = DBGeography::to_array('SRID=4326;POINT(174.5 -41.3)');
        $this->assertEquals([1725580.442709817, 5426854.149476525], DBGeography::to_srid($array, 2193)['coordinates']);

        $array = DBGeography::to_array('SRID=2193;POINT(1753000	5432963)');
        $this->assertEquals([174.82583517653558, -41.240268094959326], DBGeography::to_srid($array, 4326)['coordinates']);
    }

    public function testDbRoundTrip()
    {
        $wkt = DBGeography::from_array([10,53.5]);
        $address = TestGeography::create();
        $address->GeoLocation = $wkt;
        $id = $address->write();

        $address1 = TestGeography::get()->byID($id);
        $this->assertEquals($wkt, $address1->GeoLocation);

        $wkt = DBGeography::from_array([174.5,-41.3]);
        $address->GeoLocation = $wkt;
        $address->write();
        $this->assertEquals($wkt, TestGeography::get()->byID($id)->GeoLocation);

        $address1->GeoLocation = $wkt;
        $address1->write();
        $this->assertEquals($wkt, TestGeography::get()->byID($id)->GeoLocation);
    }

    public function testWithinFilter()
    {
        $address = TestGeography::create(['Name' => 'Lisbon']);
        $address->GeoLocation = DBGeography::from_array([-9.1,38.7]);
        $address->write();

        $box = DBGeography::from_array([[
            [-10,40],
            [ -8,40],
            [ -8,35],
            [-10,35],
            [-10,40],
        ]]);
        $lisbon = TestGeography::get()->filter('GeoLocation:WithinGeo', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:WithinGeo:not', $box)->count());

        $box = DBGeography::from_array([[
            [10,40],
            [ 8,40],
            [ 8,35],
            [10,35],
            [10,40],
        ]]);
        $lisbon = TestGeography::get()->filter('GeoLocation:WithinGeo:not', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:WithinGeo', $box)->count());
    }

    public function testTypeFilter()
    {
        $address = TestGeography::create();
        $address->GeoLocation = DBGeography::from_array([10,53.5]);
        $address->write();

        $this->assertEquals(1, TestGeography::get()->filter('GeoLocation:TypeGeo', 'Point')->count());
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:TypeGeo:not', 'Point')->count());
        $this->assertEquals(1, TestGeography::get()->filter('GeoLocation:TypeGeo', ['Point', 'LineString'])->count());
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:TypeGeo:not', ['Point', 'LineString'])->count());
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:TypeGeo', ['Polygon', 'LineString'])->count());
        $this->assertEquals(1, TestGeography::get()->filter('GeoLocation:TypeGeo:not', ['Polygon', 'LineString'])->count());
    }

    public function testDWithinFilter()
    {
        if (preg_match('/MariaDB/', DB::get_conn()->getVersion(), $matches)) {
            $this->markTestSkipped('ST_Distance_Sphere currently not implemented in MariaDB.');
        }

        $address = TestGeography::create();
        $address->GeoLocation = DBGeography::from_array([10,53.5]);
        $address->write();

        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:DWithinGeo', [DBGeography::from_array([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(1, TestGeography::get()->filter('GeoLocation:DWithinGeo', [DBGeography::from_array([-9.1,38.7]), 2200000])->count());
        $this->assertEquals(1, TestGeography::get()->filter('GeoLocation:DWithinGeo:not', [DBGeography::from_array([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(0, TestGeography::get()->filter('GeoLocation:DWithinGeo:not', [DBGeography::from_array([-9.1,38.7]), 2200000])->count());
    }

    public function testDistance()
    {
        if (preg_match('/MariaDB/', DB::get_conn()->getVersion(), $matches)) {
            $this->markTestSkipped('ST_Distance_Sphere currently not implemented in MariaDB.');
        }

        $geo1 = DBGeography::from_array([10,53.5]);
        $geo2 = DBGeography::from_array([-9.1,38.7]);
        $distance = DBGeography::distance($geo1, $geo2);

        $this->assertTrue($distance > 2190000 && $distance < 2200000);
    }
}
