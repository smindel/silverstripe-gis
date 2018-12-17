<?php

namespace Smindel\GIS\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use SilverStripe\Security\Security;
use SilverStripe\Security\Permission;
use Smindel\GIS\ORM\FieldType\DBGeography;
use Smindel\GIS\Service\Tile;

class WebMapTileService extends Controller
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

    public function index($request)
    {
        $model = str_replace('-', '\\', $request->param('Model'));
        $list = $model::get();

        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');

        $tileSize = $model::config()->get('webmaptile_service')['tile_size'] ?? Tile::config()->get('tile_size');
        $tile = Tile::create($z, $x, $y, $tileSize);

        list($lon1, $lat1) = Tile::zxy2lonlat($z, $x, $y);
        list($lon2, $lat2) = Tile::zxy2lonlat($z, $x + 1, $y + 1);

        $geographyField = DBGeography::of($list->dataClass());

        $bufferSize = $model::config()->get('webmaptile_service')['tile_buffer'] ?? self::config()->get('tile_buffer');
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
            $geographyField . ':IntersectsGeo',
            DBGeography::from_array(DBGeography::to_srid(
                $bounds,
                Config::inst()->get(DBGeography::class, 'default_projection')
            )['coordinates'])
        );
        $tile->debug("$z, $x, $y, " . $list->count());

        $response = $this->getResponse();
        $response->addHeader('Content-Type', $tile->getContentType());
        $response->setBody($tile->render($list));
        return $response;
    }
}
