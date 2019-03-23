<?php

namespace Smindel\GIS;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeography;
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
use Exception;

class GIS
{
    use Configurable;

    use Injectable;

    private static $default_srid = 4326;

    const EWKT_PATTERN = '/^SRID=(\d+);(([A-Z]+)\s*(\(.+\)))$/i';

    const TYPES = [
        'point' => 'Point',
        'linestring' => 'LineString',
        'polygon' => 'Polygon',
        'multipolygon' => 'MultiPolygon',
    ];

    public static function of($dataObjectClass)
    {
        if ($field = $dataObjectClass::config()->get('default_geo_field')) {
            return $field;
        }

        foreach ($dataObjectClass::config()->get('db') ?: [] as $field => $type) {
            if ($type == 'Geography' || $type == 'Geometry') {
                return $field;
            }
        }
    }

    public static function to_ewkt($geo)
    {
        if (is_string($geo)) return $geo;

        if ($geo instanceof DBGeography) $geo = $geo->getValue();

        $type = isset($geo['type']) ? strtoupper($geo['type']) : null;
        $srid = isset($geo['srid']) ? $geo['srid'] : Config::inst()->get(self::class, 'default_srid');
        $array = isset($geo['coordinates']) ? $geo['coordinates'] : $geo;

        if (!$type) {
            switch (true) {
                case is_numeric($array[0]): $type = 'POINT'; break;
                case is_numeric($array[0][0]): $type = 'LINESTRING'; break;
                case is_numeric($array[0][0][0]): $type = 'POLYGON'; break;
                case is_numeric($array[0][0][0][0]): $type = 'MULTIPOLYGON'; break;
            }
        }

        $replacements = [
            '/(?<=\d),(?=-|\d)/' => ' ',
            '/\[\[\[\[/' => '(((',
            '/\]\]\]\]/' => ')))',
            '/\[\[\[/' => '((',
            '/\]\]\]/' => '))',
            '/\[\[/' => '(',
            '/\]\]/' => ')',
            '/\[/' => '',
            '/\]/' => '',
        ];

        $coords = preg_replace(array_keys($replacements), array_values($replacements), json_encode($array));

        return sprintf('SRID=%d;%s%s', $srid, $type, $type == 'POINT' ? "($coords)" : $coords);
    }

    public static function to_array($geo)
    {
        if ($geo instanceof DBGeography) $geo = $geo->getValue();

        if (is_array($geo)) {

            if (isset($geo['coordinates'])) return $geo;

            switch (true) {
                case is_numeric($geo[0]): $type = 'Point'; break;
                case is_numeric($geo[0][0]): $type = 'LineString'; break;
                case is_numeric($geo[0][0][0]): $type = 'Polygon'; break;
                case is_numeric($geo[0][0][0][0]): $type = 'MultiPolygon'; break;
            }

            return [
                'srid' => Config::inst()->get(self::class, 'default_srid'),
                'type' => $type,
                'coordinates' => $geo
            ];
        }

        if (preg_match(self::EWKT_PATTERN, $geo, $matches)) {

            $coords = str_replace(['(', ')'], ['[', ']'], preg_replace('/([\d\.-]+)\s+([\d\.-]+)/', "[$1,$2]", $matches[4]));

            if (strtolower($matches[3]) != 'point') {
                $coords = "[$coords]";
            }

            return [
                'srid' => $matches[1],
                'type' => self::TYPES[strtolower($matches[3])],
                'coordinates' => json_decode($coords, true)[0],
            ];
        }
    }

    public static function split_ewkt($ewkt, $fallbackSrid = null)
    {
        $fallbackSrid = $fallbackSrid ?: Config::inst()->get(self::class, 'default_srid');

        if (preg_match(GIS::EWKT_PATTERN, $ewkt, $matches)) {
            $wkt = $matches[2];
            $srid = (int)$matches[1];
        } else {
            $wkt = $ewkt;
            $srid = (int)$fallbackSrid;
        }

        return [$wkt, $srid];
    }

    public static function get_type($geo)
    {
        if (is_array($geo) && isset($geo['type'])) {
            return self::TYPES[strtolower($geo['type'])];
        } elseif (is_array($geo)) {
            $geo = self::to_ewkt($geo);
        }

        if (preg_match(
            '/;(' . implode('|', array_keys(self::TYPES)) . ')\(/i',
            strtolower(substr($geo, 8, 30)),
            $matches
        )) {
            return self::TYPES[$matches[1]];
        }
    }

    /**
     * reproject an array representation of a geometry to the given srid
     */
    public static function reproject($geo, $toSrid = 4326)
    {
        $array = self::to_array($geo);

        $fromSrid = $array['srid'];
        $fromCoordinates = $array['coordinates'];
        $type = $array['type'];

        if ($fromSrid != $toSrid) {
            $fromProj = self::get_proj4($fromSrid);
            $toProj = self::get_proj4($toSrid);
            $toCoordinates = self::reproject_array($fromCoordinates, $fromProj, $toProj);
        } else {
            $toCoordinates = $fromCoordinates;
        }

        return [
            'srid' => $toSrid,
            'type' => $type,
            'coordinates' => $toCoordinates,
        ];
    }

    /**
     * @var proj4php instance
     */
    protected static $proj4;

    protected static function get_proj4($srid)
    {
        self::$proj4 = self::$proj4 ?: new Proj4php();

        if (!self::$proj4->hasDef('EPSG:' . $srid)) {

            $projDefs = Config::inst()->get(self::class, 'projections');

            if (!isset($projDefs[$srid])) {
                throw new Exception("Cannot use unregistered SRID $srid. Register it's <a href=\"http://spatialreference.org/ref/epsg/$srid/proj4/\">PROJ.4 definition</a> in GIS::projections.");
            }

            self::$proj4->addDef('EPSG:' . $srid, $projDefs[$srid]);
        }

        return new Proj('EPSG:' . $srid, self::$proj4);
    }

    protected static function reproject_array($coordinates, $fromProj, $toProj)
    {
        return self::each($coordinates, function($coordinate) use ($fromProj, $toProj) {
            return array_slice(self::$proj4->transform($toProj, new Point($coordinate[0], $coordinate[1], $fromProj))->toArray(), 0, 2);
        });
    }

    public static function each($coordinates, $callback)
    {
        if (isset($coordinates['coordinates'])) {
            $coordinates = $coordinates['coordinates'];
        }

        if (is_array($coordinates[0])) {

            foreach ($coordinates as &$coordinate) {
                $coordinate = self::each($coordinate, $callback);
            }

            return $coordinates;
        }

        return $callback($coordinates);
    }

    public static function distance($geo1, $geo2)
    {
        return DB::query('select ' . DB::get_schema()->translateDistanceQuery($geo1, $geo2))->value();
    }
}
