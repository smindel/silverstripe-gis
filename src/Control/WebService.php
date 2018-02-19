<?php

namespace Smindel\GIS\Control;

use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class WebService extends Controller
{
    private static $url_handlers = array(
        '$Model' => 'handleAction',
    );

    public function index($request)
    {
        $model = str_replace('-', '\\', $request->param('Model'));
        $formater = 'format_' . $request->getExtension() ?: 'GeoJson';
        if (!Config::inst()->get($model, 'web_service') || !$formater) return $this->httpError(404);
        return $this->$formater($model::get(), $request->requestVars());
    }

    public static function format_geojson($list, $query)
    {
        $collection = [];

        $modelClass = $list->dataClass();

        $geometryField = array_search('Geography', Config::inst()->get($modelClass, 'db'));

        $propertyMap = Config::inst()->get($modelClass, 'web_service');
        if ($propertyMap == true) $propertyMap = ['ID' => 'ID', 'Title' => 'Title'] + Config::inst()->get($modelClass, 'summary_fields');

        foreach ($list as $item) {

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
                    $properties[$propertyName] = $item->relField($fieldName);
                }
            }

            $array = DBGeography::to_array($geometry);

            if (!$array['type'] || !$array['coordinates']) continue;

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
}
