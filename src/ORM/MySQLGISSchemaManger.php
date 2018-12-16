<?php

namespace Smindel\GIS\ORM;

use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeography;

class MySQLGISSchemaManger extends MySQLSchemaManager
{
    public function geography($values)
    {
        return 'geometry';
    }

    public function translateToWrite($field)
    {
        list($wkt, $srid) = DBGeography::split_ewkt($field->getValue(), (int)$field->srid);
        return ["ST_GeomFromText(?, ?)" => [$wkt, $srid]];
    }

    public function translateToRead($field)
    {
        $table = $field->getTable();
        $column = $field->getName();
        $identifier = $table ? sprintf('"%s"."%s"', $table, $column) : sprintf('"%s"', $column);
        return sprintf('CONCAT(\'SRID=\', ST_SRID(%s), \';\', ST_AsText(%s)) "%s"', $identifier, $identifier, $column);
    }

    public function translateFilterWithin($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf('%sST_Contains(ST_GeomFromText(?, ?),%s)%s', $inclusive ? '' : 'NOT ', $field, $null);
        return [$fragment => DBGeography::split_ewkt($value)];
    }

    public function translateFilterGeoType($field, $value, $inclusive)
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

    public function translateFilterIntersects($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf('%sST_Intersects(ST_GeomFromText(?, ?),%s)%s', $inclusive ? '' : 'NOT ', $field, $null);
        return [$fragment => DBGeography::split_ewkt($value)];
    }

    public function translateFilterDWithin($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        $fragment = sprintf('ST_Distance_Sphere(ST_GeomFromText(?, ?),%s) %s ?%s', $field, $inclusive ? '<=' : '> ', $null);
        list($wkt, $srid) = DBGeography::split_ewkt($value[0]);
        $distance = $value[1];
        return [$fragment => [$wkt, $srid, $distance]];
    }

    public function translateDistanceQuery($geo1,$geo2)
    {
        list($wkt1, $srid1) = DBGeography::split_ewkt($geo1);
        list($wkt2, $srid2) = DBGeography::split_ewkt($geo2);
        return sprintf("ST_Distance_Sphere(ST_GeomFromText('%s', %d),ST_GeomFromText('%s', %d))", $wkt1, $srid1, $wkt2, $srid2);
    }
}
