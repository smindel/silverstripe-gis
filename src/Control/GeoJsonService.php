<?php

namespace Smindel\GIS\Control;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\ORM\FieldType\DBGeography;

class GeoJsonService extends AbstractGISWebServiceController
{
    private static $url_handlers = array(
        '$Model' => 'handleAction',
    );

    public function getConfig($model)
    {
        $modelConfig = parent::getConfig($model);
        if (!$modelConfig) return false;
        $defaults = [
            'property_map' => singleton($model)->summaryFields(),
        ];
        return is_array($modelConfig) ? array_merge($defaults, $modelConfig) : $defaults;
    }

    public function index($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);

        $collection = [];

        $geometryField = $config['geography_field'];

        $propertyMap = $config['property_map'];

        foreach ($list as $item) {

            if (!$item->canView()) {
                continue;
            }

            $geography = $item->{$config['geography_field']};

            $properties = [];
            foreach ($propertyMap as $fieldName => $propertyName) {
                $properties[$propertyName] = $item->$fieldName;
            }

            $array = DBGeography::to_srid(DBGeography::to_array($geography), 4326);

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
