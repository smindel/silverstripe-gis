<?php

namespace Smindel\GIS\Tests;

use Smindel\GIS\Model\Raster;
use SilverStripe\Dev\TestOnly;

class TestRaster extends Raster implements TestOnly
{
    private static $full_path = __DIR__ . DIRECTORY_SEPARATOR . 'RasterTest.tif';
    private static $webmaptileservice = true;
}
