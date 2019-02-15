<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Config\Configurable;

class GDRenderer
{
    use Injectable;

    use Configurable;

    const LIB_NAME = 'GD';

    protected $width;

    protected $height;

    protected $list;

    protected $image;

    protected $defaultStyle;

    public function __construct($width, $height, $defaultStyle = [])
    {
        $this->width = $width;
        $this->height = $height;
        $this->defaultStyle = $defaultStyle;

        $this->image = imagecreate($this->width, $this->height);

        foreach ($defaultStyle['gd'] as $key => $val) {
            if ($key == 'pointradius') continue;
            $method = 'image' . $key;
            array_unshift($val, $this->image);
            $method(...$val);
        }
    }

    public function debug($text)
    {
        $color = imagecolorallocate($this->image, 255, 0, 0);
        imagedashedline($this->image, 0, 0, $this->width, 0, $color);
        imagedashedline($this->image, 0, 0, 0, $this->height, $color);
        imagestring($this->image, 5, 5, 5, $text, $color);
    }

    public function getImage()
    {
        return $this->image;
    }

    public function getContentType()
    {
        return 'image/png';
    }

    public function drawPoint($coordinates)
    {
        $boxpadding = $this->defaultStyle['gd']['pointradius'];
        list($x, $y) = $coordinates;
        imagefilledrectangle($this->image, $x - $boxpadding, $y - $boxpadding, $x + $boxpadding, $y + $boxpadding, 1);
    }

    public function drawLineString($coordinates)
    {
        $boxpadding = 2;
        foreach ($coordinates as $coord) {
            if (isset($prev)) {
                imageline($this->image, $prev[0], $prev[1], $coord[0], $coord[1], 1);
            }
            imagefilledrectangle($this->image, $coord[0] - $boxpadding, $coord[1] - $boxpadding, $coord[0] + $boxpadding, $coord[1] + $boxpadding, 1);
            $prev = $coord;
        }
    }

    public function drawPolygon($coordinates)
    {
        $boxpadding = 2;
        foreach ($coordinates as $coords) {
            $xy = [];
            foreach ($coords as $coord) {
                $xy[] = $coord[0];
                $xy[] = $coord[1];
            }
            imagefilledpolygon($this->image, $xy, count($xy) / 2, 2);
            imagepolygon($this->image, $xy, count($xy) / 2, 1);
        }
    }

    public function drawMultipoint($multicoordinates)
    {
        foreach ($multicoordinates as $coordinates) {
            $this->drawPoint($coordinates);
        }
    }

    public function drawMultilinestring($multicoordinates)
    {
        foreach ($multicoordinates as $coordinates) {
            $this->drawLinestring($coordinates);
        }
    }

    public function drawMultipolygon($multicoordinates)
    {
        foreach ($multicoordinates as $coordinates) {
            $this->drawPolygon($coordinates);
        }
    }

    public function getImageBlob()
    {
        ob_start();
        imagepng($this->image);
        $blob = ob_get_clean();

        return $blob;
    }
}
