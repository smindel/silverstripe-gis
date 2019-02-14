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

    public function index($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);

        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');

        $tileSize = $config['tile_size'];
        $tile = Tile::create($z, $x, $y, $config['wrap_date'], $tileSize);

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
        return $response;
    }
}
