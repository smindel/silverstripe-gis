# Smindel\GIS\Control\GeoJsonService

The configuration of the GeoJsonService applies to all GeoJSON Services you expose. They can be overwritten in [your DataObject classes](DataObject-Example.md).

## Configuration

### DataObject config

The service can just be turned on in the DataObject class with the default settings like this:

```php
private static $geojsonservice = true; // enables geojsonservice with default settings
```

... or control one or all configurable aspects:

```php
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
    'access_control_allow_origin' => '*',
];
```

## Accessing the endpoint

In order to access the endpoint you have to use the namespaced class name with the backslashes replaced with dashes:

    http://yourdomain/geojsonservice/VendorName-ProductName-DataObjectClassName

If you want to filter records, you can do so by using the configured or default search fields. You can even use filter modifiers:

    .../DataObjectClassName?FieldName:StartsWith:not=searchTerm

A Leaflet layer can be created like this, if you add the Leaflet.AJAX plugin:

```javascript
new L.GeoJSON.AJAX("http://yourdomain/geojsonservice/City")
    .bindPopup(function (layer) { return layer.feature.properties.Name; }).addTo(map);
```
