<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use Smindel\GIS\ORM\FieldType\DBGeography;
use Imagick;
use ImagickDraw;
use ImagickPixel;

// @todo: extending imagick makes it a hard dependency, so don't

class ImagickRenderer
{
    use Injectable;

    use Configurable;

    const LIB_NAME = 'Imagick';

    protected $width;

    protected $height;

    protected $list;

    protected $image;

    public function __construct($width, $height)
    {
        $this->width = $width;
        $this->height = $height;

        $this->image = new Imagick();

        $this->image->newImage($this->width, $this->height, new ImagickPixel('rgba(0,0,0,0)'));
        $this->image->setImageFormat('png');
    }

    public function debug($text)
    {
        $draw = new ImagickDraw();
        $draw->setStrokeOpacity(1);
        $draw->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $draw->setFillColor(new ImagickPixel('rgba(92,92,255,0)'));
        $draw->setStrokeDashArray([5,5]);
        $draw->setStrokeWidth(1);

        $draw->polyline([
            ['x' => 0, 'y' => 255],
            ['x' => 0, 'y' => 0],
            ['x' => 255, 'y' => 0],
        ]);

        $draw->setStrokeDashArray([0]);
        $draw->setFont('DejaVu-Sans');
        $draw->setFontSize(15);
        $draw->setFontWeight(700);
        $draw->setStrokeAntialias(true);
        $draw->setTextAntialias(true);

        $draw->annotation(5, 15, $text);

        $this->image->drawImage($draw);
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

        $this->image->drawImage($point);
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

        $this->image->drawImage($linestring);
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
            $this->image->drawImage($polygon);
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
                $this->image->drawImage($polygon);
            }
        }
    }

    public function getImageBlob()
    {
        return $this->image->getImageBlob();
    }
}
