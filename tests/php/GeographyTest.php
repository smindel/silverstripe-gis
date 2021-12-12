<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class GeographyTest extends SapphireTest
{
    protected static $mysql_test_methods = [
        'Intersects' => ['Contains', 'Crosses', 'Equals', 'Intersects', 'Overlaps', 'Touches', 'Within'],
    ];

    protected static $mariadb_test_methods = [
        'Intersects' => ['Contains', 'Crosses', 'Equals', 'Intersects', 'Overlaps', 'Touches', 'Within'],
    ];

    protected static $test_distances = [
        1000755 => 2,
        1111950 => 8,
        1223145 => 10,
    ];

    public static function getExtraDataObjects()
    {
        if (
            static::class == self::class &&
            DB::get_schema()->geography(null) == 'geography'
        ) {
            static::$fixture_file = 'TestGeography.yml';
            return [TestGeography::class];
        }
    }

    public function testDbRoundTrip()
    {
        if (
            static::class == GeographyTest::class &&
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


    // @TODO This test fails with mariadb (10.7) as the database
    public function testStGenericFilter()
    {
        $databaseServer = 'UNKNOWN';

        if (
            static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }

        $client = DB::get_conn()->getDatabaseServer();
        $databaseVersion = DB::query('select version()')->value();
  #      error_log('**** database version: ' . $databaseVersion);

        if ($client == 'mysql') {
            if (strpos($databaseVersion, 'MariaDB') !== false) {
                $databaseServer = 'mariadb';
            } else {
                // mysql only spits out a version number
                $databaseServer = 'mysql';
            }
        }

        $methodsToTest = static::$mysql_test_methods;
        if ($databaseServer == 'mariadb') {
            $methodsToTest = static::$mariadb_test_methods;
        }

     #   error_log('Database Server: ' . $databaseServer);


        $class = $this->getExtraDataObjects()[0];

        $reference = $this->objFromFixture($class, 'reference');

        $all = $class::get()->exclude('ID', $reference->ID)
            ->map()
            ->toArray();

  #      error_log('Test methods');
   #     error_log(print_r($methodsToTest, true));

        foreach ($methodsToTest as $filter => $geometries) {
            #error_log('FILTER: ' . $filter);
            $matches = $class::get()
                ->exclude('ID', $reference->ID)
                ->filter('GeoLocation:ST_' . $filter, $reference->GeoLocation)
                ->map()
                ->toArray();
            asort($matches);

      #      error_log('GEOMS: ' . print_r($geometries, true));
       #     error_log('MATCHES:' . print_r($matches, true));


            switch($databaseServer) {
                case 'mysql':
                    $this->assertEquals($geometries, array_values($matches), $filter);
                    break;

                case 'mariadb':
                    $this->assertEquals($geometries, array_values($matches), $filter);
                    break;

            }


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
        if (
            static::class == GeographyTest::class &&
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
        if (
            static::class == GeographyTest::class &&
            DB::get_schema()->geography(null) != 'geography'
        ) {
            $this->markTestSkipped('MySQL does not support Geography.');
        }

        $class = $this->getExtraDataObjects()[0];

        if ($class == TestGeography::class) {
            $this->markTestSkipped('GeometryTypeFilter does not yet work with Geography');
        }

        $result = DB::query('SELECT @@character_set_database, @@collation_database;');
        error_log('T1: ' . print_r($result, true));

        $result =  DB::query('SELECT     table_schema,    table_name,    table_collation    FROM information_schema.tables');
        error_log('T2: ' . print_r($result, true));

        $this->assertEquals(0, $class::get()->filter('GeoLocation:ST_GeometryType', 'Wibble')->count());
        $this->assertEquals(1, $class::get()->filter('GeoLocation:ST_GeometryType', 'MultiLineString')->count());
        $this->assertEquals(3, $class::get()->filter('GeoLocation:ST_GeometryType:not', 'Polygon')->count());
    }
}
