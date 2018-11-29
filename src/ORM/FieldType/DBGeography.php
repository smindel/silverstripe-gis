<?php

namespace Smindel\GIS\ORM\FieldType;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBComposite;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\Forms\MapField;
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
use Exception;

class DBGeography extends DBComposite
{
    const POINT = 'POINT';

    private static $default_location = [10,53.5];

    private static $default_projection = 4326;

    CONST EWKT_PATTERN = '/^SRID=(\d+);(([A-Z]+)\s*(\(.+\)))$/i';

    protected $geographyType;
    protected $srid;

    public function __construct($name = null, $geographyType = null, $srid = null, $options = [])
    {
        $this->geographyType = $geographyType;
        $this->srid = $srid;
        parent::__construct($name, $options);
    }

    /**
     * Add the field to the underlying database.
     */
    public function requireField()
    {
        DB::require_field(
            $this->tableName,
            $this->name,
            [
                'type'=>'geography',
                'parts' => [
                    'datatype' => $this->geographyType,
                    'srid' => $this->srid,
                ]
            ]
        );
    }

    public function addToQuery(&$query)
    {
        $select = DB::get_schema()->translateToRead($this);
        $query->selectField($select);
    }

    public function compositeDatabaseFields()
    {
        return ['' => 'Geography'];
    }

    public function writeToManipulation(&$manipulation)
    {
        $value = $this->exists() ? DB::get_schema()->translateToWrite($this) : $this->nullValue();
        $manipulation['fields'][$this->name] = $value;
    }

    public function exists()
    {
        // reinstates parent::parent::writeToManipulation()
        return DBField::exists();
    }

    public function saveInto($dataObject)
    {
        // reinstates parent::parent::setValue()
        return DBField::saveInto($dataObject);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        // reinstates parent::parent::setValue()
        return DBField::setValue($value, $record, $markChanged);
    }

    public static function of($dataObjectClass)
    {
        return $dataObjectClass::config()->get('default_geography') ?: array_search('Geography', $dataObjectClass::config()->get('db'));
    }

    public static function from_array($array, $srid = null)
    {
        $srid = $srid ?: Config::inst()->get(self::class, 'default_projection');

        if (is_numeric($array[0])) {
            $type = 'POINT';
            $coords = implode(' ', $array);
        } else if (is_numeric($array[0][0])) {
            $type = 'LINESTRING';
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
        } else if (is_numeric($array[0][0][0])) {
            $type = 'POLYGON';
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
        } else if (is_numeric($array[0][0][0][0])) {
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
                                                return implode(
                                                    ' ',
                                                    $coord
                                                );
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

    public static function to_array($ewkt)
    {
        $types = [
            'point' => 'Point',
            'linestring' => 'LineString',
            'polygon' => 'Polygon',
            'multipolygon' => 'MultiPolygon',
        ];
        if (preg_match(self::EWKT_PATTERN, $ewkt, $matches)) {
            $coords = str_replace(['(', ')'], ['[', ']'], preg_replace('/([\d\.-]+)\s+([\d\.-]+)/', "[$1,$2]", $matches[4]));
            if (strtolower($matches[3]) != 'point') {
                $coords = "[$coords]";
            }
            return [
                'srid' => $matches[1],
                'type' => $types[strtolower($matches[3])],
                'coordinates' => json_decode($coords, true)[0],
            ];
        }
    }

    /**
     * @var proj4php instance
     */
    protected static $proj4;

    /**
     * reproject an array representation of a geometry to the given srid
     */
    public static function to_srid($array, $toSrid = 4326)
    {
        if (isset($array['srid'])) {
            $fromSrid = $array['srid'];
            $fromCoordinates = $array['coordinates'];
            $type = $array['type'];
        } else {
            $fromSrid = Config::inst()->get(DBGeography::class, 'default_projection') ?: 4326;
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

    public static function get_proj4($srid)
    {
        self::$proj4 = self::$proj4 ?: new Proj4php();

        if (!self::$proj4->hasDef('EPSG:' . $srid)) {
            $projDefs = Config::inst()->get(DBGeography::class, 'projections');
            if (!isset($projDefs[$srid])) {
                throw new Exception("Cannot use unregistered SRID $srid. Register it's <a href=\"http://spatialreference.org/ref/epsg/$srid/proj4/\">PROJ.4 definition</a> in DBGeography::projections.");
            }
            self::$proj4->addDef('EPSG:' . $srid        , $projDefs[$srid]);
        }

        return new Proj('EPSG:' . $srid, self::$proj4);
    }

    public static function reproject_old($coordinates, $fromProj, $toProj)
    {
        if (is_array($coordinates[0])) {
            foreach ($coordinates as &$coordinate) {
                $coordinate = self::reproject($coordinate, $fromProj, $toProj);
            }
            return $coordinates;
        }

        return array_slice(self::$proj4->transform($toProj, new Point($coordinates[0], $coordinates[1], $fromProj))->toArray(), 0, 2);
    }

    public static function reproject($coordinates, $fromProj, $toProj)
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

    public function scaffoldFormField($title = null, $params = null)
    {
        return MapField::create($this->name, $title);
    }

    public static function distance($geo1,$geo2)
    {
        return DB::query('select ' . DB::get_schema()->translateDistanceQuery($geo1,$geo2))->value();
    }

    public static function split_ewkt($ewkt, $fallbackSrid = null)
    {
        $fallbackSrid = $fallbackSrid ?: Config::inst()->get(self::class, 'default_projection');

        if (preg_match(DBGeography::EWKT_PATTERN, $ewkt, $matches)) {
            $wkt = $matches[2];
            $srid = (int)$matches[1];
        } else {
            $wkt = $ewkt;
            $srid = (int)$fallbackSrid;
        }
        return [$wkt,$srid];
    }
}
