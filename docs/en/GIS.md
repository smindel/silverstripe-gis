# Smindel\GIS\GIS

Spatial utility class. This class helps with converting between the different formats and is the home for some static helper methods.

GIS data used in this module can be represented in one of five formats:

- Instance of the geo type Geometry
- Instance of the geo type Geography
- EWKT string (extended Well Known Text), e.g. "SRID=4326;POINT(173 -43)"
- Simple array (nested arrays of coordinate tuples), e.g. [173 -43], SRID defaults to the default, type can be inferred from structure
- Explicit arrays that define SRID, type and coordinates, e.g. ['srid' => 4326, 'type'='Point', 'coordinates' => [173 -43]]

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

## Methods

### public static function GIS::array_to_ewkt($array, $srid = null) : string

Transforms a geometry from array to EWKT representation

- __$array__ (array) geo as simple or explicit array
- __$srid__ (int) SRID to instead of default if geo was supplied as simple array

Returns the EWKT representation of the given array

### public static function GIS::distance($geo1, $geo2) : float

Returns the distance between geometries

- __$geo1__ (string) geo in EWKT
- __$geo1__ (string) geo in EWKT

Returns the distance between the supplied geos in the projections unit (e.g. m, km or degrees)

### public static function GIS::ewkt_to_array($ewkt) : array

Transforms a geometry from EWKT to explicit array representation

- __$ewkt__ (string) geo in EWKT

Returns the explicit array representation of the given EWKT

### public static function GIS::get_type($geometry) : string

Returns the shape type of a geometry

- __$geometry__ (mixed) geo in simple or explicit array representation or string representation

Returns the geo's shape type

### public static function GIS::of($dataObjectClass) : mixed

Returns the name of the geometry property of the given DataObject class, be that the one configured through the DataObject's $default_geo_field or the first one that is found in the list of db fields.

- __$dataObjectClass__ (string) DataObject class name

Returns the DataObject classes preferred geo field as a string or null if none can be found

### public static function GIS::reproject_array($array, $toSrid = 4326) : array

Re-projects a geometry

- __$array__ (array) geo as simple or explicit array
- __$toSrid__ (int) SRID to reproject to

Returns the reprojected array

### public static function GIS::split_ewkt($ewkt, $fallbackSrid = null) : array

Splits an $ewkt string into the [SRID](https://en.wikipedia.org/wiki/Spatial_reference_system#Identifier) and [WKT](https://en.wikipedia.org/wiki/Well-known_text_representation_of_geometry)

- __$ewkt__ (string) the EWKT to split, also excepts WKT
- __$fallbackSrid__ (int) SRID to overwrite the default in case a WKT is supplied

Returns an array with the WKT as the first and the SRID as the second value
