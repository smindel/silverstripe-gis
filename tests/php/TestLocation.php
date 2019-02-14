<?php

namespace Smindel\GIS\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class TestLocation extends DataObject implements TestOnly
{
    private static $table_name = 'TestLocation';

    private static $db = [
        'Name' => 'Varchar',
        'GeoLocation' => 'Geometry',
    ];
}
