<?php

namespace Smindel\Tests;

use SilverStripe\ORM\DataObject;
use SilverStripe\Dev\TestOnly;

class TestAddress extends DataObject implements TestOnly
{
    private static $table_name = 'TestAddress';

    private static $db = [
        'Name' => 'Varchar',
        'GeoLocation' => 'Geography("POINT",4326)',
    ];
}
