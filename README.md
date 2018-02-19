# SilverStripe GIS Module

[![Build Status](https://travis-ci.org/smindel/silverstripe-gis.svg?branch=master)](https://travis-ci.org/smindel/silverstripe-gis)

Adds support for geographic types.

## Features

- adds new data type Geography to DataObjects
- POINT, LINESTRING or POLYGON types
- WGS 84 / EPSG:4326 support
- MapField to edit a DataObject's geography in a form
- map widgets for MapField and GridFieldMap are powered by Leaflet
- DataList filters ST_Within(Geography) and ST_DWithin(Geography,distance)
- GeoJson web service
- helper to calculate distances

## WIP

- GridFieldMap component to display a DataList as features on a map
- WFS
- alternative reference systems and re-projection
- full support for LINESTRING and POLYGON
- polygon editor field

## Requirements

- MySQL 5.7+ or Postgres with PostGIS extension

## Installation

MySQL natively supports geodetic coordinate systems for geometries since version 5.7. When using Postgres you have to install PostGIS:

1. `sudo apt-get install postgis`
2. `sudo -u postgres psql SS_gis -c "create extension postgis;"`

After installing PostGIS or if you are using MySQL5.7+ you can install the module like this:

1. `composer require smindel/silverstripe-gis dev-master`
2. http://mysite/dev/build?flush=all

## Using the module

### Adding Geography attributes to DataObjects

Add Geography attributes like any other attribute using the new type Geography:


`
class City extends DataObject
{
    private static $db = [
        'Name' => 'Varchar',
        'Location' => 'Geography',
    ];
}
`

### Transforming Geographies from PHP to WKT

Internally Geographies are represented as Well Known Text (WKT, https://en.wikipedia.org/wiki/Well-known_text#Geometric_objects). You can use the helper DBGeography::fromArray() to create WKT from PHP arrays:

- `DBGeography::fromArray([10,30])` creates "POINT (30 10)"
- `DBGeography::fromArray([[10,30],[30,10],[40,40]])` creates "LINESTRING (30 10, 10 30, 40 40)"
- `DBGeography::fromArray([[[10,30],[40,40],[40,20],[20,10],[10,30]]])` creates "POLYGON ((30 10, 40 40, 20 40, 10 20, 30 10))"

### Spacial queries

#### Query Within

To find all DataObjects within a polygon:

`$cities = City::get()->('Location:Whithin', DBGeography::fromArray([[[10,30],[40,40],[40,20],[20,10],[10,30]]]));`

#### Query Whithin Distance

To find all DataObjects within a 100000m of a point:

`$cities = City::get()->('Location:DWhithin', [DBGeography::fromArray([10,30]), 100000]);`

#### Compute Distance

To compute the distance in meters between two points:

`$distance = DBGeography::distance(DBGeography::fromArray([10,30]), DBGeography::fromArray([40,40]));`

### Geographies and forms

The module comes with a new form field type, the MapField. For a point it renders a point picker widget. For other Geography types the field is readonly.

A GridField component for displaying and filtering Geographies is under construction.

### Web Services



Filterable web services, specifically WFS and GeoJson are under construction.
