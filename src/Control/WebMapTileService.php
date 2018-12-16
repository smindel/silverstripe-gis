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
     * Buffer in pixel by wich the tile box is enlarged which is used for
     * filtering the data. That's in orderer to include points from a bordering
     * tile that was cut and that wouldn't be rendered in this tile in order to
     * show a complete point.
     *
     * @param int
     */
    private static $tileBuffer = 256;

    public function index($request)
    {
        $model = str_replace('-', '\\', $request->param('Model'));
        $list = $model::get();

        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');

        $tile = Tile::create($z, $x, $y);

        list($lon1, $lat1) = Tile::zxy2lonlat($z, $x, $y);
        list($lon2, $lat2) = Tile::zxy2lonlat($z, $x + 1, $y + 1);

        $geographyField = DBGeography::of($list->dataClass());

        $buffer = [
            ($lon2 - $lon1) / 256 * self::config()->get('tileBuffer'),
            ($lat2 - $lat1) / 256 * self::config()->get('tileBuffer'),
        ];

        $bounds = [
            'type' => 'Polygon',
            'srid' => 4326,
            'coordinates' => [[
                [$lon1 - $buffer[0], $lat1 - $buffer[1]],
                [$lon2 + $buffer[0], $lat1 - $buffer[1]],
                [$lon2 + $buffer[0], $lat2 + $buffer[1]],
                [$lon1 - $buffer[0], $lat2 + $buffer[1]],
                [$lon1 - $buffer[0], $lat1 - $buffer[1]],
            ]],
        ];

        $filteredList = $list->filter(
            $geographyField . ':IntersectsGeo',
            DBGeography::from_array(DBGeography::to_srid(
                $bounds,
                Config::inst()->get(DBGeography::class, 'default_projection')
            )['coordinates'])
        );

        $response = $this->getResponse();
        $response->addHeader('Content-Type', $tile->getContentType());
        $response->setBody($tile->render($filteredList));
        return $response;
    }
}
