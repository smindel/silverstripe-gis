<?php

namespace Smindel\GIS;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
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

        foreach ($dataObjectClass::config()->get('db') as $field => $type) {
            if ($type == 'Geography' || $type == 'Geometry') {
                return $field;
            }
        }
    }

    public static function array_to_ewkt($array, $srid = null, $useBestGuess = false)
    {
        $type = isset($array['type']) ? strtoupper($array['type']) : null;
        $srid = isset($array['srid']) ? $array['srid'] : ($srid ?: Config::inst()->get(self::class, 'default_srid'));
        $array = isset($array['coordinates']) ? $array['coordinates'] : $array;

        if ($type == 'POINT' || is_numeric($array[0])) {
            $type = 'POINT';

            $coords = implode(' ', $array);
        } elseif (in_array($type, ['LINESTRING', 'MULTIPOINT']) || is_numeric($array[0][0])) {
            if (!$type) {
                if (!$useBestGuess) {
                    throw new Exception('Cannot infer shape type from data.');
                }
                $type = 'LINESTRING';
            }

            $coords = implode(
                ',',
                array_map(
                    function ($coord) {
                        return implode(
                            ' ',
                            $coord
                        );
                    },
                    $array
                )
            );
        } elseif (in_array($type, ['POLYGON', 'MULTILINESTRING']) || is_numeric($array[0][0][0])) {
            if (!$type) {
                if (!$useBestGuess) {
                    throw new Exception('Cannot infer shape type from data.');
                }
                $type = 'POLYGON';
            }

            $coords = '(' . implode(
                '),(',
                array_map(
                    function ($coords) {
                        return implode(
                            ',',
                            array_map(
                                function ($coord) {
                                    return implode(
                                        ' ',
                                        $coord
                                    );
                                },
                                $coords
                            )
                        );
                    },
                    $array
                )
            ) . ')';
        } elseif (is_numeric($array[0][0][0][0])) {
            $type = 'MULTIPOLYGON';

            $coords = '(' . implode(
                '),(',
                array_map(
                    function ($coords) {
                        return '(' . implode(
                            '),(',
                            array_map(
                                function ($coords) {
                                    return implode(
                                        ',',
                                        array_map(
                                            function ($coord) {
                                                return implode(' ', $coord);
                                            },
                                            $coords
                                        )
                                    );
                                },
                                $coords
                            )
                        ) . ')';
                    },
                    $array
                )
            ) . ')';
        }

        return sprintf('SRID=%d;%s(%s)', $srid, $type, $coords);
    }

    public static function ewkt_to_array($ewkt)
    {
        if (preg_match(self::EWKT_PATTERN, $ewkt, $matches)) {
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
        return [$wkt,$srid];
    }

    public static function get_type($geometry, $useBestGuess = false)
    {
        if (is_array($geometry) && isset($geometry['type'])) {
            return self::TYPES[strtolower($geometry['type'])];
        } elseif (is_array($geometry)) {
            $geometry = self::array_to_ewkt($geometry, null, $useBestGuess);
        }
        if (preg_match(
            '/;(' . implode('|', array_keys(self::TYPES)) . ')\(/i',
            strtolower(substr($geometry, 8, 30)),
            $matches
        )) {
            return self::TYPES[$matches[1]];
        }
    }

    /**
     * reproject an array representation of a geometry to the given srid
     */
    public static function reproject_array($array, $toSrid = 4326)
    {
        if (isset($array['srid'])) {
            $fromSrid = $array['srid'];
            $fromCoordinates = $array['coordinates'];
            $type = $array['type'];
        } else {
            $fromSrid = Config::inst()->get(self::class, 'default_srid') ?: 4326;
            $fromCoordinates = $array;
            $type = is_array($array[0]) ? (is_array($array[0][0]) ? 'Polygon' : 'LineString') : 'Point';
        }

        if ($fromSrid != $toSrid) {
            $fromProj = self::get_proj4($fromSrid);
            $toProj = self::get_proj4($toSrid);
            $toCoordinates = self::reproject($fromCoordinates, $fromProj, $toProj);
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

    protected static function reproject($coordinates, $fromProj, $toProj)
    {
        return self::each($coordinates, function ($coordinate) use ($fromProj, $toProj) {
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
