<?php

namespace Smindel\GIS\Control;

use DOMDocument;
use Smindel\GIS\GIS;
use ReflectionClass;
use SimpleXMLElement;

class WebFeatureService extends AbstractGISWebServiceController
{
    private static $url_handlers = [
        '$Model' => 'handleAction',
    ];

    private static $type_map = [
        'Varchar' => 'string',
        'Float' => 'decimal',       // key tbc
        'Datetime' => 'dateTime',   // key tbc
        'Int' => 'integer',         // key tbc
    ];

    private static $ns = 'ssgis=https://github.com/smindel/silverstripe-gis';

    public function getConfig($model)
    {
        $modelConfig = parent::getConfig($model);
        if (!$modelConfig) {
            return false;
        }
        $defaults = [
            'property_map' => singleton($model)->summaryFields(),
            'feature_type_name' => (new ReflectionClass($model))->getShortName(),
        ];
        return is_array($modelConfig) ? array_merge($defaults, $modelConfig) : $defaults;
    }

    public function index($request)
    {
        $operation = $request->requestVars()['request'] ??
            (new SimpleXMLElement($raw = $request->getBody()))->getName();

        if (!in_array($operation, ['GetCapabilities', 'DescribeFeatureType', 'GetFeature'])) {
            throw new Exception(sprintf('Unkown operation "%s" requested', $operation));
        }

        return $this->$operation($request);
    }

    public function DescribeFeatureType($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $propertyMap = $config['property_map'];

        if (count(array_intersect_key($params = array_intersect_key($request->requestVars(), array_fill_keys([
            'service',
            'version',
            'request',
            'typeNames',
            'exceptions',
            'outputFormat',
        ], null)), array_fill_keys([
            'service',
            'version',
            'request',
            'typeNames',
        ], null))) == 4) {
            extract($params);
        } else {
            $xml = new SimpleXMLElement($raw = $request->getBody());
            $service = (string)$xml['service'];
            $version = (string)$xml['version'];
            $request = $xml->getName();
            $typeNames = [];
            foreach ($xml->xpath('TypeName') as $typeName) {
                $typeNames[] = (string)$typeName;
            }
        }

        list($nsName, $nsUri) = explode('=', $config['ns']);

        $dom = new DOMDocument('1.0', 'UTF-8');
        $schema = $dom->createElementNS('http://www.w3.org/2001/XMLSchema', 'schema');
        $schema->setAttribute('elementFormDefault', 'qualified');
        $schema->setAttribute('targetNamespace', $nsUri);
        $schema->setAttribute('xmlns:gml', 'http://www.opengis.net/gml/3.2');
        $schema->setAttribute('xmlns:' . $nsName, $nsUri);

        $complexType = $dom->createElement('complexType');
        $complexType->setAttribute('name', $nsName . ':' . $config['feature_type_name']);

        $complexContent = $dom->createElement('complexContent');

        $extension = $dom->createElement('extension');
        $extension->setAttribute('base', 'gml:AbstractFeatureType');

        $sequence = $dom->createElement('sequence');

        foreach ($propertyMap as $fieldName => $propertyName) {
            $element = $dom->createElement('element');
            $element->setAttribute('name', $propertyName);
            $element->setAttribute('type', $this->config()->type_map[$model::config()->db[$fieldName]]);
            $sequence->appendChild($element);
        }

        $element = $dom->createElement('element');
        $element->setAttribute('name', $config['geometry_field']);
        $element->setAttribute('type', 'gml:GeometryPropertyType');
        $sequence->appendChild($element);

        $extension->appendChild($sequence);

        $complexContent->appendChild($extension);

        $complexType->appendChild($complexContent);

        $schema->appendChild($complexType);

        $dom->appendChild($schema);

        $response = $this->getResponse();
        $response->addHeader('content-type', 'text/xml; charset=utf-8');
        $response->setBody($dom->saveXML());

        return $response;
    }

