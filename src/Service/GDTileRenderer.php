<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use Smindel\GIS\ORM\FieldType\DBGeography;

class GDTileRenderer
{
    use Injectable;

    use Configurable;

    protected $tileWidth;

    protected $tileHeight;

    protected $list;

    protected $tile;

    public function __construct($tileWidth = 256, $tileHeight = 256)
    {
        $this->tileWidth = $tileWidth;
        $this->tileHeight = $tileHeight;
    }

    public function getContentType()
    {
        return 'image/png';
    }

    public function render($list)
    {
        $tile = $this->initialiseImage();

        foreach (($list)($this->tileWidth, $this->tileHeight) as $dataObject) {
            $this->{'draw' . $dataObject->_type}($dataObject);
        }

        return $this->toString();
    }

    protected function initialiseImage()
    {
        $this->tile = imagecreate($this->tileWidth, $this->tileHeight);
        imagecolorallocatealpha($this->tile, 0, 0, 0, 127);
        imagecolorallocate($this->tile, 255, 0, 0);
        imagecolorallocatealpha($this->tile, 255, 0, 0, 96);
    }

    protected function drawPoint($dataObject)
    {
        $boxpadding = 2;
        list($x, $y) = $dataObject->_tileCoordinates;
        imagefilledrectangle($this->tile, $x - $boxpadding, $y - $boxpadding, $x + $boxpadding, $y + $boxpadding, 1);
    }

    protected function drawLine($dataObject)
    {
        throw new Exception(__METHOD__ . ' not yet implemented.');
    }

    protected function drawPolygon($dataObject)
    {
        $boxpadding = 2;
        foreach ($dataObject->_tileCoordinates as $coords) {
            $xy = [];
            foreach ($coords as $coord) {
                $xy[] = $coord[0];
                $xy[] = $coord[1];
                imagefilledrectangle($this->tile, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
            }
            imagefilledpolygon($this->tile, $xy, count($xy) / 2, 2);
            imagepolygon($this->tile, $xy, count($xy) / 2, 1);
        }
    }

    protected function drawMultipolygon($dataObject)
    {
        $boxpadding = 2;
        foreach ($dataObject->_tileCoordinates as $polygonCoords) {
            foreach ($polygonCoords as $coords) {
                $xy = [];
                foreach ($coords as $coord) {
                    $xy[] = $coord[0];
                    $xy[] = $coord[1];
                    imagefilledrectangle($this->tile, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
                }
                imagefilledpolygon($this->tile, $xy, count($xy) / 2, 2);
                imagepolygon($this->tile, $xy, count($xy) / 2, 1);
            }
        }
    }

    protected function toString()
    {
        ob_start();
        imagepng($this->tile);
        $binary = ob_get_clean();
        imagedestroy($this->tile);

        return $binary;
    }
}
