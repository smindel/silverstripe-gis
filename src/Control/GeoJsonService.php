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

    public function getConfig($model)
    {
        $modelConfig = parent::getConfig($model);
        if (!$modelConfig) {
            return false;
        }
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

        $geometryField = $config['geometry_field'];

        $propertyMap = $config['property_map'];

        // The HTTP kernel keeps a copy of the response body, which
        // can exhaust the memory limit for large data sets. So we
        // opt out and flush the buffer after processing each feature.
        if (!($is_test = headers_sent())) header('Content-Type: application/geo+json');
        echo '{"type":"FeatureCollection","features":[';

        foreach ($list as $item) {
            if (!$item->canView()) {
                continue;
            }

            echo isset($geometry) ? ',' : '';

            $geometry = $item->{$config['geometry_field']};

            $properties = [];
            foreach ($propertyMap as $fieldName => $propertyName) {
                $properties[$propertyName] = $item->$fieldName;
            }

            $array = GIS::reproject_array(GIS::ewkt_to_array($geometry), 4326);

            echo json_encode([
                'type' => 'Feature',
                'geometry' => [
                    'type' => $array['type'],
                    'coordinates' => $array['coordinates']
                ],
                'properties' => $properties,
            ]);
        }

        echo ']}';
        if (!$is_test) die;
    }
}
