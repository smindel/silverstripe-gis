# Smindel\GIS\Service\GeoJSONImporter

To import GeoJSON programmatically use the GeoJsonImporter.

## Methods

### public static function GeoJSONImporter::import($class, $geoJson, $propertyMap = null, $geoProperty = null, $featureCallback = null) : void

Import GeoJSON files programmatically.

- __$class__ (string) DataObject class to import to
- __$geoJson__ (string) raw GeoJSON data
- __$propertyMap__ (array) map GeoJSON properties to the DataObject's db fields, uses summary fields by default
- __$geoProperty__ (string) name of the geo property of the DataObject class, defaults to the first geo field
- __$featureCallback__ (callable) callable to preprocess features, receives a feature as the only attribute, returns the preprocesses feature as an array

## Examples

### Use defaults

```php
use Smindel\GIS\Service\GeoJSONImporter;

GeoJsonImporter::import(
    City::class,
    file_get_contents('City.geojson')
);
```

### Full example

```php
use Smindel\GIS\Service\GeoJSONImporter;

GeoJsonImporter::import(
    Country::class,
    file_get_contents('Country.geojson'),
    [
        'country_name' => 'Name',
        'polulation' => 'Population',
    ],
    'Area',
    [Country::class, 'on_before_import']
);
```
