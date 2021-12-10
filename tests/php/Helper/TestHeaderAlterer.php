<?php

namespace Smindel\GIS\Tests\Helper;

use Smindel\GIS\Interfaces\HeaderAltererInterface;

class TestHeaderAlterer implements HeaderAltererInterface
{

    public function setHeader($headerString)
    {
        error_log('TEST HEADER: ' . $headerString);
    }
}
