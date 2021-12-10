<?php

namespace Smindel\GIS\Interfaces;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Smindel\GIS\GIS;

/*
gdaldem hillshade -of PNG public/assets/wellington-lidar-1m-dem-2013.4326.tif hillshade.png
*/

interface HeaderAltererInterface
{
    public function setHeader($headerString);
}
