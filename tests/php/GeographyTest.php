<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class GeographyTest extends SapphireTest
{
    protected static $test_methods = [
        'Intersects' => ['Contains', 'Crosses', 'Equals', 'Intersects', 'Overlaps', 'Touches', 'Within'],
    ];

    protected static $test_distances = [
        1000755 => 2,
        1111950 => 8,
        1223145 => 10,
    ];

    public static function getExtraDataObjects()
    {
        if (static::class == self::class &&
            DB::get_schema()->geography(null) == 'geography'
        ) {
            static::$fixture_file = 'TestGeography.yml';
            return [TestGeography::class];
        }
    }

    public function testDbRoundTrip()
    {
        if (static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }

        $class = $this->getExtraDataObjects()[0];

        // write a geometry
        $geo = GIS::create([10,53.5]);
        $address = $class::create();
        $address->GeoLocation = (string)$geo;
        $id = $address->write();

        // read it back
        $address1 = $class::get()->byID($id);
        $this->assertEquals((string)$geo, $address1->GeoLocation);

        // change and check changed
        $wkt = GIS::create([174.5,-41.3]);
        $address->GeoLocation = (string)$wkt;
        $address->write();
        $this->assertEquals((string)$wkt, $class::get()->byID($id)->GeoLocation);
    }

    public function testStGenericFilter()
    {
        if (static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }
        
        $class = $this->getExtraDataObjects()[0];
        $reference = $this->objFromFixture($class, 'reference');

        $all = $class::get()->exclude('ID', $reference->ID)
            ->map()
            ->toArray();

        foreach (static::$test_methods as $filter => $geometries) {
            $matches = $class::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_' . $filter, $reference->GeoLocation)
                ->map()
                ->toArray();
            asort($matches);

            $this->assertEquals($geometries, array_values($matches), $filter);

            $matches = $class::get()
                ->exclude('ID', $reference->ID)
                ->exclude('GeoLocation:ST_' . $filter, $reference->GeoLocation)
                ->map()
                ->toArray();
            sort($matches);
            $nots = array_diff($all, $geometries);
            sort($nots);
            $this->assertEquals(array_values($nots), array_values($matches), $filter);
        }
    }

    public function testStDistanceFilter()
    {
        if (static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }

        $class = $this->getExtraDataObjects()[0];

        $reference = $this->objFromFixture($class, 'distance');

        foreach (static::$test_distances as $distance => $count) {
            $within = $class::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_Distance', [$reference->GeoLocation, $distance]);

            $notWithin = $class::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_Distance:not', [$reference->GeoLocation, $distance]);

            $this->assertEquals($count, $within->count(), 'within ' . $distance);
            $this->assertEquals(10 - $count, $notWithin->count(), 'not within ' . $distance);
        }
    }

    public function testGeometryTypeFilter()
    {
        if (static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }

        $class = $this->getExtraDataObjects()[0];

        if ($class == TestGeography::class) {
            $this->markTestSkipped('GeometryTypeFilter does not yet work with Geography');
        }

        $this->markTestSkipped('@TODO This is failing on a collation issue.  FIXME');

        $this->assertEquals(1, $class::get()->filter('GeoLocation:ST_GeometryType', 'MultiLineString')->count());
        $this->assertEquals(3, $class::get()->filter('GeoLocation:ST_GeometryType:not', 'Polygon')->count());
    }
}