    public function GetFeature($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);
        $propertyMap = $config['property_map'];

        if (count(array_intersect_key($params = array_intersect_key($request->requestVars(), array_fill_keys([
            'service',
            'version',
            'request',
            'typeNames',
        ], null)), array_fill_keys([
            'service',
            'version',
            'request',
            'typeNames',
        ], null))) == 4) {
            extract($params);
        } else {
            $xml = new SimpleXMLElement($raw = $request->getBody());
            $service = (string)$xml['service'];
            $version = (string)$xml['version'];
            $request = $xml->getName();
            $typeNames = [];
            foreach ($xml->xpath('TypeName') as $typeName) {
                $typeNames[] = (string)$typeName;
            }
        }

        list($nsName, $nsUri) = explode('=', $config['ns']);

        $dom = new DOMDocument('1.0', 'UTF-8');

        $featureCollection = $dom->createElement('FeatureCollection');
        $featureCollection->setAttribute('xmlns:gml', 'http://www.opengis.net/gml');
        $featureCollection->setAttribute('xmlns:' . $nsName, $nsUri);

        foreach ($list as $item) {
            if (!$item->canView()) {
                continue;
            }

            $member = $dom->createElement('gml:featureMember');

            $record = $dom->createElement($nsName . ':' . $config['feature_type_name']);
            $record->setAttribute('gml:id', $item->ID);

            foreach ($propertyMap as $fieldName => $propertyName) {
                $property = $dom->createElement($propertyName, $item->$fieldName);
                $record->appendChild($property);
            }

            $Geometry = $dom->createElement($nsName . ':' . $config['geometry_field']);

            $geometry_field = $config['geometry_field'];
            $shape = $this->createGeometry($dom, $item->$geometry_field);

            $Geometry->appendChild($shape);

            $record->appendChild($Geometry);

            $member->appendChild($record);

            $featureCollection->appendChild($member);
        }

        $dom->appendChild($featureCollection);

        $response = $this->getResponse();
        $response->addHeader('content-type', 'text/xml; charset=utf-8');
        $response->setBody($dom->saveXML());

        return $response;
    }

    public function createGeometry(DOMDocument $dom, $value)
    {
        return call_user_func([$this, 'create' . ($gis = GIS::create($value))->type . 'Geometry'], $dom, $gis);
    }

    public function createPointGeometry(DOMDocument $dom, GIS $gis)
    {
        $point = $dom->createElement('gml:Point');
        $point->setAttribute('srsName', 'urn:ogc:def:crs:EPSG::4326');

        $pos = $dom->createElement('gml:pos', implode(' ', $gis->coordinates));
        $point->appendChild($pos);

        return $point;
    }

    public function createLineStringGeometry(DOMDocument $dom, GIS $gis)
    {
        $line = $dom->createElement('gml:LineString');
        $line->setAttribute('srsName', 'urn:ogc:def:crs:EPSG::' . $gis->srid);

        $posList = $dom->createElement('gml:posList', implode(' ', array_map(function ($point) {
            return implode(' ', $point);
        }, $gis->coordinates)));

        $line->appendChild($posList);

        return $line;
    }

    public function createPolygonGeometry(DOMDocument $dom, GIS $gis)
    {
        $polygon = $dom->createElement('gml:Polygon');
        $polygon->setAttribute('srsName', 'urn:ogc:def:crs:EPSG::' . $gis->srid);

        foreach ($gis->coordinates as $i => $ring) {
            $exinterior = $dom->createElement('gml:' . ['exterior', 'interior'][$i]);

            $linearRing = $dom->createElement('gml:LinearRing');

            $posList = $dom->createElement('gml:posList', implode(' ', array_map(function ($point) {
                return implode(' ', $point);
            }, $ring)));

            $linearRing->appendChild($posList);

            $exinterior->appendChild($linearRing);

            $polygon->appendChild($exinterior);
        }

        return $polygon;
    }
}
