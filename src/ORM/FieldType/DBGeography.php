<?php

namespace Smindel\GIS\ORM\FieldType;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBComposite;
use Smindel\GIS\Forms\MapField;

class DBGeography extends DBComposite
{
    const POINT = 'POINT';

    private static $default_location = [10,53.5];

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

    public static function fromArray($array, $srid = 4326)
    {
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
        if (preg_match(self::EWKT_PATTERN, $ewkt, $matches)) {
            $type = str_replace(['polygon', 'linestring'], ['Polygon', 'LineString'], ucfirst(strtolower($matches[3])));
            $coordinates = preg_replace('/([\d\.-]+)\s+([\d\.-]+)/', "[$1,$2]", $matches[4]);
            return [
                'srid' => $matches[1],
                'type' => $type,
                'coordinates' => json_decode(str_replace(
                    ['(', ')'],
                    $type == 'Point' ? '' : ['[', ']'],
                    $coordinates
                ), true),
            ];
        }
    }

    public function scaffoldFormField($title = null, $params = null)
    {
        return MapField::create($this->name, $title);
    }

    public static function distance($geo1,$geo2)
    {
        return DB::query('select ' . DB::get_schema()->translateDistanceQuery($geo1,$geo2))->value();
    }

    public static function split_ewkt($ewkt, $fallbackSrid = 4326)
    {
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
