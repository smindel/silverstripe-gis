<?php

namespace Smindel\GIS\Tests;

use SilverStripe\Core\Config\Config;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class GeometryTest extends GeographyTest
{
    protected static $fixture_file = 'TestGeometry.yml';

    // @todo Are these changes correct?
    protected static $test_methods = [
        'Contains' => ['Contains', 'Equals'],
        'Crosses' => ['Crosses', 'Intersects', 'Overlaps', 'Touches'],
        'Disjoint' => ['Disjoint', 'Distance', 'GeometryType'],
        'Equals' => ['Equals'],
        'Intersects' => ['Contains', 'Crosses', 'Equals', 'Intersects', 'Overlaps', 'Touches', 'Within'],
        'Overlaps' => ['Crosses', 'Intersects', 'Overlaps', 'Touches'],
        'Touches' => ['Touches'],
        'Within' => ['Equals', 'Within'],
    ];

    protected static $test_distances = [
        9 => 2,
        10 => 8,
        11 => 10,
    ];

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
}
