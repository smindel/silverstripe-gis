<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use Smindel\GIS\ORM\FieldType\DBGeography;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class ImagickRenderer extends Imagick
{
    use Injectable;

    use Configurable;

    const LIB_NAME = 'Imagick';

    protected $tileWidth;

    protected $tileHeight;

    protected $list;

    public function __construct($tileWidth = 256, $tileHeight = 256)
    {
        parent::__construct();
        $this->tileWidth = $tileWidth;
        $this->tileHeight = $tileHeight;

        $this->newImage($this->tileWidth, $this->tileHeight, new ImagickPixel('rgba(0,0,0,0)'));
        $this->setImageFormat('png');
    }

    public function getTileContentType()
    {
        return 'image/png';
    }

    public function drawPointToTile($tileCoordinates)
    {
        $point = new ImagickDraw();
        $point->setStrokeOpacity(1);
        $point->setStrokeColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setFillColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setStrokeWidth(2);

        list($x, $y) = $tileCoordinates;
        $point->circle($x, $y, $x + 5, $y + 5);

        $this->drawImage($point);
    }

    public function drawLineStringToTile($tileCoordinates)
    {
        $linestring = new ImagickDraw();
        $linestring->setStrokeOpacity(1);
        $linestring->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $linestring->setFillColor(new ImagickPixel('rgba(92,92,255,0)'));
        $linestring->setStrokeWidth(2);

        $points = [];
        foreach ($tileCoordinates as $j => $coordinate) {
            $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
        }

        $linestring->polyline($points);

        $this->drawImage($linestring);
    }

    public function drawPolygonToTile($tileCoordinates)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($tileCoordinates as $tilePolyCoordinates) {
            $points = [];
            foreach ($tilePolyCoordinates as $j => $coordinate) {
                $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
            }
            $polygon->polygon($points);
            $this->drawImage($polygon);
        }
    }

    public function drawMultipolygonToTile($tileCoordinates)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($tileCoordinates as $polygonTileCoordinates) {
            foreach ($polygonTileCoordinates as $tileCoordinates) {
                $points = [];
                foreach ($tileCoordinates as $j => $coordinate) {
                    $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
                }
                $polygon->polygon($points);
                $this->drawImage($polygon);
            }
        }
    }
}
