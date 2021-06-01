# ORM Filters

The module adds spatial filters to the ORM in order to query DataObjects. Most of them test relationships and accept a geometry as a parameter, except ST_Distance which expects an array of geometry and distance and ST_GeometryType which expects a shape type:

All filters can also be inverted using the `:not` modifier.

## Filters

### ST_Contains

Filter geometries that [contain](https://postgis.net/docs/ST_Contains.html) the given geometry

```php
$city = City::get()->filter('Name', 'Wellington')->first();
$country = Country::get()->filter('Area:ST_Contains', $city->Location);
```

### ST_Crosses

Filter geometries that [cross](https://postgis.net/docs/ST_Crosses.html) the given geometry

### ST_Disjoint

Filter geometries that are [disjoint](https://postgis.net/docs/ST_Disjoint.html) from the given geometry

### ST_Distance

Filter geometries that are within [distance](https://postgis.net/docs/ST_Distance.html) of the given geometry

```php
$city = City::get()->filter('Name', 'Wellington')->first();
$distance = 100000; // metres
$degrees = $distance / 111195;
$cities = Cities::get()->filter('Location:ST_Distance', [$city->Location, $degrees]);
```

__Note:__ important to note is that the distance is given in the projections unit. For SRID 4326 it is degrees. Others use metres or kilometres. You can check this on [epsg.io](http://epsg.io/) for the projection you are using.

### ST_Equals

Filter geometries that are [equal](https://postgis.net/docs/ST_Equals.html) to the given geometry

### ST_GeometryType

Filter geometries by the given shape [type](https://postgis.net/docs/ST_GeometryType.html)

```php
$points = Anything::get()->filter('Location:ST_GeometryType', 'Point');
```

### ST_Intersects

Filter geometries that [intersect](https://postgis.net/docs/ST_Intersects.html) with the given geometry

### ST_Overlaps

Filter geometries that [overlap](https://postgis.net/docs/ST_Overlaps.html) the given geometry

### ST_Touches

Filter geometries that [touch](https://postgis.net/docs/ST_Touches.html) the given geometry

### ST_Within

Filter geometries that lie [within](https://postgis.net/docs/ST_Within.html) the given geometry
