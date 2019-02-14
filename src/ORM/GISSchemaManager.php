<?php

namespace Smindel\GIS\ORM;

use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeometry;
use Smindel\GIS\GIS;

trait GISSchemaManager
{
    // Ellipsoidal spatial data type.
    public function geography($values)
    {
        return 'geography';
    }

    // Planar spatial data type.
    public function geometry($values)
    {
        return 'geometry';
    }

    public function translateToRead($field)
    {
        $table = $field->getTable();
        $column = $field->getName();
        $identifier = $table ? sprintf('"%s"."%s"', $table, $column) : sprintf('"%s"', $column);
        return sprintf('CASE WHEN %s IS NULL THEN NULL ELSE CONCAT(\'SRID=\', ST_SRID(%s), \';\', ST_AsText(%s)) END AS "%s"', $identifier, $identifier, $identifier, $column);
    }

    public function translateToWrite($field)
    {
        return $this->prepareFromText($field);
    }

    public function translateStGenericFilter($field, $value, $inclusive, $hint)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf(
            '%sST_%s(%s, ST_GeomFromText(?, ?))%s',
            $inclusive ? '' : 'NOT ',
            $hint,
            $field,
            $null
        );
        return [$fragment => GIS::split_ewkt($value)];
    }

    public function translateStContainsFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStCrossesFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStDisjointFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStDistanceFilter($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf('ST_Distance(ST_GeomFromText(?, ?),%s) %s ?%s', $field, $inclusive ? '<=' : '> ', $null);
        list($wkt, $srid) = GIS::split_ewkt($value[0]);
        $distance = $value[1];
        return [$fragment => [$wkt, $srid, $distance]];
    }

    public function translateStEqualsFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStGeometryTypeFilter($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf(
            '%sLOWER(ST_GeometryType(%s)) = ?%s',
            $inclusive ? '' : 'NOT ',
            $field,
            $null
        );
        return [$fragment => 'st_' . strtolower($value)];
    }
    public function translateStIntersectsFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStOverlapsFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStTouchesFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    public function translateStWithinFilter($field, $value, $inclusive, $hint = false)
    {
        return $this->translateStGenericFilter($field, $value, $inclusive, $hint);
    }

    protected function prepareFromText($field)
    {
        if (!$field->getValue()) {
            return ['?' => [null]];
        }

        list($wkt, $srid) = GIS::split_ewkt($field->getValue(), (int)$field->srid);

        if ($field instanceof DBGeometry) {
            return ['ST_GeomFromText(?, ?)' => [$wkt, $srid]];
        }

        if ($srid != 4326) {
            list($wkt) = GIS::split_ewkt(GIS::array_to_ewkt(GIS::reproject_array(GIS::ewkt_to_array($field->getValue()))));
        }
        return ['ST_GeogFromText(?)' => [$wkt]];
    }

}
