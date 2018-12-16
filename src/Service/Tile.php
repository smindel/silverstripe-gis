<?php

namespace Smindel\GIS\Service;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Smindel\GIS\ORM\FieldType\DBGeography;

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
    protected $tileWidth = 256;

    /**
     * Default tile height in pixel
     *
     * @var int
     */
    protected $tileHeight = 256;

    /**
     * Top left corner of the tile
     *
     * @var array
     */
    protected $topLeft;

    protected $resource;

    public function __construct($z, $x, $y)
    {
        // @todo: parameterise tile size, search for 256 accross the module
        $this->z = $z;
        $this->x = $x;
        $this->y = $y;

        list($lon, $lat) = Tile::zxy2lonlat($z, $x, $y);

        $this->topLeft = [
            (($lon + 180) / 360) * 256 * pow(2, $z),
            (0.5 - log((1 + sin($lat * pi()/180)) / (1 - sin($lat * pi()/180))) / (4 * pi())) * 256 * pow(2, $z),
        ];

        $this->resource = Injector::inst()->get('TileRenderer');
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

    public function getCoordinates($wkt)
    {
        $array = DBGeography::to_srid(DBGeography::to_array($wkt), 4326);
        return DBGeography::each(
            $array,
            function ($lonlat) {
                return [
                    (($lonlat[0] + 180) / 360) * $this->tileWidth * pow(2, $this->z) - $this->topLeft[0],
                    (0.5 - log((1 + sin($lonlat[1] * pi()/180)) / (1 - sin($lonlat[1] * pi()/180))) / (4 * pi())) * $this->tileHeight * pow(2, $this->z) - $this->topLeft[1],
                ];
            }
        );
    }

    public function render($list)
    {
        foreach ($list as $item) {
            if ($item->hasMethod('renderOnWebMapTile')) {
                $item->renderOnWebMapTile($this);
            } else {
                $property = DBGeography::of(get_class($item));
                $this->resource->{'draw' . DBGeography::get_type($item->$property) . 'ToTile'}($this->getCoordinates($item->$property));
            }
        }
        // var_dump(0);die;
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
