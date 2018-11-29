<?php

namespace Smindel\GIS\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class WebService extends Controller
{
    private static $url_handlers = array(
        '$Model//$z/$x/$y' => 'handleAction',
        // '$Model' => 'handleAction',
    );

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

        return json_encode($raw);
    }

    public static function format_png($list, $request)
    {
        $z = $request->param('z');
        $x = $request->param('x');
        $y = $request->param('y');
        list($lon1, $lat1) = self::xyz2lonlat($x, $y, $z);
        list($lon2, $lat2) = self::xyz2lonlat($x + 1, $y + 1, $z);
        $lond = $lon2 - $lon1;
        $latd = $lat2 - $lat1;

        // $points = $list->filter(
        //     'Location:IntersectsGeo',
        //     DBGeography::from_array([[
        //         [$lon1, $lat1],
        //         [$lon2, $lat1],
        //         [$lon2, $lat2],
        //         [$lon1, $lat2],
        //         [$lon1, $lat1]
        //     ]], 4326)
        // );

        $points = $list->filter(
            'Location:IntersectsGeo',
            DBGeography::from_array(DBGeography::to_srid(
                [
                    'type' => 'Polygon',
                    'srid' => 4326,
                    'coordinates' => [[
                        [$lon1, $lat1],
                        [$lon2, $lat1],
                        [$lon2, $lat2],
                        [$lon1, $lat2],
                        [$lon1, $lat1]
                    ]],
                ],
                Config::inst()->get(DBGeography::class, 'default_projection')
            )['coordinates'])
        );

        $tile = imagecreate(256, 256);
        $background_color = imagecolorallocatealpha($tile, 0, 0, 0, 127);
        $text_color = imagecolorallocate($tile, 233, 14, 91);
        $point_color = imagecolorallocate($tile, 255, 0, 0);
        $area_color = imagecolorallocatealpha($tile, 255, 0, 0, 96);
        $boxpadding = 2;

        foreach ($points as $point) {
            $array = DBGeography::to_srid(DBGeography::to_array($point->Location), 4326);
            if ($array['type'] == 'Polygon') {
                foreach ($array['coordinates'] as $coords) {
                    $xy = [];
                    foreach ($coords as $coord) {
                        list($lon, $lat) = $coord;
                        $x = ($lon - $lon1) / $lond * 256;
                        $y = ($lat - $lat1) / $latd * 256;
                        $xy[] = $x;
                        $xy[] = $y;
                        imagefilledrectangle($tile, $x - $boxpadding, $y - $boxpadding, $x + $boxpadding, $y + $boxpadding, $point_color);
                    }
                    imagefilledpolygon($tile, $xy, count($xy) / 2, $area_color);
                    imagepolygon($tile, $xy, count($xy) / 2, $point_color);
                }
            } else {
                list($lon, $lat) = $array['coordinates'];
                $x = ($lon - $lon1) / $lond * 256;
                $y = ($lat - $lat1) / $latd * 256;
                imagefilledrectangle($tile, $x - $boxpadding, $y - $boxpadding, $x + $boxpadding, $y + $boxpadding, $point_color);
            }
        }

        ob_start();
        imagepng($tile);
        $binary = ob_get_clean();
        imagedestroy($tile);

        $response = Controller::curr()->getResponse();
        $response->addHeader('Content-Type', 'image/png');
        $response->setBody($binary);
        return $response;
    }

    public static function xyz2lonlat($x, $y, $z)
    {
        $n = pow(2, $z);
        $lon_deg = $x / $n * 360.0 - 180.0;
        $lat_rad = atan(sinh(M_PI * (1 - 2 * $y / $n)));
        $lat_deg = $lat_rad * 180.0 / M_PI;
        return [$lon_deg, $lat_deg];
    }
}
