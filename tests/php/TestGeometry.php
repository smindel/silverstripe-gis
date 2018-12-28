<?php

namespace Smindel\GIS\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class TestGeometry extends DataObject implements TestOnly
{
    private static $table_name = 'TestGeometry';

    private static $db = [
        'Name' => 'Varchar',
        'GeoLocation' => 'Geometry',
    ];
}
