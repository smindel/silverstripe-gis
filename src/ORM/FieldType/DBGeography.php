<?php

namespace Smindel\GIS\ORM\FieldType;

use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\ORM\FieldType\DBComposite;
use Smindel\GIS\Forms\MapField;

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

    public function scaffoldFormField($title = null, $params = null)
    {
        return MapField::create($this->name, $title);
    }
}
