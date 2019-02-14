<?php

namespace Smindel\GIS\ORM;

use SilverStripe\PostgreSQL\PostgreSQLSchemaManager;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Control\Director;
use SilverStripe\Core\Config\Config;
use Smindel\GIS\GIS;
use Smindel\GIS\ORM\FieldType\DBGeometry;
use Exception;

/*
http://postgis.net/docs/PostGIS_Special_Functions_Index.html#PostGIS_3D_Functions
*/

if (!class_exists(PostgreSQLSchemaManager::class)) {
    return;
}

class PostGISSchemaManager extends PostgreSQLSchemaManager
{
    use GISSchemaManager;

    public function translateDistanceQuery($geo1,$geo2)
    {
        list($wkt1, $srid1) = GIS::split_ewkt($geo1);
        list($wkt2, $srid2) = GIS::split_ewkt($geo2);
        return sprintf("ST_Distance(ST_GeomFromText('%s', %d),ST_GeomFromText('%s', %d),true)", $wkt1, $srid1, $wkt2, $srid2);
    }

    public function schemaUpdate($callback)
    {
        // @todo: terrible hack to make the postgis extension manually installed in the "public" schema
        // abailable in the unit test db
        if (Director::is_cli() && !Director::isLive()) {
            DB::get_conn()->setSchemaSearchPath(DB::get_conn()->currentSchema(), 'public');
        }
        parent::schemaUpdate($callback);
    }
}
