<?php

namespace Smindel\GIS\ORM\FieldType;

use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class DBGeometry extends DBGeography
{
    protected $srid;

    public function __construct($name = null, $srid = null, $options = [])
    {
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
                'type'=>'geometry',
                'parts' => [
                    'srid' => $this->srid,
                ]
            ]
        );
    }

    public function compositeDatabaseFields()
    {
        return ['' => 'Geometry'];
    }

    public function prepValueForDB($value)
    {
        if (!$value) {
            return null;
        }

        list($wkt, $srid) = GIS::split_ewkt($value);

        return ['ST_GeomFromText(?, ?)' => [$wkt, $srid]];
    }
}
