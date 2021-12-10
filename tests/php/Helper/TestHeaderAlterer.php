<?php

namespace Smindel\GIS\Tests\Helper;

use Smindel\GIS\Interfaces\HeaderAltererInterface;

class TestHeaderAlterer implements HeaderAltererInterface
{

    private static $recordedHeaders = [];

    public static function getRecordedHeaders()
    {
        return self::$recordedHeaders;
    }

    public static function resetRecordedHeaders()
    {
        self::$recordedHeaders = [];
    }

    public function setHeader($headerString)
    {
        self::$recordedHeaders[] = $headerString;
    }
}
