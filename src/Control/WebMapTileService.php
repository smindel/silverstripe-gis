<?php

namespace Smindel\GIS\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use SilverStripe\ORM\DB;
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
        $model = $this->model = $this->getModel($request);
        $config = $this->getConfig($model);

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

        $renderer = Config::inst()->get($this->getModel($request), 'tile_renderer');
        $response = $this->$renderer($request);

        if ($cache && $response->getStatusCode() == 200) {
            $this->writeCache($cache, $response->getBody());
        }

        return $response;
    }

    public function vector_renderer($request)
    {
        $model = $this->model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);

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
        } elseif (count($bufferSize) == 2) {
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

        return $response;
    }

    public function raster_renderer($request)
    {
        $model = $this->model = $this->getModel($request);
        $config = $this->getConfig($model);
        $raster = singleton($model);

        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');

        list($lon1, $lat1) = Tile::zxy2lonlat($z, $x, $y);
        list($lon2, $lat2) = Tile::zxy2lonlat($z, $x + 1, $y + 1);

        list($x1, $y1) = ($srid = $raster->getSrid()) == 4326 ? [$lon1, $lat1] : GIS::reproject_array(['srid' => 4326, 'type' => 'Point', 'coordinates' => [$lon1, $lat1]], $srid)['coordinates'];
        list($x2, $y2) = ($srid = $raster->getSrid()) == 4326 ? [$lon2, $lat2] : GIS::reproject_array(['srid' => 4326, 'type' => 'Point', 'coordinates' => [$lon2, $lat2]], $srid)['coordinates'];

        $sfx = ($x2 - $x1) / $config['tile_size'];
        $sfy = ($y2 - $y1) / $config['tile_size'];

        DB::query("set bytea_output='escape'");

        $rasterDef = ($colormap = $raster->getColorMap())
            ? sprintf('ST_ColorMap(%1$s, \'%2$s\')', $raster->getRasterColumn(), $raster->getColorMap())
            : $raster->getRasterColumn();

        $sql = sprintf('
            SELECT
                ST_AsPNG(%3$s) pngbinary
            FROM
                ST_Retile(
                    \'%1$s\'::regclass,
                    \'%2$s\',
                    ST_MakeEnvelope(%4$f, %5$f, %6$f, %7$f, %8$d),
                    %9$f, %10$f,
                    %11$d, %11$d
                ) %2$s
            LIMIT 1;
            ',
            $raster->getTableName(),
            $raster->getRasterColumn(),
            $rasterDef,
            $x1, $y1, $x2, $y2,
            $srid,
            $sfx, $sfy,
            $config['tile_size']
        );

        $query = DB::query($sql);

        $raw = $query->value();
        $response = $this->getResponse();

        $dimensions = $raster->getDimensions();

        $padding[0] = ($dimensions[0] - $x1) / $sfx;
        $padding[1] = ($dimensions[1] - $y1) / $sfy;
        $padding[2] = ($x2 - $dimensions[2]) / $sfx;
        $padding[3] = ($y2 - $dimensions[3]) / $sfy;

        $response->addHeader('Content-Type', 'image/png');
        if (!$raw) {
            $response->setBody(base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABAQMAAAAl21bKAAAAA1BMVEUAAACnej3aAAAAAXRSTlMAQObYZgAAAApJREFUCNdjYAAAAAIAAeIhvDMAAAAASUVORK5CYII='));
        } else if (max(...$padding) > 0) {

            $imgout = imagecreatetruecolor($config['tile_size'], $config['tile_size']);
            imagecolortransparent($imgout, imagecolorallocatealpha($imgout, 0, 0, 0, 0));
            imagealphablending($imgout, true);

            imagecopyresampled(
                $imgout,
                imagecreatefromstring(pg_unescape_bytea($raw)),
                $padding[0] > 0 ? $padding[0] : 0,
                $padding[1] > 0 ? $padding[1] : 0,
                0,
                0,
                $config['tile_size'],
                $config['tile_size'],
                (max($x1, $x2) - min($x1, $x2)) / abs($sfx),
                (max($y1, $y2) - min($y1, $y2)) / abs($sfy)
            );

            ob_start();
            imagepng($imgout);

            $response->setBody(ob_get_clean());
        } else {
            $response->setBody(pg_unescape_bytea($raw));
        }

        DB::query("set bytea_output='hex'");

        return $response;
    }

    protected function cacheFile($cache)
    {
        $dir = $this->getConfig($this->model)['cache_path'] . DIRECTORY_SEPARATOR;
        $dir = $dir[0] != DIRECTORY_SEPARATOR
            ? TEMP_PATH . DIRECTORY_SEPARATOR . $dir
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
