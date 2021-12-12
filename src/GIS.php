<?php

namespace Smindel\GIS;

use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeography;
use Smindel\GIS\ORM\FieldType\DBGeometry;
use proj4php\Proj4php;
use proj4php\Proj;
use proj4php\Point;
use Exception;

/**
 * @property string $array
 * @property string $ewkt
 * @property string $wkt
 * @property string $srid
 * @property string $type
 * @property string $coordinates
 */
class GIS
{
    use Configurable;

    use Injectable;

    const WKT_PATTERN = '/^(([A-Z]+)\s*(\(.+\)))$/i';
    const EWKT_PATTERN = '/^SRID=(\d+);(([A-Z]+)\s*(\(.+\)))$/i';

    const TYPES = [
        'point' => 'Point',
        'linestring' => 'LineString',
        'polygon' => 'Polygon',
        'multipoint' => 'MultiPoint',
        'multilinestring' => 'MultiLineString',
        'multipolygon' => 'MultiPolygon',
        'geometrycollection' => 'GeometryCollection'
    ];

    private static $default_srid = 4326;

    protected $value;

    /**
     * Constructor
     *
     * @param $value mixed geo value:
     *      string: wkt (default srid) or ewkt
     *      array: just coordinates (default srid, autodetect shape) or assoc with type, srid and coordinates
     *      GIS: extract value
     *      DBGeography: extract value
     */
    public function __construct($value)
    {
        DB::get_schema()->initialise();
        if ($value instanceof GIS) {
            $this->value = $value->value;
        } elseif ($value instanceof DBGeography) {
            $this->value = $value->getValue();
        } elseif (is_string($value) && preg_match(self::WKT_PATTERN, $value)) {
            $this->value = 'SRID=' . self::config()->default_srid . ';' . $value;
        } elseif (is_array($value) && count($value) == 3 && isset($value['type'])) {
            $this->value = $value;
        } elseif (is_string($value) && preg_match(self::EWKT_PATTERN, $value)) {
            $this->value = $value;
        } elseif (empty($value) || isset($value['coordinates']) && empty($value['coordinates'])) {
            $this->value = null;
        } elseif (is_array($value) && !isset($value['type'])) {
            switch (true) {
                case is_numeric($value[0]):
                    $type = 'Point';
                    break;
                case is_numeric($value[0][0]):
                    $type = 'LineString';
                    break;
                case is_numeric($value[0][0][0]):
                    $type = 'Polygon';
                    break;
                case is_numeric($value[0][0][0][0]):
                    $type = 'MultiPolygon';
                    break;
            }
            $this->value = [
                'srid' => GIS::config()->default_srid,
                'type' => $type,
                'coordinates' => $value,
            ];
        } else {
            throw new Exception('Invalid geo value: "' . var_export($value) . '"');
        }
    }

    public function __isset($property)
    {
        return array_search($property, ['array', 'ewkt', 'srid', 'type', 'coordinates']) !== false;
    }

    public function __get($property)
    {
        if (isset($this->value[$property])) {
            return $this->value[$property];
        }

        switch ($property) {
            case 'array':
                return ['srid' => $this->srid, 'type' => $this->type, 'coordinates' => $this->coordinates];
            case 'ewkt':
                return (string)$this;
            case 'wkt':
                return explode(';', (string)$this)[1];
            case 'srid':
                return preg_match('/^SRID=(\d+);/i', $this->value, $matches) ? (int)$matches[1] : null;
            case 'type':
                return preg_match(
                    '/^SRID=\d+;(' .
                    implode('|', array_change_key_case(self::TYPES, CASE_UPPER)) . ')/i',
                    $this->value,
                    $matches
                ) ? self::TYPES[strtolower($matches[1])] : null;
            case 'coordinates':
                if (preg_match(self::EWKT_PATTERN, $this->value, $matches)) {
                    $coords = str_replace(
                        ['(', ')'],
                        ['[', ']'],
                        preg_replace('/([\d\.-]+)\s+([\d\.-]+)/', "[$1,$2]", $matches[4])
                    );

                    if (strtolower($matches[3]) != 'point') {
                        $coords = "[$coords]";
                    }

                    return json_decode($coords, true)[0];
                } else {
                    return null;
                }
            case 'geometries':
                // primarily used for GeometryCollections
                // @todo: what's supposed to be returned for non-GeometryCollections?
                if (preg_match(self::EWKT_PATTERN, $this->value, $matches)) {
                    $geometries = preg_split('/,(?=[a-zA-Z])/', substr($matches[4], 1, -1));
                    $srid = $this->srid;
                    return array_map(function ($geometry) use ($srid) {
                        return GIS::create('SRID=' . $srid . ';' . $geometry);
                    }, $geometries);
                }
                return null;
            default:
                throw new Exception('Unkown property ' . $property);
        }
    }

    public function __toString()
    {
        if (is_string($this->value)) {
            return $this->value;
        }

        $type = isset($this->value['type']) ? strtoupper($this->value['type']) : null;
        $srid = isset($this->value['srid']) ? $this->value['srid'] : GIS::config()->default_srid;
        $array = isset($this->value['coordinates']) ? $this->value['coordinates'] : $this->value;

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

    public function isNull()
    {
        return empty($this->value) || isset($this->value['coordinates']) && empty($this->value['coordinates']);
    }

    public static function of($dataObjectClass)
    {
        if ($field = $dataObjectClass::config()->get('default_geo_field')) {
            return $field;
        }

        foreach ($dataObjectClass::config()->get('db') ?: [] as $field => $type) {
            if (in_array($type, ['Geography', 'Geometry', DBGeography::class, DBGeometry::class])) {
                return $field;
            }
        }
    }

    /**
     * reproject an array representation of a geometry to the given srid
     */
    public function reproject($toSrid = 4326)
    {
        $fromSrid = $this->srid;

        if ($fromSrid == $toSrid) {
            return clone $this;
        }

        $fromCoordinates = $this->coordinates;
        $type = $this->type;

        $fromProj = self::get_proj4($fromSrid);
        $toProj = self::get_proj4($toSrid);
        $toCoordinates = self::reproject_array($fromCoordinates, $fromProj, $toProj);

        return GIS::create([
            'srid' => $toSrid,
            'type' => $type,
            'coordinates' => $toCoordinates,
        ]);
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
                throw new Exception("Cannot use unregistered SRID $srid. Register it's " .
                    "<a href=\"http://spatialreference.org/ref/epsg/$srid/proj4/\">" .
                    "PROJ.4 definition</a> in GIS::projections.");
            }

            self::$proj4->addDef('EPSG:' . $srid, $projDefs[$srid]);
        }

        return new Proj('EPSG:' . $srid, self::$proj4);
    }

    protected static function reproject_array($coordinates, $fromProj, $toProj)
    {
        return self::each($coordinates, function ($coordinate) use ($fromProj, $toProj) {
            return array_slice(self::$proj4->transform(
                $toProj,
                new Point(
                    $coordinate[0],
                    $coordinate[1],
                    $fromProj
                )
            )->toArray(), 0, 2);
        });
    }

    public static function each($coordinates, $callback)
    {
        if ($coordinates instanceof GIS) {
            $coordinates = $coordinates->coordinates;
        }

        if (is_array($coordinates[0])) {
            foreach ($coordinates as &$coordinate) {
                $coordinate = self::each($coordinate, $callback);
            }

            return $coordinates;
        }

        return $callback($coordinates);
    }

    public function distance($geo)
    {
        return DB::query('select ' . DB::get_schema()->translateDistanceQuery($this, GIS::create($geo)))->value();
    }
}
