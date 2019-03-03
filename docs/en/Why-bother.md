# Why would you bother?

If all you want to do is save a lat/lon with your DataObject and use that on a map, you don't need this module. As a matter of fact it doesn't even give you a frontend map. So why would you use silverstripe-gis? If you can answer any of the following questions with yes, that's why:

- Do you want to store more complex geo data than just single points, e.g. multi points, lines or polygons?
- Do you need to find geometries by their spatial relation to other geometries?
- Is your geo data projected or do you need to project it?
- Would you like to render map tiles from your data to be used in map frontends like Leaflet or Openlayers?
- Would you like to produce GeoJSON from your data on the fly to be used in map frontends like Leaflet or Openlayers?

If any of the above words didn't make sense to you, carry on ready my own layman's theory of GIS.

## Lat/lon should actually be lon/lat

The longitude tells you how far east or west of Greenwich (UK) a point is on Earth's surface in degrees. The latitude tells you how far north or south of the Equator. We use the term lat/lon frequently but like in most other coordinate systems, the horizontal value comes before the vertical value (most of the time, exceptions are amongst others GML). So lat/lon should actually be lon/lat.

## Projection

While lat/lon is a geographic coordinate system, well suited to describe locations on the surface of the almost spherical Earth, it distorts flat 2D maps as you move from the Equator towards the poles. Use the projections best suited for your purpose. []

You can mix projections in a table but they will only ever relate to each other if they are in the same projection.

## Geometry vs Geography

Geographies are a spherical representation, Geometries cartesian representations.

## MySQL vs Postgres

MySQL has only limited GIS features and does not support Geographies.

## Precision and accuracy

https://gis.stackexchange.com/questions/8650/measuring-accuracy-of-latitude-and-longitude/8674#8674
https://en.wikipedia.org/wiki/Decimal_degrees#Precision
