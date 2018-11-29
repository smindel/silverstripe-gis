<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use Smindel\GIS\ORM\FieldType\DBGeography;
use Imagick;
use ImagickDraw;
use ImagickPixel;

class ImagickTileRenderer
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
        $this->tile = new Imagick();
        $this->tile->newImage($this->tileWidth, $this->tileHeight, new ImagickPixel('rgba(0,0,0,0)'));
        $this->tile->setImageFormat('png');
    }

    protected function drawPoint($dataObject)
    {
        $point = new ImagickDraw();
        $point->setStrokeOpacity(1);
        $point->setStrokeColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setFillColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setStrokeWidth(2);

        list($x, $y) = $dataObject->_tileCoordinates;
        $point->circle($x, $y, $x + 5, $y + 5);

        $this->tile->drawImage($point);
    }

    protected function drawLine($dataObject)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setStrokeWidth(2);

        $points = [];
        foreach ($dataObject->_tileCoordinates as $j => $coordinate) {
            $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
        }

        $polygon->polyline($points);

        $this->tile->drawImage($polygon);
    }

    protected function drawPolygon($dataObject)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($dataObject->_tileCoordinates as $tileCoordinates) {
            $points = [];
            foreach ($tileCoordinates as $j => $coordinate) {
                $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
            }
            $polygon->polygon($points);
            $this->tile->drawImage($polygon);
        }
    }

    protected function drawMultipolygon($dataObject)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($dataObject->_tileCoordinates as $polygonTileCoordinates) {
            foreach ($polygonTileCoordinates as $tileCoordinates) {
                $points = [];
                foreach ($tileCoordinates as $j => $coordinate) {
                    $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
                }
                $polygon->polygon($points);
                $this->tile->drawImage($polygon);
            }
        }
    }

    protected function toString()
    {
        return $this->tile->getImageBlob();
    }
}
