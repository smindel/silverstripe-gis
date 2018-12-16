<?php

namespace Smindel\GIS\Control;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class GeoJsonService extends Controller
{
    private static $url_handlers = array(
        '$Model' => 'handleAction',
    );

    public function index($request)
    {
        $model = str_replace('-', '\\', $request->param('Model'));
        $config = Config::inst()->get($model, 'webmaptile_service');

        if (!$config) {
            return $this->httpError(404);
        }

        if (isset($config['code']) && !Permission::check($config['code'])) {
            return Security::permissionFailure($this);
        }

        $skip_filter = false;
        $list = is_callable([$model, 'get_webmaptile_webservice_list'])
            ? $model::get_geojson_webservice_list($request, $skip_filter)
            : $model::get();

        if (!$skip_filter) {
            // @todo: implement filter
        }

        $collection = [];

        $geometryField = array_search('Geography', Config::inst()->get($model, 'db'));

        $propertyMap = Config::inst()->get($model, 'geojson_service');
        if ($propertyMap === true) $propertyMap = singleton($model)->summaryFields();

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
}
