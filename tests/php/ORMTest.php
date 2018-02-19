<?php

namespace Smindel\Tests;

use SilverStripe\Dev\SapphireTest;
use Smindel\GIS\ORM\FieldType\DBGeography;

class ORMTest extends SapphireTest
{
    protected $usesDatabase = true;

    public static function getExtraDataObjects()
    {
        return [TestAddress::class];
    }

    public function testDbRoundTrip()
    {
        $wkt = DBGeography::fromArray([10,53.5]);
        $address = TestAddress::create();
        $address->GeoLocation = $wkt;
        $id = $address->write();

        $address1 = TestAddress::get()->byID($id);
        $this->assertEquals($wkt, $address1->GeoLocation);

        $wkt = DBGeography::fromArray([174.5,-41.3]);
        $address->GeoLocation = $wkt;
        $address->write();
        $this->assertEquals($wkt, TestAddress::get()->byID($id)->GeoLocation);

        $address1->GeoLocation = $wkt;
        $address1->write();
        $this->assertEquals($wkt, TestAddress::get()->byID($id)->GeoLocation);
    }

    public function testWithinFilter()
    {
        $address = TestAddress::create(['Name' => 'Lisbon']);
        $address->GeoLocation = DBGeography::fromArray([-9.1,38.7]);
        $address->write();

        $box = DBGeography::fromArray([[
            [-10,40],
            [ -8,40],
            [ -8,35],
            [-10,35],
            [-10,40],
        ]]);
        $lisbon = TestAddress::get()->filter('GeoLocation:WithinGeo', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestAddress::get()->filter('GeoLocation:WithinGeo:not', $box)->count());

        $box = DBGeography::fromArray([[
            [10,40],
            [ 8,40],
            [ 8,35],
            [10,35],
            [10,40],
        ]]);
        $lisbon = TestAddress::get()->filter('GeoLocation:WithinGeo:not', $box)->first();
        $this->assertEquals('Lisbon', $lisbon->Name);
        $this->assertEquals(0, TestAddress::get()->filter('GeoLocation:WithinGeo', $box)->count());
    }

    public function testDWithinFilter()
    {
        $address = TestAddress::create();
        $address->GeoLocation = DBGeography::fromArray([10,53.5]);
        $address->write();

        $this->assertEquals(0, TestAddress::get()->filter('GeoLocation:DWithinGeo', [DBGeography::fromArray([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(1, TestAddress::get()->filter('GeoLocation:DWithinGeo', [DBGeography::fromArray([-9.1,38.7]), 2200000])->count());
        $this->assertEquals(1, TestAddress::get()->filter('GeoLocation:DWithinGeo:not', [DBGeography::fromArray([-9.1,38.7]), 2190000])->count());
        $this->assertEquals(0, TestAddress::get()->filter('GeoLocation:DWithinGeo:not', [DBGeography::fromArray([-9.1,38.7]), 2200000])->count());
    }

    public function testDistance()
    {
        $geo1 = DBGeography::fromArray([10,53.5]);
        $geo2 = DBGeography::fromArray([-9.1,38.7]);
        $distance = DBGeography::distance($geo1, $geo2);

        $this->assertTrue($distance > 2190000 && $distance < 2200000);
    }
}
