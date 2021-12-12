<?php

namespace Smindel\GIS\ORM;

use SilverStripe\ORM\DB;
use Smindel\GIS\ORM\FieldType\DBGeometry;
use Smindel\GIS\GIS;

trait GISSchemaManager
{
    public function initialise()
    {
    }

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
        return [$fragment => [GIS::create($value)->wkt, GIS::create($value)->srid]];
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
        $geo = GIS::create($value[0]);
        $distance = $value[1];
        return [$fragment => [$geo->wkt, $geo->srid, $distance]];
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

    public function translateDistanceQuery($geo1, $geo2)
    {
        $geo1 = GIS::create($geo1);
        $geo2 = GIS::create($geo2);
        return sprintf(
            "ST_Distance(ST_GeomFromText('%s', %d),ST_GeomFromText('%s', %d))",
            $geo1->wkt,
            $geo1->srid,
            $geo2->wkt,
            $geo2->srid
        );
    }

    public function translateBasicSelectGeo()
    {
        return 'CASE WHEN %s IS NULL THEN NULL ELSE CONCAT(\'SRID=\', ST_SRID(%s), \';\', ST_AsText(%s)) END AS "%s"';
    }
}
