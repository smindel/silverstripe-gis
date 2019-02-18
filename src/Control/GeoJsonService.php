<?php

namespace Smindel\GIS\Control;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;

class GeoJsonService extends AbstractGISWebServiceController
{
    private static $url_handlers = array(
        '$Model' => 'handleAction',
    );

    public function index($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);

        $collection = [];

        $geometryField = $config['geometry_field'];

        $propertyMap = $config['property_map'];

        foreach ($list as $item) {

            if (!$item->canView()) {
                continue;
            }

            $geometry = $item->{$config['geometry_field']};

            $properties = [];
            foreach ($propertyMap as $fieldName => $propertyName) {
                $properties[$propertyName] = $item->$fieldName;
            }

            $array = GIS::reproject_array(GIS::ewkt_to_array($geometry), 4326);

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
