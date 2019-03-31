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
        $value = GIS::create($value);

        if ($value->isNull()) {
            return null;
        }

        return ['ST_GeogFromText(?)' => [$value->reproject(4326)->wkt]];
    }

    public function exists()
    {
        // reinstates parent::parent::exists()
        return DBField::exists();
    }

    public function writeToManipulation(&$manipulation)
    {
        // reinstates parent::parent::writeToManipulation()
        DBField::writeToManipulation($manipulation);
    }

    public function saveInto($dataObject)
    {
        // reinstates parent::parent::saveInto()
        DBField::saveInto($dataObject);
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

    public function getRAW()
    {
        return (string)$this;
    }
}
