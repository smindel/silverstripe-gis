<?php

namespace Smindel\GIS\Model;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class Raster
{
    use Configurable;

    use Injectable;

    private static $tile_renderer = 'raster_renderer';

    protected $info;

    public function searchableFields()
    {
        return [
            'Band' => 'Band',
        ];
    }

    public function getFullPath()
    {
        return static::config()->full_path;
    }

    public function getSrid()
    {
        if (empty($this->info['srid'])) {

            $cmd = sprintf('
                gdalsrsinfo -o wkt %1$s',
                $this->getFullPath()
            );

            $output = `$cmd`;

            if (preg_match('/\WAUTHORITY\["EPSG","([^"]+)"\]\]$/', $output, $matches)) {
                $this->info['srid'] = $matches[1];
            }
        }

        return $this->info['srid'];
    }

    public function getValue($geo = null, $band = null)
    {
        $cmd = sprintf('
            gdallocationinfo -wgs84 %1$s %2$s %3$s',
            $band ? sprintf('-b %d', $band) : '',
            $this->getFullPath(),
            $geo ? sprintf('%f %f', ...GIS::reproject(GIS::to_array($geo), 4326)['coordinates']) : ''
        );

        $output = `$cmd`;

        if (preg_match_all('/\sBand\s*(\d+):\s*Value:\s*([\d\.\-]+)/', $output, $matches)) return array_combine($matches[1], $matches[2]);
    }
}
