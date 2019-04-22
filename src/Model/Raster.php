<?php

namespace Smindel\GIS\Model;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use Smindel\GIS\GIS;
use Exception;

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

    public function searchableFields()
    {
        return [
            'Band' => 'Band',
        ];
    }

    public function getFilename()
    {
        return $this->filename ?: $this->config()->full_path;
    }

    public function getSrid()
    {
        if (empty($this->info['srid'])) {
            $cmd = sprintf(
                '
                gdalsrsinfo -o wkt %1$s',
                $this->getFilename()
            );

            $output = $this->execute($cmd);

            if (preg_match('/\WAUTHORITY\["EPSG","([^"]+)"\]\]$/', $output, $matches)) {
                $this->info['srid'] = $matches[1];
            }
        }

        return $this->info['srid'];
    }

    public function getLocationInfo($geo = null, $band = null)
    {
        $cmd = sprintf(
            '
            gdallocationinfo -wgs84 %1$s %2$s %3$s',
            $band ? sprintf('-b %d', $band) : '',
            $this->getFilename(),
            $geo ? sprintf('%f %f', ...GIS::create($geo)->reproject(4326)->coordinates) : ''
        );

        $output = $this->execute($cmd);

        if (preg_match_all('/\sBand\s*(\d+):\s*Value:\s*([\d\.\-]+)/', $output, $matches)) {
            $bands = array_combine($matches[1], $matches[2]);
            array_walk($bands, function (&$item) {
                $item = (int)$item;
            });
            return $bands;
        }
    }

    public function translate($topLeftGeo, $bottomRightGeo, $width, $height, &$destFileName = '/dev/stdout', $ouputFormat = 'PNG')
    {
        $topLeftGeo = GIS::create($topLeftGeo)->coordinates;
        $bottomRightGeo = GIS::create($bottomRightGeo)->coordinates;

        $destFileName = $destFileName ?: TEMP_PATH . DIRECTORY_SEPARATOR . uniqid();

        $translations = [];
        $translations[] = '-scale';
        $translations[] = '-ot byte';
        $translations[] = '-a_nodata 0';

        $cmd = sprintf(
            '
            gdal_translate -q %s -of %s -projwin %f, %f, %f, %f -outsize %d %d %s %s',
            implode(' ', $translations),
            $ouputFormat,
            $topLeftGeo[0],
            $topLeftGeo[1],
            $bottomRightGeo[0],
            $bottomRightGeo[1],
            $width,
            $height,
            $this->getFilename(),
            $destFileName
        );

        return $this->execute($cmd);
    }

    public function hillshade(&$destFileName = '/dev/stdout', $ouputFormat = 'PNG')
    {
        $destFileName = $destFileName ?: TEMP_PATH . DIRECTORY_SEPARATOR . uniqid();

        $cmd = sprintf(
            '
            gdaldem hillshade %s %s -q -of %s -az %s -alt %s',
            $this->getFilename(),
            $destFileName,
            $ouputFormat,
            $az = 315,
            $alt = 45
        );

        return $this->execute($cmd);
    }

    public function color_relief(&$destFileName = '/dev/stdout', $ouputFormat = 'PNG')
    {
        $destFileName = $destFileName ?: TEMP_PATH . DIRECTORY_SEPARATOR . uniqid();

        $cmd = sprintf(
            '
            gdaldem color-relief %s %s %s -q -of %s -alpha',
            $this->getFilename(),
            $colorMapFile = __DIR__ . DIRECTORY_SEPARATOR  . 'color_relief.txt',
            $destFileName,
            $ouputFormat
        );

        return $this->execute($cmd);
    }

    protected function execute($command)
    {
        $process = proc_open($command, [['pipe', 'r'],['pipe', 'w'],['pipe', 'w']], $pipes);

        if ($process) {
            fclose($pipes[0]);

            $stdout = stream_get_contents($pipes[1]);
            fclose($pipes[1]);

            $stderr = stream_get_contents($pipes[2]);
            fclose($pipes[2]);

            $returnCode = proc_close($process);
        }

        if ($stderr) {
            throw new Exception($stderr);
        }

        return $stdout;
    }
}
