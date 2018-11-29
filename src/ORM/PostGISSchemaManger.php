<?php

namespace Smindel\GIS\ORM;

use SilverStripe\PostgreSQL\PostgreSQLSchemaManager;
use SilverStripe\ORM\DB;
use SilverStripe\Control\Director;

/*
http://postgis.net/docs/PostGIS_Special_Functions_Index.html#PostGIS_3D_Functions
*/

if (!class_exists(PostgreSQLSchemaManager::class)) {
    return;
}

class PostGISSchemaManger extends PostgreSQLSchemaManager
{
    public function geography($values)
    {
        return 'geometry';
    }

    public function translateToWrite($field)
    {
        return ["ST_GeomFromText(?)" => [$field->getValue()]];
    }

    public function translateToRead($field)
    {
        $table = $field->getTable();
        $column = $field->getName();
        $identifier = $table ? sprintf('"%s"."%s"', $table, $column) : sprintf('"%s"', $column);
        return sprintf('ST_AsEWKT(%s) "%s"', $identifier, $column);
    }

    public function translateFilterWithin($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        return [sprintf('%sST_Covers(ST_GeographyFromText(?),%s)%s', $inclusive ? '' : 'NOT ', $field, $null) => $value];
    }

    public function translateFilterIntersects($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        return [sprintf('%sST_Intersects(ST_GeographyFromText(?),%s)%s', $inclusive ? '' : 'NOT ', $field, $null) => $value];
    }

    public function translateFilterDWithin($field, $value, $inclusive)
    {
        $null = $inclusive ? '' : ' OR ' . DB::get_conn()->nullCheckClause($field, true);
        return [sprintf('ST_Distance(ST_GeographyFromText(?),%s,true) %s ?%s', $field, $inclusive ? '<=' : '> ', $null) => $value];
    }

    public function translateDistanceQuery($geo1,$geo2)
    {
        return sprintf("ST_Distance(ST_GeographyFromText('%s'),ST_GeographyFromText('%s'),true)", $geo1, $geo2);
    }

    public function schemaUpdate($callback)
    {
        parent::schemaUpdate($callback);
        // @todo: terrible hack to make the postgis extension manually installed in the "public" schema
        // abailable in the unit test db
        if (Director::is_cli() && !Director::isLive()) {
            \SilverStripe\ORM\DB::get_conn()->setSchemaSearchPath(\SilverStripe\ORM\DB::get_conn()->currentSchema(), 'public');
        }
    }
}
