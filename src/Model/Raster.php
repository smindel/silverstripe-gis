<?php

namespace Smindel\GIS\Model;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Smindel\GIS\GIS;

/*
gdaldem hillshade -of PNG public/assets/wellington-lidar-1m-dem-2013.4326.tif hillshade.png
*/

class Raster
{
    use Configurable;

    use Injectable;

    private static $tile_renderer = 'raster_renderer';

    protected $filename;
    protected $info;

    public function __construct($filename = null)
    {
        $this->filename = $filename;
    }

    public function getFilename()
    {
        return $this->filename ?: $this->config()->full_path;
    }

    public function getSrid()
    {
        if (empty($this->info['srid'])) {

            $cmd = sprintf('
                gdalsrsinfo -o wkt %1$s',
                $this->getFilename()
            );

            $output = `$cmd`;

            if (preg_match('/\WAUTHORITY\["EPSG","([^"]+)"\]\]$/', $output, $matches)) {
                $this->info['srid'] = $matches[1];
            }
        }

        return $this->info['srid'];
    }

    public function getLocationInfo($geo = null, $band = null)
    {
        $cmd = sprintf('
            gdallocationinfo -wgs84 %1$s %2$s %3$s',
            $band ? sprintf('-b %d', $band) : '',
            $this->getFilename(),
            $geo ? sprintf('%f %f', ...GIS::create($geo)->reproject(4326)->coordinates) : ''
        );

        $output = `$cmd`;

        if (preg_match_all('/\sBand\s*(\d+):\s*Value:\s*([\d\.\-]+)/', $output, $matches)) {
            $bands = array_combine($matches[1], $matches[2]);
            array_walk($bands, function(&$item) {
                $item = (int)$item;
            });
            return $bands;
        }
    }

    public function translateRaster($topLeftGeo, $bottomRightGeo, $width, $height, $destFileName = '/dev/stdout')
    {
        $topLeftGeo = GIS::create($topLeftGeo)->coordinates;
        $bottomRightGeo = GIS::create($bottomRightGeo)->coordinates;

        $cmd = sprintf('
            gdal_translate -of PNG -q -projwin %1$f, %2$f, %3$f, %4$f -outsize %5$d %6$d %7$s %8$s',
            $topLeftGeo[0], $topLeftGeo[1],
            $bottomRightGeo[0], $bottomRightGeo[1],
            $width, $height,
            $this->getFilename(),
            $destFileName
        );

        return `$cmd`;
    }

    public function searchableFields()
    {
        return [
            'Band' => 'Band',
        ];
    }
}
