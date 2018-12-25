# SilverStripe GIS Module

[![Build Status](https://travis-ci.org/smindel/silverstripe-gis.svg?branch=master)](https://travis-ci.org/smindel/silverstripe-gis)
[![Version](http://img.shields.io/packagist/v/smindel/silverstripe-gis.svg?style=flat)](https://packagist.org/packages/smindel/silverstripe-gis)
[![License](http://img.shields.io/packagist/l/smindel/silverstripe-gis.svg?style=flat)](LICENSE.md)

Adds support for geographic types.

## Features

- adds new data type Geography to DataObjects
- POINT, LINESTRING, POLYGON and MULTIPOLYGON types
- built in support for WGS 84 / EPSG:4326
- supports alternative projections
- MapField to edit a DataObject's geography in a form (currently only supports Point, LineString and Polygon)
- GridFieldMap component to search for DataObjects on a map
- Map tile service
- GeoJson web service
- GeoJson importer
- DataList filters
    - Within(Geography)
    - DWithin(Geography,distance), not supported by MariaDB
    - Intersects(Geograpy)

## Requirements

- MySQL 5.7+ or Postgres with PostGIS extension
- SilverStripe framework 4

## Installation

    $ composer require smindel/silverstripe-gis dev-master`
    $ vendor/bin/sake dev/build flush=all

### MySQL

MySQL natively supports geodetic coordinate systems for geometries since version 5.7.6.

### MariaDB

MariaDB does not currently support ST_Distance_Sphere(), so that you cannot calculate distances.

### Postgres

When using Postgres you have to install PostGIS:

    sudo apt-get install postgis
    sudo -u postgres psql SS_gis -c "create extension postgis;"

If you get errors try:

    sudo apt-get install postgresql-9.5-postgis-scripts
    sudo apt-get install postgresql-9.5-postgis-2.2

## Configuration

### Alternative projections

By default the module uses WGS 84 aka LatLon (EPSG:4326). You can register other [projections in proj4 format](https://epsg.io/) change the default:

app/_config/config.yml:

    Smindel\GIS\ORM\FieldType\DBGeography:
      default_projection: 2193
      projections:
         2193: "+proj=tmerc +lat_0=0 +lon_0=173 +k=0.9996 +x_0=1600000 +y_0=10000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs"

or

app/_config.php:

    // defaults to 4326
    \Smindel\GIS\ORM\FieldType\DBGeography::config()->set('default_projection', 2193);
    // register New Zealand Transverse Mercator projection
    \Smindel\GIS\ORM\FieldType\DBGeography::config()->set('projections', [
        2193 => '+proj=tmerc +lat_0=0 +lon_0=173 +k=0.9996 +x_0=1600000 +y_0=10000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
    ]);

### Default map center

MapField and GridFieldMap will show the default_location if no data is available.

app/_config/config.yml:

    Smindel\GIS\ORM\FieldType\DBGeography:
      default_location: [5900755,1782733]
      
or

app/_config.php;

    // defaults to [10,53.5]
    \Smindel\GIS\ORM\FieldType\DBGeography::config()->get('default_location', [5900755,1782733]);

### TileRender

You can choose from two renderers for the map tile service, the default one requires the php module Imagick to be installed. If that module is not available you can use the GD libaray through the GDTileRenderer

app/_config/config.yml:

    SilverStripe\Core\Injector\Injector:
      TileRenderer:
        class: Smindel\GIS\Service\GDTileRenderer

or

app/_config.php;

    // defaults to [10,53.5]
    \Smindel\GIS\ORM\FieldType\DBGeography::config()->get('TileRenderer', \Smindel\GIS\Service\GDTileRenderer::class);

### Tile buffer

In order to complete rendering of features that actually fall within a neighboring tile. Sometimes fetures have to be rendered in more than one tile, e.g. a Point marker of a certain size for a point that is exactly on the border of a tile extends into the neighboring tile. In order to guarantee the rendering of a circle with a radius of 5 pixel as a marker you can set a tile buffer of 5 pixel. The size of the buffer has a negative impact on the performance of the renderer as it has to load and render more features.

app/_config/config.yml:

    Smindel\GIS\Service\WebService:
      tile_buffer: 10
      
or

app/_config.php;

    // defaults to 5
    \Smindel\GIS\Service\WebService::config()->get('tile_buffer', 10);

## Model setup

### Adding Geography attributes to DataObjects

Add Geography attributes like any other attribute using the new type Geography:

app/src/Model/City.php

    <?php
    
    class City extends DataObject
    {
        private static $db = [
            'Name' => 'Varchar',
            'Location' => 'Geography',
        ];
    }

### Activating the web services

The GeoJson and map tile service are deactivated by default. You can activate them using the config:

app/_config/config.yml:

    City:
      web_service: true

or

app/src/Model/City.php

    <?php
    
    class City extends DataObject
    {
        private static $db = [
            'Name' => 'Varchar',
            'Location' => 'Geography',
        ];

        private static $web_service = true;
    }

### Forms

#### MapField

If you are using the module in combination with ModelAdmin, you will notice the new form field type MapField. It allows editing of Points, LineStings and unnested Polygons. If you want to limit the the geometry types you can remove them from the field:

app/src/Model/City.php

    <?php
    
    class City extends DataObject
    {
        private static $db = [
            'Name' => 'Varchar',
            'Location' => 'Geography',
        ];
        
        public function getCMSFields()
        {
            $fields = parent::getCMSFields();
            $fields->dataFieldByName('Location')->setControl('polygon', false)->setControl('polyline', false);
            return $fields;
        }
    }

#### GridFieldMap

If you want to add a map to your GridField to visualise a DataList on a map, you can use the GridFieldMap component that comes with the module. E.g. you can build a GIS aware ModelAdmin like this:

app/src/Model/City.php

    <?php
    
    use SilverStripe\Admin\ModelAdmin;
    use Smindel\GISDemo\Model\City;
    use Smindel\GIS\Forms\GridFieldMap;
    use Smindel\GIS\ORM\FieldType\DBGeography;

    class GISAdmin extends ModelAdmin
    {
        private static $url_segment = 'gis';
        
        private static $menu_title = 'GIS';
        
        private static $managed_models = [
            City::class,
        ];
        
        public function getEditForm($id = NULL, $fields = NULL)
        {
            $form = parent::getEditForm($id, $fields);
        
            if (
                ($geography = DBGeography::of($this->modelClass))
                && ($field = $form->Fields()->dataFieldByName($this->sanitiseClassName($this->modelClass)))
            ) {
                $field->getConfig()->addComponent(new GridFieldMap($geography));
            }
            
            return $form;
        }
    }

### Utility methods

#### Transforming Geographies from PHP to WKT

Because PHP doesn't have native spacial types the module uses the extended Well Known Text format (eWKT, https://en.wikipedia.org/wiki/Well-known_text#Geometric_objects) as well as arrays.

You can use the helper DBGeography::from_array() to create eWKT from PHP arrays:

    // creates "SRID=0000;POINT (30 10)"
    $ewkt = DBGeography::from_array([10,30])
    
    // creates "SRID=0000;LINESTRING (30 10, 10 30, 40 40)"
    $ewkt = DBGeography::from_array([[10,30],[30,10],[40,40]])
    
    // creates "SRID=0000;POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"
    $ewkt = DBGeography::from_array([[[10,30],[40,40],[40,20],[20,10],[10,30]]])

Or you can retrieve arrays from eWKT like this:

    // returns array([10,30])
    $array = DBGeography::to_array("SRID=0000;POINT (30 10)")['coordinates']

#### Spacial queries

##### Within Query

To find all DataObjects within a polygon:

    $cities = City::get()->filter('Location:WithinGeo', DBGeography::from_array([[[10,30],[40,40],[40,20],[20,10],[10,30]]]));

##### Intersects Query

To find all DataObjects intersects with a polygon:

    $cities = City::get()->filter('Location:IntersectsGeo', DBGeography::from_array([[[10,30],[40,40],[40,20],[20,10],[10,30]]]));

##### Whithin Distance Query

To find all DataObjects within a 100000m of a point:

    $cities = City::get()->filter('Location:DWithinGeo', [DBGeography::from_array([10,30]), 100000]);

#### Compute Distance

To compute the distance in meters between two points:

    $distance = DBGeography::distance(DBGeography::from_array([10,30]), DBGeography::from_array([40,40]));

### GeoJson import

You can invoke the importer in the simplest form like this:

    Smindel\GIS\Service\GeoJsonImporter::import(
        self::class,
        file_get_contents(__DIR__ . '/City.geojson')
    );

Additional optional agruments are:

- an associative array mapping DataObject properties to the GeoJson properties
- the name of the geometry property of the DataObject, if omitted the first Geography is used
- a callable called with every feature of the GeoJson feature set
