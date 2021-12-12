<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Smindel\GIS\GIS;
use Smindel\GIS\Control\WebMapTileService;

class Tile
{
    use Configurable;
    use Injectable;

    /**
     * Zoom
     *
     * @var int
     */
    protected $z;

    /**
     * x'th tile from left
     *
     * @var int
     */
    protected $x;

    /**
     * y'th tile from the top
     *
     * @var int
     */
    protected $y;

    /**
     * Default tile width in pixel
     *
     * @var int
     */
    protected $size;

    /**
     * Wrap around dateline
     *
     * @var int
     */
    protected $wrap;

    /**
     * Top left corner of the tile
     *
     * @var array
     */
    protected $topLeft;

    protected $longSpan;

    protected $resource;

    public function __construct($z, $x, $y, $defaultStyle = [], $wrap = null, $size = null)
    {
        $this->z = $z;
        $this->x = $x;
        $this->y = $y;
        $this->wrap = $wrap !== null ? $wrap : self::config()->get('wrap_date');
        $this->size = $size ?: WebMapTileService::config()->get('tile_size');

        list($lon, $lat) = Tile::zxy2lonlat($z, $x, $y);
        $this->longSpan = [$lon, Tile::zxy2lonlat($z, $x + 1, $y)[0]];

        $this->topLeft = [
            (($lon + 180) / 360) * $this->size * pow(2, $this->z),
            (0.5 - log((1 + sin($lat * pi() / 180)) / (1 - sin($lat * pi() / 180))) /
                (4 * pi())) * $this->size * pow(2, $this->z),
        ];

        $this->resource = Injector::inst()->get('TileRenderer', false, [$this->size, $this->size, $defaultStyle]);
    }

    public function debug($text = null)
    {
        $this->resource->debug($text ?: "$this->z, $this->x, $this->y");
    }

    public function getZXY()
    {
        return [$this->z, $this->x, $this->y];
    }

    public function getContentType()
    {
        return 'image/png';
    }

    public function getLibName()
    {
        return get_class($this->resource)::LIB_NAME;
    }

    public function getResource()
    {
        return $this->resource;
    }

    public function getRelativePixelCoordinates($wkt, &$reflection = null)
    {
        $geo = GIS::create($wkt)->reproject(4326);

        { // determin rendering offset
            $min = $max = null;
            GIS::each(
                $geo,
                function ($lonlat) use (&$min, &$max) {
                    $min = is_null($min) ? $lonlat[0] : min($min, $lonlat[0]);
                    $max = is_null($max) ? $lonlat[0] : max($max, $lonlat[0]);
                }
            );
            $distance = [-360 => null, 0 => null, 360 => null];
        foreach ($distance as $offset => &$dist) {
            $dist = min($max, $this->longSpan[1] + $offset) - max($min, $this->longSpan[0] + $offset);
        }
        }

        foreach ($distance as $offset => &$dist) {
            $dist = GIS::each(
                $geo,
                function ($lonlat) use ($offset) {
                    return [
                            (($lonlat[0] + 180 - $offset) / 360) * $this->size * pow(2, $this->z) - $this->topLeft[0],
                            (0.5 - log((1 + sin($lonlat[1] * pi() / 180)) /
                                    (1 - sin($lonlat[1] * pi() / 180))) /
                                (4 * pi())) * $this->size * pow(2, $this->z) - $this->topLeft[1],
                        ];
                }
            );
        }

        $largest = &$distance[0];

        $reflection = array_filter($distance);

        return $largest;
    }

    public function render($list)
    {
        foreach ($list as $item) {
            if ($item->hasMethod('renderOnWebMapTile')) {
                $item->renderOnWebMapTile($this);
            } else {
                $property = GIS::of(get_class($item));
                $primary = $this->getRelativePixelCoordinates($item->$property, $reflections);

                if ($this->wrap) {
                    foreach ($reflections as $reflection) {
                        $this->resource->{'draw' . GIS::create($item->$property)->type}($reflection);
                    }
                } else {
                    $this->resource->{'draw' . GIS::create($item->$property)->type}($primary);
                }
            }
        }
        return $this->resource->getImageBlob();
    }

    public static function zxy2lonlat($z, $x, $y)
    {
        $n = pow(2, $z);
        $lon_deg = $x / $n * 360.0 - 180.0;
        $lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $y / $n))));
        return [$lon_deg, $lat_deg];
    }
}
