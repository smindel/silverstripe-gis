# Smindel\GIS\Model\Raster

## Import rasters

Rasters live in the tables in the database that are not managed through SilverStripe. You have to import them yourself: Download a GeoTIFF and import it. Here is an example:

    raster2pgsql -I -C -F -t 100x100 -s 4326 lenz-mean-annual-temperature.4326.tif temperature_raster > temperature_raster.4326.sql
    sudo -u postgres psql SS_gis -f temperature_raster.4326.sql

Here the table name temperature_raster was picked deliberately to break SilverStripe's table name convention in order to avoid name clashes in the db, here SS_gis.

All you have to do in your code is create one extension of the Raster class per raster table. See the [temperature example](#temperature-example).

## Methods

### public function Raster::ST_Value($geo, $band = 1) : numeric

Returns the value of the raster at the given location for the given band.

- __$geo__ (array) geo as simple or explicit array
- __$band__ (int) raster band to return the value for

Returns the raw numeric value

### public function Raster::ST_SummaryStats($geo = null, $band = 1) : array

Returns stats on the raster for the given geo and band.

- __$geo__ (array) geo as simple or explicit array, if omitted band 1 of the entire raster is summarised (!)
- __$band__ (int) raster band to return the value for

Returns stats as an associative array with the keys: count, sum, mean, stddev, min and max

## Examples

### Temperature example

You have to supply the raster table name and column as well as the SRID and the raster dimensions (upper left and lower right corner coordinates in the raster's unit), which you can read from your rasterfile through gdalinfo:

    gdalinfo lenz-mean-annual-temperature.4326.tif

If the raster doesn't use 8BUI or 16BUI colours you also have to supply an otherwise optional [colormap](https://postgis.net/docs/RT_ST_ColorMap.html).

```php
<?php

use Smindel\GIS\Model\Raster;

class Temperature extends Raster
{
    private static $webmaptileservice = true;

    protected $tableName = 'temperature_raster';

    protected $rasterColumn = 'rast';

    protected $srid = 4326;

    protected $dimensions = [
        166.1220958, -33.9577085,
        179.6020165, -47.3137072,
    ];

    protected $colorMap = '162 255   0   0 255
        130 255 191 128 255
        -69   0   0 255 255
         0%   0   0   0   0
         nv 255 255 255   0';
}
```

You can now use the raster methods:

```php
$temperature = Temperature::create()->ST_Value(GIS::to_array('SRID=4326;POINT(174.78 -41.29)'));
// 130
```

With `$webmaptileservice` set to a trueish value you can also serve map tiles. Use your Raster class as the model. See the [example](WebMapTileService.md#accessing-the-endpoint). This is hard on the db so consider tile caching.

## For internal reference PostGIS ST_Retile implementation

https://appgeodb.nancy.inra.fr/donnees/documentations/bdd/db_cefs/technique/Functions/st_retile_public.html
