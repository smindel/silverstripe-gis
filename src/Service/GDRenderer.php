<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use Smindel\GIS\ORM\FieldType\DBGeography;

class GDRenderer
{
    use Injectable;

    use Configurable;

    const LIB_NAME = 'Imagick';

    protected $tileWidth;

    protected $tileHeight;

    protected $list;

    protected $image;

    public function __construct($tileWidth = 256, $tileHeight = 256)
    {
        $this->tileWidth = $tileWidth;
        $this->tileHeight = $tileHeight;

        $this->image = imagecreate($this->tileWidth, $this->tileHeight);
        imagecolorallocatealpha($this->image, 0, 0, 0, 127);
        imagecolorallocate($this->image, 255, 0, 0);
        imagecolorallocatealpha($this->image, 255, 0, 0, 96);
    }

    public function getTileContentType()
    {
        return 'image/png';
    }

    public function drawPointToTile($tileCoordinates)
    {
        $boxpadding = 2;
        list($x, $y) = $tileCoordinates;
        imagefilledrectangle($this->image, $x - $boxpadding, $y - $boxpadding, $x + $boxpadding, $y + $boxpadding, 1);
    }

    public function drawLineStringToTile($tileCoordinates)
    {
        $boxpadding = 2;
        foreach ($tileCoordinates as $coord) {
            if (isset($prev)) {
                imagefilledrectangle($this->image, $prev[0], $prev[1], $coord[0], $coord[1], 1);
            }
            imagefilledrectangle($this->image, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
            $prev = $coord;
        }
    }

    public function drawPolygonToTile($tileCoordinates)
    {
        $boxpadding = 2;
        foreach ($tileCoordinates as $coords) {
            $xy = [];
            foreach ($coords as $coord) {
                $xy[] = $coord[0];
                $xy[] = $coord[1];
                imagefilledrectangle($this->image, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
            }
            imagefilledpolygon($this->image, $xy, count($xy) / 2, 2);
            imagepolygon($this->image, $xy, count($xy) / 2, 1);
        }
    }

    public function drawMultipolygonToTile($tileCoordinates)
    {
        $boxpadding = 2;
        foreach ($tileCoordinates as $polygonCoords) {
            foreach ($polygonCoords as $coords) {
                $xy = [];
                foreach ($coords as $coord) {
                    $xy[] = $coord[0];
                    $xy[] = $coord[1];
                    imagefilledrectangle($this->image, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
                }
                imagefilledpolygon($this->image, $xy, count($xy) / 2, 2);
                imagepolygon($this->image, $xy, count($xy) / 2, 1);
            }
        }
    }

    public function getImageBlob()
    {
        ob_start();
        imagepng($this->image);
        $blob = ob_get_clean();
        imagedestroy($this->image);

        return $blob;
    }
}
