<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;
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

    protected $defaultStyle;

    public function __construct($width, $height, $defaultStyle = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->defaultStyle = $defaultStyle['imagick'];

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

    public function getDraw(&$style)
    {
        if ($style instanceof ImagickDraw) return $style;

        $style = array_merge($this->defaultStyle, $style);

        $draw = new ImagickDraw();

        foreach ($style as $key => $value) {
            if (substr($key, -5) == 'Color') $value = new ImagickPixel($value);
            else if ($value === null || $key == 'PointRadius') continue;
            $draw->{'set' . $key}($value);
        }

        return $draw;
    }

    public function drawPoint($coordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        list($x, $y) = $coordinates;
        $draw->circle($x, $y, $x + $style['PointRadius'], $y + $style['PointRadius']);

        $this->image->drawImage($draw);
    }

    public function drawLineString($coordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        $points = [];
        foreach ($coordinates as $j => $coordinate) {
            $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
        }

        $draw->polyline($points);

        $this->image->drawImage($draw);
    }

    public function drawPolygon($coordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        foreach ($coordinates as $polyCoordinates) {
            $points = [];
            foreach ($polyCoordinates as $j => $coordinate) {
                $points[$j] = ['x' => $coordinate[0], 'y' => $coordinate[1]];
            }
            $draw->polygon($points);
            $this->image->drawImage($draw);
        }
    }

    public function drawMultipoint($multiCoordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        foreach ($multiCoordinates as $coordinates) {
            $this->drawPoint($coordinates, $draw);
        }
    }

    public function drawMultilinestring($multiCoordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        foreach ($multiCoordinates as $coordinates) {
            $this->drawLinestring($coordinates, $draw);
        }
    }

    public function drawMultipolygon($multiCoordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        foreach ($multiCoordinates as $coordinates) {
            $this->drawPolygon($coordinates, $draw);
        }
    }

    public function getImageBlob()
    {
        return $this->image->getImageBlob();
    }
}
