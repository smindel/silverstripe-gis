<?php

namespace Smindel\GIS\Control;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class WebService extends Controller
{
    private static $url_handlers = array(
        '$Model//$z/$x/$y' => 'handleAction',
        // '$Model' => 'handleAction',
    );

    /**
     * Buffer in pixel by wich the tile box is enlarged which is used for
     * filtering the data. That's in orderer to include points from a bordering
     * tile that was cut and that wouldn't be rendered in this tile in order to
     * show a complete point.
     *
     * @param int
     */
    private static $tileBuffer = 5;

    public function index($request)
    {
        $model = str_replace('-', '\\', $request->param('Model'));
        $formater = 'format_' . $request->getExtension() ?: 'GeoJson';
        if (!Config::inst()->get($model, 'web_service') || !$formater) return $this->httpError(404);
        return $this->$formater($model::get(), $request);
    }

    public static function format_geojson($list, $request)
    {
        $collection = [];

        $modelClass = $list->dataClass();

        $geometryField = array_search('Geography', Config::inst()->get($modelClass, 'db'));

        $propertyMap = Config::inst()->get($modelClass, 'web_service');
        if ($propertyMap === true) $propertyMap = singleton($modelClass)->summaryFields();

        foreach ($list as $item) {

            if (!$item->canView()) {
                continue;
            }

            if ($item->hasMethod('getWebServiseGeometry')) {
                $geometry = $item->getWebServiseGeometry();
            } else {
                $geometry = $item->$geometryField;
            }

            if ($item->hasMethod('getWebServiseProperties')) {
                $properties = $item->getWebServiseProperties();
            } else {
                $properties = [];
                foreach ($propertyMap as $fieldName => $propertyName) {
                    $properties[$propertyName] = $item->$fieldName;
                }
            }

            $array = DBGeography::to_srid(DBGeography::to_array($geometry), 4326);

            $collection[] = [
                'type' => 'Feature',
                'geometry' => [
                    'type' => $array['type'],
                    'coordinates' => $array['coordinates']
                ],
                'properties' => $properties,
            ];
        }

        $raw = [
            'type' => 'FeatureCollection',
            'features' => $collection,
        ];

        $response = Controller::curr()->getResponse();
        $response->addHeader('Content-Type', 'application/geo+json');
        $response->setBody(json_encode($raw));
        return $response;
    }

    public static function format_png($list, $request)
    {
        $tileZ = $request->param('z');
        $tileX = $request->param('x');
        $tileY = $request->param('y');
        list($lon1, $lat1) = self::xyz2lonlat($tileX, $tileY, $tileZ);
        list($lon2, $lat2) = self::xyz2lonlat($tileX + 1, $tileY + 1, $tileZ);

        $topLeft = [
            (($lon1 + 180) / 360) * 256 * pow(2, $tileZ),
            (0.5 - log((1 + sin($lat1 * pi()/180)) / (1 - sin($lat1 * pi()/180))) / (4 * pi())) * 256 * pow(2, $tileZ),
        ];

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

        $generator = function ($tileWidth, $tileHeight) use ($filteredList, $geographyField, $topLeft, $tileZ) {
            foreach ($filteredList as $dataObject) {
                $array = DBGeography::to_srid(DBGeography::to_array($dataObject->$geographyField), 4326);
                $tileCoordinates = DBGeography::each(
                    $array,
                    function ($lonlat) use ($topLeft, $tileWidth, $tileHeight, $tileZ) {
                        return [
                            (($lonlat[0] + 180) / 360) * $tileWidth * pow(2, $tileZ) - $topLeft[0],
                            (0.5 - log((1 + sin($lonlat[1] * pi()/180)) / (1 - sin($lonlat[1] * pi()/180))) / (4 * pi())) * $tileHeight * pow(2, $tileZ) - $topLeft[1],
                        ];
                    }
                );
                yield $dataObject->customise([
                    '_type' => $array['type'],
                    '_tileCoordinates' => $tileCoordinates,
                ]);
            }
        };

        $renderer = Injector::inst()->get('TileRenderer');

        $response = Controller::curr()->getResponse();
        $response->addHeader('Content-Type', $renderer->getContentType());
        $response->setBody($renderer->render($generator));
        return $response;
    }

    public static function xyz2lonlat($x, $y, $z)
    {
        $n = pow(2, $z);
        $lon_deg = $x / $n * 360.0 - 180.0;
        $lat_deg = rad2deg(atan(sinh(pi() * (1 - 2 * $y / $n))));
        return [$lon_deg, $lat_deg];
    }
}
