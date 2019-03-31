# Smindel\GIS\Model\Raster

In order to use the raster feature you have to install gdal:

    $ sudo apt-get install gdal-bin

## Methods

### public function Raster::__construct($filename = null)

Constructor

- __$filename__ (string) absolute pathe or relative to the public folder

### public function Raster::getLocationInfo($geo, $band = 1) : array

Returns the values of the raster at the given location for the given band or all bands.

- __$geo__ (array) geo as simple or explicit array
- __$band__ (int) raster band to return the value for

Returns an array of raw numeric values per band

## Examples

### Basic Examples

```php
$filename = ASSETS_PATH . DIRECTORY_SEPARATOR . 'lenz-mean-annual-temperature.4326.tif';
$bandValues = Raster::create($filename)->getLocationInfo([174.78, -41.29]);
// [1 => 130]
```

### Tiles from raster extensions

There are two ways to use rasters with the WebMapTileService, depending on how you want to handle your rasters. If you have large static rasters baked into you business logic that are not likely to change, you would extend the Raster class like this:

```php
<?php

namespace My\Name\Space;

use Smindel\GIS\Model\Raster;

class Temperature extends Raster
{
    private static $webmaptileservice = true;

    private static $full_path = ASSETS_PATH . DIRECTORY_SEPARATOR . 'Wellington.Harbour.4326.tif';
}
```

Your URL would be for example:

    https://yourdomain/webmaptileservice/My-Name-Space-Temperature/11/2018/1282.png

Leaflet:

```javascript
L.tileLayer('http://yourdomain/webmaptileservice/My-Name-Space-Temperature/{z}/{x}/{y}.png').addTo(map);
```

### Tiles from Images in the assets

If your rasters are small enough to be handled in the [assets module](https://github.com/silverstripe/silverstripe-asset-admin) you can turn on tile rendering on assets, e.g.:

app/_config/config.yml

```yaml
---
Name: myproject
After:
  - '#gisconfig'
---
SilverStripe\Assets\File:
  webmaptileservice:
    cache_ttl: 3600
```

Your URL would be for example:

    https://yourdomain/webmaptileservice/SilverStripe-Assets-File/3/11/2018/1282.png

Leaflet:

```javascript
L.tileLayer('http://yourdomain/webmaptileservice/SilverStripe-Assets-File/3/{z}/{x}/{y}.png').addTo(map);
```

The 3, the first number would be the ID of the File in the assets.
