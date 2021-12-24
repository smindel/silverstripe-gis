<?php

namespace Smindel\GIS\Control;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Control\Controller;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;
use Smindel\GIS\Interfaces\HeaderAltererInterface;

class GeoJsonService extends AbstractGISWebServiceController
{
    private static $url_handlers = [
        '$Model' => 'handleAction',
    ];

    /**
     * @var HeaderAltererInterface
     */
    private $headerAlterer;


    public function __construct()
    {
        parent::__construct();
        $this->headerAlterer = Injector::inst()->get('Smindel\GIS\Helper\HeaderAlterer');
    }

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

        $propertyMap = $config['property_map'];


        $this->headerAlterer->setHeader('Access-Control-Allow-Origin: ' . $config['access_control_allow_origin']);

        // The HTTP kernel keeps a copy of the response body, which
        // can exhaust the memory limit for large data sets. So we
        // opt out and flush the buffer after processing each feature.
        if (!($is_test = headers_sent())) {
            $this->headerAlterer->setHeader('Content-Type: application/geo+json');
        }
        echo '{"type":"FeatureCollection","features":[';

        foreach ($list as $item) {
            if (!$item->canView()) {
                continue;
            }

            echo isset($geo) ? ',' : '';

            $geo = GIS::create($item->{$config['geometry_field']})->reproject(4326);

            $properties = [];
            foreach ($propertyMap as $fieldName => $propertyName) {
                $properties[$propertyName] = $item->$fieldName;
            }

            $feature = [
                'type' => 'Feature',
                'geometry' => ['type' => $geo->type],
                'properties' => $properties,
            ];
            if ($geo->type == GIS::TYPES['geometrycollection']) {
                $geometries = [];
                foreach ($geo->geometries as $geometry) {
                    $geometries[] = [
                        'type' => $geometry->type,
                        'coordinates' => $geometry->coordinates,
                    ];
                }
                $feature['geometry']['geometries'] =  $geometries;
            } else {
                $feature['geometry']['coordinates'] =  $geo->coordinates;
            }
            echo json_encode($feature);
        }
        echo ']}';
        if (!$is_test) {
            die;
        }
    }
}
