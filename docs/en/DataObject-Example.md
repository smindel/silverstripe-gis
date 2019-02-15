# DataObject Example

## Minimal Example

All features of the module can be used separately and are pre-configured with sensible defaults. In order to setup a DataObject with a geometry and expose it through the GeoJSON and Web Map Tile Service the following is the minimal example:

app/src/Model/City.php

```php
<?php

use SilverStripe\ORM\DataObject;

class City extends DataObject
{
    private static $db = [
        'Name' => 'Varchar',
        'Location' => 'Geometry',
        'Population' => 'Int',
    ];

    private static $geojsonservice = true;

    private static $webmaptileservice = [
        'cache_ttl' => 3600,
    ];
}
```

## Full Example

The next example shows all possible configuration options and methods in action:

app/src/Model/City.php

```php
<?php

use SilverStripe\ORM\DataObject;
use Smindel\GIS\Forms\MapField;
use ImagickDraw;

class City extends DataObject
{
    private static $db = [
        'Name' => 'Varchar',
        'Location' => 'Geometry',
        'Population' => 'Int',
    ];

    private static $default_geo_field = 'Location';

    private static $geojsonservice = [
        'geometry_field' => 'Location',     // set geometry field explicitly
        'searchable_fields' => [            // set fields that can be searched by through the service
            'FirstName' => [
                'title' => 'given name',
                'filter' => 'ExactMatchFilter',
            ],
            'Surname' => [
                'title' => 'name',
                'filter' => 'PartialMatchFilter',
            ],
        ],
        'code' => 'ADMIN',                  // restrict access to admins (see: Permission::check())
        'record_provider' => [              // callable to return a DataList of records to be served
            'SomeClass',                    // receives 2 parameters:
            'static_method'                 // the HTTPRequest and a reference which you can set to true
        ],                                  // in order to skip filtering further down in the stack
        'property_map' => [                 // map DataObject fields to GeoJSON properties
            'ID' => 'id',
            'FirstName' => 'given name',
            'Surname' => 'name',
        ],
    ];

    private static $webmaptileservice = [
        'default_style' => [
            'gd' => [
                'backgroundcolor' => [0, 0, 0, 127],
                'strokecolor' => [60, 60, 210, 0],
                'fillcolor' => [60, 60, 210, 80],
                'setthickness' => [2],
                'pointradius' => 5,
            ],
            'imagick' => [
                'StrokeOpacity' => 1,
                'StrokeWidth' => 2,
                'StrokeColor' => 'rgb(60,60,210)',
                'FillColor' => 'rgba(60,60,210,.25)',
                'PointRadius' => 5,
            ],
        ],
        'geometry_field' => 'Location',     // set geometry field explicitly
        'searchable_fields' => [            // set fields that can be searched by through the service
            'FirstName' => [
                'title' => 'given name',
                'filter' => 'ExactMatchFilter',
            ],
            'Surname' => [
                'title' => 'name',
                'filter' => 'PartialMatchFilter',
            ],
        ],
        'code' => 'ADMIN',                  // restrict access to admins (see: Permission::check())
        'record_provider' => [              // callable to return a DataList of records to be served
            'City',                         // receives 2 parameters:
            'record_provider'               // the HTTPRequest and a reference which you can set to true
        ],                                  // in order to skip filtering further down in the stack
        'tile_size' => [256, 256],
        'tile_buffer' => 5,
        'wrap_date' => true,
        'cache_path' => 'tile-cache',
        'cache_ttl' => 3600,
    ];

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->addFieldToTab(
            'Root.Main',
            MapField::create('Location')
                ->setControl('polyline', false)
                ->enableMulti(true),
            'Content'
        );
        return $fields;
    }

    public function requireDefaultRecords()
    {
        parent::requireDefaultRecords();
        if (!self::get()->count()) {
            GeoJsonImporter::import(
                self::class,
                file_get_contents(__DIR__ . '/City.geojson')
            );
        }
    }

    public static function record_provider($request, &$skipFilter)
    {
        $list = static::get();

        // custom actions

        // Set $skipFilter to true, if you have already processed request filters
        // or don't want the service to apply request filters for other reasons.

        return $list;
    }

    public function renderOnWebMapTile($tile)
    {
        $tileCoords = $tile->getRelativePixelCoordinates($this->Location);

        $style = [
            'PointRadius' => strlen($this->Population),
            'StrokeColor' => 'rgb(210,60,60)',
        ];

        if ($tile->getZXY()[0] < 10) {
            $tile->getResource()->drawPoint($tileCoords, $style);
        } else {

            $draw = new ImagickDraw();

            $draw->...(...$args);

            $tile->getResource()->drawImage($draw);

        }
    }
}
```
