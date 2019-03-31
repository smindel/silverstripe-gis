<?php

namespace Smindel\GIS\ORM;

use SilverStripe\ORM\Connect\MySQLSchemaManager;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class MySQLGISSchemaManager extends MySQLSchemaManager
{
    use GISSchemaManager;

    public function geography($values)
    {
        // ATTENTION: GEOGRAPHY IS NOT SUPPORTED BY MYSQL. THIS IS STRICTLY FOR COMPATIBILITY
        return 'geometry';
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
        return [$fragment => strtolower($value)];
    }
}
