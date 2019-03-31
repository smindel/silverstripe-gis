# Smindel\GIS\GIS

Spatial utility class. This class helps with converting between the different formats and is the home for some static helper methods.

## Configuration

### private static GIS::$default_srid = int

Default spacial reference sytem id

### private static GIS::$projections = array

[proj4 definitions](https://epsg.io/) for SRIDs 4326, 3857 and 2193 are preconfigured. Use key value pairs of SRID to proj4 definition:

```php
GIS::$projections = [
    2193 => '+proj=tmerc +lat_0=0 +lon_0=173 +k=0.9996 +x_0=1600000 +y_0=10000000 +ellps=GRS80 +towgs84=0,0,0,0,0,0,0 +units=m +no_defs',
];
```
- __key__ (int) is the SRID to be registered
- __value__ (string) is the corresponding projection definition in proj4 format

## Properties

### public GIS::array

GIS in explicit arrays form (see constructor)

### public GIS::ewkt

GIS in ewkt form

### public GIS::wkt

GIS in wkt form

### public GIS::srid

GIS'es srid

### public GIS::type

GIS'es type

### public GIS::coordinates

GIS'es coordinates

## Methods

### public function GIS::__construct($value) : GIS

Transforms a geometry from array to EWKT representation

- __$value__ (mixed) geo value in one of these forms:
    - Instance of the geo type Geometry
    - Instance of the geo type Geography
    - Instance of GIS
    - WKT string (Well Known Text), e.g. "POINT(173 -43)"
    - EWKT string (extended Well Known Text), e.g. "SRID=4326;POINT(173 -43)"
    - Coordinates array (nested arrays of coordinate tuples), e.g. [173 -43], SRID defaults to the default, type will be inferred from structure
    - Explicit arrays that define SRID, type and coordinates, e.g. ['srid' => 4326, 'type'='Point', 'coordinates' => [173 -43]]


Returns the EWKT representation of the given array

### public function GIS::distance($geo) : float

Returns the distance between geometries

- __$geo__ (mixed) geo in any of the supported forms

Returns the distance between the supplied geos in the projections unit (e.g. m, km or degrees)

### public static function GIS::of($dataObjectClass) : mixed

Returns the name of the geometry property of the given DataObject class, be that the one configured through the DataObject's $default_geo_field or the first one that is found in the list of db fields.

- __$dataObjectClass__ (string) DataObject class name

Returns the DataObject classes preferred geo field as a string or null if none can be found

### public static function GIS::reproject($toSrid = 4326) : GIS

Re-projects a geometry

- __$toSrid__ (int) SRID to reproject to

Returns the reprojected GIS
