<?php

namespace Smindel\GIS\ORM\FieldType;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBComposite;
use Smindel\GIS\Forms\MapField;
use Smindel\GIS\GIS;

class DBGeography extends DBComposite
{
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
            ]
        );
    }

    public function addToQuery(&$query)
    {
        $table = $this->getTable();
        $column = $this->getName();
        $identifier = $table ? sprintf('"%s"."%s"', $table, $column) : sprintf('"%s"', $column);
        $select = sprintf('CASE WHEN %s IS NULL THEN NULL ELSE CONCAT(\'SRID=\', ST_SRID(%s), \';\', ST_AsText(%s)) END AS "%s"', $identifier, $identifier, $identifier, $column);
        $query->selectField($select);
    }

    public function compositeDatabaseFields()
    {
        return ['' => 'Geography'];
    }

    public function prepValueForDB($value)
    {
        if (!$value) {
            return null;
        }

        list($wkt, $srid) = GIS::split_ewkt($value);

        if ($srid != 4326) {
            list($wkt) = GIS::split_ewkt(GIS::array_to_ewkt(GIS::reproject_array(GIS::ewkt_to_array($value), 4326)));
        }

        return ['ST_GeogFromText(?)' => [$wkt]];
    }

    public function exists()
    {
        // reinstates parent::parent::exists()
        return DBField::exists();
    }

    public function writeToManipulation(&$manipulation)
    {
        // reinstates parent::parent::writeToManipulation()
        return DBField::writeToManipulation($manipulation);
    }

    public function saveInto($dataObject)
    {
        // reinstates parent::parent::saveInto()
        return DBField::saveInto($dataObject);
    }

    public function setValue($value, $record = null, $markChanged = true)
    {
        // reinstates parent::parent::setValue()
        return DBField::setValue($value, $record, $markChanged);
    }

    public function scaffoldFormField($title = null, $params = null)
    {
        return MapField::create($this->name, $title);
    }

    public function scalarValueOnly()
    {
        return false;
    }
}
