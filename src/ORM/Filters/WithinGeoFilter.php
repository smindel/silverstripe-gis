<?php

namespace Smindel\GIS\ORM\Filters;

use SilverStripe\ORM\Filters\SearchFilter;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;

class WithinGeoFilter extends SearchFilter
{
    /**
     * Applies an exact match (equals) on a field value.
     *
     * @param DataQuery $query
     * @return DataQuery
     */
    protected function applyOne(DataQuery $query)
    {
        return $this->oneFilter($query, true);
    }

    /**
     * Excludes an exact match (equals) on a field value.
     *
     * @param DataQuery $query
     * @return DataQuery
     */
    protected function excludeOne(DataQuery $query)
    {
        return $this->oneFilter($query, false);
    }

    /**
     * Applies a single match, either as inclusive or exclusive
     *
     * @param DataQuery $query
     * @param bool $inclusive True if this is inclusive, or false if exclusive
     * @return DataQuery
     */
    protected function oneFilter(DataQuery $query, $inclusive)
    {
        $this->model = $query->applyRelation($this->relation);
        $field = $this->getDbName();
        $value = $this->getValue();

        // Null comparison check
        if ($value === null) {
            $where = DB::get_conn()->nullCheckClause($field, $inclusive);
            return $query->where($where);
        }

        // Value comparison check
        $where = DB::get_schema()->translateFilterWithin($field, $value, $inclusive);

        return $this->aggregate ?
            $this->applyAggregate($query, $where) :
            $query->where($where);
    }
}
