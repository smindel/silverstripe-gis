<?php

namespace Smindel\GIS\Service;

use Smindel\GIS\ORM\FieldType\DBGeography;

class GeoJsonImporter
{
    public static function import($class, $geoJson, $propertyMap = null, $geometryProperty = null, $featureCallback = null)
    {
        $geometryProperty = $geometryProperty ?: DBGeography::of($class);
        $features = (is_array($geoJson) ? $geoJson : json_decode($geoJson, true))['features'];
        foreach ($features as $feature) {

            if (is_callable($featureCallback)) $feature = $featureCallback($feature);

            if ($feature['type'] != 'Feature') continue;

            if ($propertyMap === null) {
                $propertyMap = array_intersect(array_keys($class::config()->get('db')), array_keys($feature['properties']));
                $propertyMap = array_combine($propertyMap, $propertyMap);
            }

            $obj = $class::create();
            $obj->$geometryProperty = DBGeography::from_array($feature['geometry']['coordinates']);
            foreach ($propertyMap as $doProperty => $jsonProperty) {
                $obj->$doProperty = $feature['properties'][$jsonProperty];
            }
            $obj->write();
        }
    }
}
