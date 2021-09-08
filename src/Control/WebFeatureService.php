<?php

namespace Smindel\GIS\Control;

use Exception;
use DOMDocument;
use Smindel\GIS\GIS;
use ReflectionClass;
use SimpleXMLElement;
use SilverStripe\Control\Director;

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

    public function getRequestParams($request)
    {
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
    }

    public function index($request)
    {
        $operation = $request->requestVars()['request'] ?? (new SimpleXMLElement($raw = $request->getBody()))->getName();

        if (!in_array(strtolower($operation), ['getcapabilities', 'describefeaturetype', 'getfeature'])) {
            throw new Exception(sprintf('Unkown operation "%s" requested', $operation));
        }

        return $this->$operation($request);
    }

    public function GetCapabilities($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $propertyMap = $config['property_map'];

        $url = Director::absoluteURL($request->requestVar('url'));

        list($nsName, $nsUri) = explode('=', $config['ns']);

        $xml = FluidXmlWriter::create('1.0','UTF-8')
            ->make('WFS_Capabilities', 'http://www.opengis.net/wfs/2.0')
                ->attribute('version', '2.0.0')
                ->attribute('xmlns:wfs', 'http://www.opengis.net/wfs/2.0')
                ->attribute('xmlns:ows', 'http://www.opengis.net/ows/1.1')
                ->attribute('xmlns:ogc', 'http://www.opengis.net/ogc')
                ->attribute('xmlns:fes', 'http://www.opengis.net/fes/2.0')
                ->attribute('xmlns:gml', 'http://www.opengis.net/gml')
                ->attribute('xmlns:xlink', 'http://www.w3.org/1999/xlink')
                ->attribute('xmlns:xsi', 'http://www.w3.org/2001/XMLSchema-instance')
                ->attribute('xsi:schemaLocation', 'http://www.opengis.net/wfs/2.0 http://schemas.opengis.net/wfs/2.0/wfs.xsd')
            ->make('ows:ServiceIdentification')
            ->make('ows:Title')->value('WFS 2.0.0 CITE Setup')->pop()
            ->make('ows:Abstract')->pop()
            ->make('ows:ServiceType')->attribute('codeSpace', 'http://www.opengeospatial.org/')->value('WFS')->pop()
            ->make('ows:ServiceTypeVersion')->value('2.0.0')->pop(2)
            ->make('ows:OperationsMetadata')
            ->make('ows:Operation')->attribute('name', 'GetCapabilities')
            ->make('ows:DCP')
            ->make('ows:HTTP')
            ->make('ows:Get')->attribute('xlink:href', $url . '?')->pop()
            ->make('ows:Post')->attribute('xlink:href', $url)->pop(4)
            ->make('ows:Operation')->attribute('name', 'DescribeFeatureType')
            ->make('ows:DCP')
            ->make('ows:HTTP')
            ->make('ows:Get')->attribute('xlink:href', $url . '?')->pop()
            ->make('ows:Post')->attribute('xlink:href', $url)->pop(4)
            ->make('ows:Operation')->attribute('name', 'GetFeature')
            ->make('ows:DCP')
            ->make('ows:HTTP')
            ->make('ows:Get')->attribute('xlink:href', $url . '?')->pop()
            ->make('ows:Post')->attribute('xlink:href', $url)->pop(5)
            ->make('FeatureTypeList')
            ->make('FeatureType')
            ->make('Name')->value($model)->pop()
            ->make('Title')->value($model::config()->get('singular_name'))->pop()
            ->make('DefaultCRS')->value('urn:ogc:def:crs:EPSG::4326')->pop()
            ;

        $this->getResponse()->addHeader('content-type', 'text/xml; charset=utf-8');

        return $xml->toXML();
    }

    public function DescribeFeatureType($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $propertyMap = $config['property_map'];

        list($nsName, $nsUri) = explode('=', $config['ns']);

        $xml = FluidXmlWriter::create('1.0','UTF-8')
            ->make('schema', 'http://www.w3.org/2001/XMLSchema')
                ->attribute('elementFormDefault', 'qualified')
                ->attribute('targetNamespace', $nsUri)
                ->attribute('xmlns:gml', 'http://www.opengis.net/gml/3.2')
                ->attribute('xmlns:' . $nsName, $nsUri)
            ->make('complexType')
                ->attribute('name', $nsName . ':' . $config['feature_type_name'])
            ->make('complexContent')
            ->make('extension')
                ->attribute('base', 'gml:AbstractFeatureType')
            ->make('sequence');

        foreach ($propertyMap as $fieldName => $propertyName) {
            $xml->make('element')
                ->attribute('name', $propertyName)
                ->attribute('type', $this->config()->type_map[$model::config()->db[$fieldName]])
                ->pop();
        }

        $xml->make('element')
            ->attribute('name', $config['geometry_field'])
            ->attribute('type', 'gml:GeometryPropertyType');

        $this->getResponse()->addHeader('content-type', 'text/xml; charset=utf-8');

        return $xml->toXML();
    }

    public function GetFeature($request)
    {
        $model = $this->getModel($request);
        $config = $this->getConfig($model);
        $list = $this->getRecords($request);
        $propertyMap = $config['property_map'];
        $geometry_field = $config['geometry_field'];

        list($nsName, $nsUri) = explode('=', $config['ns']);

        $xml = FluidXmlWriter::create('1.0','UTF-8')
            ->make('FeatureCollection')
                ->attribute('xmlns:gml', 'http://www.opengis.net/gml')
                ->attribute('xmlns:' . $nsName, $nsUri);

        foreach($list as $item) {
            if (!$item->canView()) {
                continue;
            }

            $xml->make('gml:featureMember');

            $xml->make($nsName . ':' . $config['feature_type_name'])
                ->attribute('gml:id', $item->ID);

            foreach ($propertyMap as $fieldName => $propertyName) {
                $xml->make($propertyName)->value($item->$fieldName)->pop();
            }

            $xml->make($nsName . ':' . $config['geometry_field']);

            $this->createGeometry($xml, $item->$geometry_field);

            $xml->pop(3);
        }

        $this->getResponse()->addHeader('content-type', 'text/xml; charset=utf-8');

        return $xml->toXML();
    }

    function createGeometry(FluidXmlWriter $xml, $value)
    {
        return call_user_func([$this, 'create' . ($gis = GIS::create($value))->type . 'Geometry'], $xml, $gis);
    }

    function createPointGeometry(FluidXmlWriter $xml, GIS $gis)
    {
        $xml->make('gml:Point')->attribute('srsName', 'urn:ogc:def:crs:EPSG::4326')
            ->make('gml:pos')->value(implode(' ', $gis->coordinates))
            ->pop(2);
    }

    function createLineStringGeometry(FluidXmlWriter $xml, GIS $gis)
    {
        $xml->make('gml:LineString')->attribute('srsName', 'urn:ogc:def:crs:EPSG::' . $gis->srid)
            ->make('gml:posList')->value(implode(' ', array_map(function($point){return implode(' ', $point);}, $gis->coordinates)))
            ->pop(2);
    }

    function createPolygonGeometry(FluidXmlWriter $xml, GIS $gis)
    {
        $xml->make('gml:Polygon')->attribute('srsName', 'urn:ogc:def:crs:EPSG::' . $gis->srid);

        foreach ($gis->coordinates as $i => $ring) {
            $xml->make('gml:' . ['exterior', 'interior'][$i])
                ->make('gml:LinearRing')
                ->make('gml:posList')->value(implode(' ', array_map(function($point){return implode(' ', $point);}, $ring)))
                ->pop(3);
        }

        $xml->pop();
    }
}
