<?php

namespace Smindel\GIS\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use Smindel\GIS\GIS;
use Smindel\GIS\Service\Tile;

class WebMapTileService extends AbstractGISWebServiceController
{
    private static $url_handlers = array(
        '$Model//$z/$x/$y' => 'handleAction',
    );

    /**
     * Buffer in pixel by wich the tile box is enlarged, which is used for
     * filtering the data. That's in orderer to include points from a bordering
     * tile that was cut and that wouldn't be rendered in this tile in order to
     * show a complete point. Use int as a uniform buffer around the current tile
     * or a two values for left/right and top/bottom or four values for left, top,
     * right and bottom.
     *
     * @var mixed
     */
    private static $tile_buffer = 5;

    private static $tile_size = 256;

    private static $wrap_date = true;

    private static $cache_path = 'tile-cache';

    private static $cache_ttl = 0;

    private static $default_style = [
        'gd' => [
            'backgroundcolor' => [0, 0, 0, 127],
            'strokecolor' => [60, 60, 210, 0],
            'fillcolor' => [60, 60, 210, 80],
            'setthickness' => [2],
            'pointradius' => 5,
        ],
        'imagick' => [
            'StrokeOpacity' => 1,
            'StrokeWidth' => 2,
            'StrokeColor' => 'rgb(60,60,210)',
            'FillColor' => 'rgba(60,60,210,.25)',
            'PointRadius' => 5,
        ],
    ];

    public function index($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);

        if (
            ($cache = $config['cache_ttl'] ? sha1(json_encode($request->getVars())) : false)
            && ($age = $this->cacheAge($cache)) !== false
            && $config['cache_ttl'] > $age
        ) {
            $response = $this->getResponse();
            $response->addHeader('Content-Type', 'image/png');
            $response->setBody($this->readCache($cache));
            return $response;
        }

        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');

        $tileSize = $config['tile_size'];
        $tile = Tile::create($z, $x, $y, $config['default_style'], $config['wrap_date'], $tileSize);

        list($lon1, $lat1) = Tile::zxy2lonlat($z, $x, $y);
        list($lon2, $lat2) = Tile::zxy2lonlat($z, $x + 1, $y + 1);

        $geometryField = $config['geometry_field'];

        $bufferSize = $config['tile_buffer'];
        if (!is_array($bufferSize)) {
            $bufferSize = array_fill(0, 4, $bufferSize);
        } else if (count($bufferSize) == 2) {
            $bufferSize += $bufferSize;
        }

        $buffer = [
            ($lon2 - $lon1) / $tileSize * $bufferSize[0],
            ($lat2 - $lat1) / $tileSize * $bufferSize[1],
            ($lon2 - $lon1) / $tileSize * $bufferSize[2],
            ($lat2 - $lat1) / $tileSize * $bufferSize[3],
        ];

        $boxes = [[
            [$lon1 - $buffer[0], $lat1 - $buffer[1]],
            [$lon2 + $buffer[2], $lat1 - $buffer[1]],
            [$lon2 + $buffer[2], $lat2 + $buffer[3]],
            [$lon1 - $buffer[0], $lat2 + $buffer[3]],
            [$lon1 - $buffer[0], $lat1 - $buffer[1]],
        ]];

        $bounds = [
            'type' => 'Polygon',
            'srid' => 4326,
            'coordinates' => $boxes,
        ];

        $list = $list->filter(
            $geometryField . ':ST_Intersects',
            GIS::array_to_ewkt(
                GIS::reproject_array(
                    $bounds,
                    Config::inst()->get(GIS::class, 'default_srid')
                )
            )
        );

        if ($request->requestVar('debug')) {
            $tile->debug("$z, $x, $y, " . $list->count());
        }

        $response = $this->getResponse();
        $response->addHeader('Content-Type', $tile->getContentType());
        $response->setBody($tile->render($list));

        if ($cache) {
            $this->writeCache($cache, $response->getBody());
        }

        return $response;
    }

    protected function cacheFile($cache)
    {
        $dir = $this->config()->cache_path . DIRECTORY_SEPARATOR;
        $dir = $dir[0] != DIRECTORY_SEPARATOR
            ? TEMP_PATH . '/../' . $dir
            : $dir;

        if (!file_exists($dir)) {
            mkdir($dir, fileperms(TEMP_PATH), true);
        }

        return $dir . $cache;
    }

    protected function cacheAge($cache)
    {
        return is_readable($file = $this->cacheFile($cache))
            ? time() - filemtime($file)
            : false;
    }

    protected function readCache($cache)
    {
        return file_get_contents($this->cacheFile($cache));
    }

    protected function writeCache($cache, $data)
    {
        file_put_contents($this->cacheFile($cache), $data);
    }
}
