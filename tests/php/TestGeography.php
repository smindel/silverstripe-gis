<?php

namespace Smindel\GIS\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class TestGeography extends DataObject implements TestOnly
{
    private static $table_name = 'TestGeography';

    private static $db = [
        'Name' => 'Varchar',
        'GeoLocation' => 'Geography',
    ];
}
