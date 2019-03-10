# SilverStripe GIS Module

[![Build Status](https://travis-ci.org/smindel/silverstripe-gis.svg?branch=master)](https://travis-ci.org/smindel/silverstripe-gis)
[![Build Status](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/badges/build.png?b=master)](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/build-status/master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/?branch=master)
[![Code Intelligence Status](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/badges/code-intelligence.svg?b=master)](https://scrutinizer-ci.com/code-intelligence)
[![Code Coverage](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/smindel/silverstripe-gis/?branch=master)
[![Version](http://img.shields.io/packagist/v/smindel/silverstripe-gis.svg?style=flat)](https://packagist.org/packages/smindel/silverstripe-gis)
[![Total Downloads](https://poser.pugx.org/smindel/silverstripe-gis/downloads.svg)](https://packagist.org/packages/smindel/silverstripe-gis)
[![License](http://img.shields.io/packagist/l/smindel/silverstripe-gis.svg?style=flat)](LICENSE.md)

GIS developer toolkit for SilverStripe

![feature name](docs/images/MapField.png)

## Features

- __New field types:__ geo field types Geometry and Geography for DataObjects
- __New form fields:__ edit the new geo types using the MapField or add maps to GridFields in ModelAdmin
- __Configurable projections:__ support for multiple projections through proj4
- __Primitive and multipart geometries:__ Point, LineString, Polygon, MultiPoint, MultiLineString, MultiPolygon
- __Developer tools:__ heaps of useful helpers, e.g. for re-projecting, distance measuring, [EWKT](https://postgis.net/docs/manual-2.1/using_postgis_dbmanagement.html#EWKB_EWKT)
- __MySQL and Postgres:__ supports Postgres with PostGIS, MySQL 5.7+, partial support for MariaDB
- __ORM integration:__ DataList filters, e.g. to find intersecting DataObjects or within distance
- __GeoJSON imorter:__ import a GeoJSON source as DataObjects
- __GeoJSON web service:__ GeoJSON API for DataObjects
- __WMTS:__ render DataObjects to ZXY tiles e.g. for a leaflet frontend


## Requirements

- MySQL 5.7+ or Postgres with PostGIS extension
- SilverStripe framework 4


## Installation

It's recommended to use composer to install the module

    $ composer require smindel/silverstripe-gis
    $ vendor/bin/sake dev/build flush=all

__MySQL__ natively supports geodetic coordinate systems for geometries since version 5.7.6.

__MariaDB__ does not currently support ST\_Distance\_Sphere(), so that you cannot calculate distances.

When using __Postgres__ you have to install PostGIS. On Ubuntu and Debian run the following commands:

    $ sudo apt-get install postgis
    $ sudo apt-get install postgresql-9.5-postgis-scripts
    $ sudo apt-get install postgresql-9.5-postgis-2.2
    $ sudo -u postgres psql SS_gis -c "create extension postgis;"

(replace 'SS\_gis' with your db name)

Steps two and three may not be necessary, so you might want to try one and four first and if four fails, do two, three and four.

## Configuration

silverstripe-gis, like any other SilverStripe module, can be [configured](https://docs.silverstripe.org/en/4/developer_guides/configuration/configuration/) using YAML files, the Config class or private static properties of Configurables. Check out the following sections to see what can be configured.

## Examples and how tos:

- [Why would you bother?](docs/en/Why-bother.md) - And if you should, what should you bother about?
- [DataObject Example](docs/en/DataObject-Example.md) - How to set up your own DataObjects
- [GridFieldMap](docs/en/GridFieldMap.md#example) - How create a spatially aware admin interface
- [MapField](docs/en/MapField.md#examples) - How to edit geo types
- [ORM Filters](docs/en/ORM-Filters.md) - How to retrieve DataObjects from the db using spatial filters

## API:

- [GIS](docs/en/GIS.md) - Spatial utility class with all sorts of useful helpers
- [GeoJSONImporter](docs/en/GeoJSONImporter.md) - Import GeoJSON files into the db
- [GridFieldMap](docs/en/GridFieldMap.md) - GridField component to browse DataObjects by map
- [MapField](docs/en/MapField.md) - Form field to edit geo types
- [GeoJsonService](docs/en/GeoJsonService.md) - Expose your DataObjects dynamically in GeoJSON format though an API
- [WebMapTileService](docs/en/WebMapTileService.md) - Generate map tiles for Leaflet or Openlayers from your DataObjects

## Note

The module is incompatible with the framework version 4.3.1, which disallowed parameterised field assignments. The issue [has been fixed](https://github.com/silverstripe/silverstripe-framework/pull/8815), so that all versions of the framework before and after 4.3.1 are working.
