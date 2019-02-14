<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class GeometryTest extends SapphireTest
{
    protected static $fixture_file = 'TestGeometry.yml';

    public function setUp()
    {
        // reset GIS environment
        Config::modify()->set(GIS::class, 'default_srid', 0);
        Config::modify()->set(GIS::class, 'projections', [
            0 => null,
        ]);
        parent::setUp();
    }

    public static function getExtraDataObjects()
    {
        return [TestGeometry::class];
    }

    public function testDbRoundTrip()
    {
        // write a geometry
        $wkt = GIS::array_to_ewkt([10,53.5]);
        $address = TestGeometry::create();
        $address->GeoLocation = $wkt;
        $id = $address->write();

        // read it back
        $address1 = TestGeometry::get()->byID($id);
        $this->assertEquals($wkt, $address1->GeoLocation);

        // change and check changed
        $wkt = GIS::array_to_ewkt([174.5,-41.3]);
        $address->GeoLocation = $wkt;
        $address->write();
        $this->assertEquals($wkt, TestGeometry::get()->byID($id)->GeoLocation);
    }

    public function testStGenericFilter()
    {
        $reference = $this->objFromFixture(TestGeometry::class, 'reference');

        foreach ([
            'Contains' => ['Contains', 'Equals'],
            'Crosses' => ['Crosses'],
            'Disjoint' => ['Disjoint', 'Distance', 'GeometryType'],
            'Equals' => ['Equals'],
            'Intersects' => ['Contains', 'Crosses', 'Equals', 'Intersects', 'Overlaps', 'Touches', 'Within'],
            'Overlaps' => ['Intersects', 'Overlaps'],
            'Touches' => ['Touches'],
            'Within' => ['Equals', 'Within'],
        ] as $filter => $geometries) {
            $matches = TestGeometry::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_' . $filter, $reference->GeoLocation)
                ->map()
                ->toArray();
            asort($matches);
            $this->assertEquals($geometries, array_values($matches), $filter);
        }
    }

    public function testStDistanceFilter()
    {
        $reference = $this->objFromFixture(TestGeometry::class, 'distance');

        foreach ([
            9 => 2,
            10 => 8,
            11 => 10,
        ] as $distance => $count) {

            $within = TestGeometry::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_Distance', [$reference->GeoLocation, $distance]);

            $notWithin = TestGeometry::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_Distance:not', [$reference->GeoLocation, $distance]);

            $this->assertEquals($count, $within->count(), 'within ' . $distance);
            $this->assertEquals(10 - $count, $notWithin->count(), 'not within ' . $distance);
        }
    }

    public function testGeometryTypeFilter()
    {
        $this->assertEquals(1, TestGeometry::get()->filter('GeoLocation:ST_GeometryType', 'MultiLineString')->count());
        $this->assertEquals(3, TestGeometry::get()->filter('GeoLocation:ST_GeometryType:not', 'Polygon')->count());
    }
}
