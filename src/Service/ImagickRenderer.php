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

    protected $width;

    protected $height;

    protected $list;

    public function __construct($width, $height)
    {
        parent::__construct();
        $this->width = $width;
        $this->height = $height;

        $this->newImage($this->width, $this->height, new ImagickPixel('rgba(0,0,0,0)'));
        $this->setImageFormat('png');
    }

    public function getContentType()
    {
        return 'image/png';
    }

    public function drawPoint($coordinates)
    {
        $point = new ImagickDraw();
        $point->setStrokeOpacity(1);
        $point->setStrokeColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setFillColor(new ImagickPixel('rgb(60,60,210)'));
        $point->setStrokeWidth(2);

        list($x, $y) = $coordinates;
        $point->circle($x, $y, $x + 5, $y + 5);

        $this->drawImage($point);
    }

    public function drawLineString($coordinates)
    {
        $linestring = new ImagickDraw();
        $linestring->setStrokeOpacity(1);
        $linestring->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $linestring->setFillColor(new ImagickPixel('rgba(92,92,255,0)'));
        $linestring->setStrokeWidth(2);

        $points = [];
        foreach ($coordinates as $j => $coordinate) {
            $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
        }

        $linestring->polyline($points);

        $this->drawImage($linestring);
    }

    public function drawPolygon($coordinates)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($coordinates as $polyCoordinates) {
            $points = [];
            foreach ($polyCoordinates as $j => $coordinate) {
                $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
            }
            $polygon->polygon($points);
            $this->drawImage($polygon);
        }
    }

    public function drawMultipolygon($coordinates)
    {
        $polygon = new ImagickDraw();
        $polygon->setStrokeOpacity(1);
        $polygon->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $polygon->setFillColor(new ImagickPixel('rgba(92,92,255,.25)'));
        $polygon->setStrokeWidth(2);

        foreach ($coordinates as $polygonCoordinates) {
            foreach ($polygonCoordinates as $coords) {
                $points = [];
                foreach ($coords as $j => $coordinate) {
                    $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
                }
                $polygon->polygon($points);
                $this->drawImage($polygon);
            }
        }
    }
}
