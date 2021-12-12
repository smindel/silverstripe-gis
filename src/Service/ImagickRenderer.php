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

        if ($this->defaultStyle['marker'] ?? 0) {
            switch ($this->defaultStyle['marker']['offset'][0] ?? 0) {
                case 'left':
                    $this->defaultStyle['marker_offset_x'] = 0;
                    break;
                case 'center':
                    $this->defaultStyle['marker_offset_x'] =
                        getimagesize($this->defaultStyle['marker']['image'])[0] / 2;
                    break;
                case 'right':
                    $this->defaultStyle['marker_offset_x'] = getimagesize($this->defaultStyle['marker']['image'])[0];
                    break;
                default:
                    $this->defaultStyle['marker_offset_x'] = $this->defaultStyle['marker']['offset'][0] ?? 0;
            }
            switch ($this->defaultStyle['marker']['offset'][1] ?? 0) {
                case 'top':
                    $this->defaultStyle['marker_offset_y'] = 0;
                    break;
                case 'middle':
                    $this->defaultStyle['marker_offset_y'] =
                        getimagesize($this->defaultStyle['marker']['image'])[1] / 2;
                    break;
                case 'bottom':
                    $this->defaultStyle['marker_offset_y'] =
                        getimagesize($this->defaultStyle['marker']['image'])[1];
                    break;
                default:
                    $this->defaultStyle['marker_offset_y'] = $this->defaultStyle['marker']['offset'][0] ?? 0;
            }

            $this->defaultStyle['marker_image'] = new Imagick();
            $this->defaultStyle['marker_image']->readImage($this->defaultStyle['marker']['image']);
        }
    }

    public function debug($text)
    {
        $draw = new ImagickDraw();
        $draw->setStrokeOpacity(1);
        $draw->setStrokeColor(new ImagickPixel('rgb(92,92,255)'));
        $draw->setFillColor(new ImagickPixel('rgba(92,92,255,0)'));
        $draw->setStrokeDashArray([5, 5]);
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
        if ($style instanceof ImagickDraw) {
            return $style;
        }

        $style = array_merge($this->defaultStyle, $style);

        $draw = new ImagickDraw();

        foreach ($style as $key => $value) {
            if (substr($key, -5) == 'Color') {
                $value = new ImagickPixel($value);
            }

            if ($value !== null) {
                if (!is_array($value)) {
                    $value = [$value];
                }
                if (method_exists($draw, $key)) {
                    $draw->$key(...$value);
                } elseif (method_exists($draw, 'set' . $key)) {
                    $draw->{'set' . $key}(...$value);
                }
            }
        }

        return $draw;
    }

    public function drawMarker($coordinates, $style = [])
    {
        if (!count($style)) {
            $this->getDraw($style);
        }

        $this->image->compositeImage(
            $style['marker_image'],
            imagick::COMPOSITE_OVER,
            $coordinates[0] - $style['marker_offset_x'],
            $coordinates[1] - $style['marker_offset_y']
        );
    }

    public function drawCircle($coordinates, $style = [])
    {
        $draw = $this->getDraw($style);

        list($x, $y) = $coordinates;
        $draw->circle($x, $y, $x + $style['PointRadius'], $y + $style['PointRadius']);

        $this->image->drawImage($draw);
    }

    public function drawPoint($coordinates, $style = [])
    {
        $this->getDraw($style);

        if (isset($style['marker_image'])) {
            $this->drawMarker($coordinates, $style);
        } else {
            $this->drawCircle($coordinates, $style);
        }
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

        $draw->pathStart();
        foreach ($coordinates as $ring) {
            $draw->pathMoveToAbsolute(...array_shift($ring));
            foreach ($ring as $point) {
                $draw->pathLineToAbsolute(...$point);
            }
            $draw->pathclose();
        }
        $draw->pathFinish();
        $this->image->drawImage($draw);
    }

    public function drawMultipoint($multiCoordinates, $style = [])
    {
        foreach ($multiCoordinates as $coordinates) {
            $this->drawPoint($coordinates, $style);
        }
    }

    public function drawMultilinestring($multiCoordinates, $style = [])
    {
        foreach ($multiCoordinates as $coordinates) {
            $this->drawLinestring($coordinates, $style);
        }
    }

    public function drawMultipolygon($multiCoordinates, $style = [])
    {
        foreach ($multiCoordinates as $coordinates) {
            $this->drawPolygon($coordinates, $style);
        }
    }

    public function getImageBlob()
    {
        return $this->image->getImageBlob();
    }
}
