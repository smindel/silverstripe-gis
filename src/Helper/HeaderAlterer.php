<?php

namespace Smindel\GIS\Helper;

/*
gdaldem hillshade -of PNG public/assets/wellington-lidar-1m-dem-2013.4326.tif hillshade.png
*/

use Smindel\GIS\Interfaces\HeaderAltererInterface;

class HeaderAlterer implements HeaderAltererInterface
{
    public function setHeader($headerString)
    {
        header($headerString);
    }
}
