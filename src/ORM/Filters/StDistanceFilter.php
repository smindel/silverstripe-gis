<?php

namespace Smindel\GIS\ORM\Filters;

use SilverStripe\ORM\Filters\SearchFilter;
use SilverStripe\ORM\DataQuery;
use SilverStripe\ORM\DB;
use InvalidArgumentException;

class StDistanceFilter extends SearchFilter
{
    /**
     * Applies an exact match (equals) on a field value.
     *
     * @param DataQuery $query
     * @return DataQuery
     */
    protected function applyOne(DataQuery $query)
    {
        throw new InvalidArgumentException(static::class .
            " is used by supplying an array containing a geometry and a distance.");
    }

    protected function applyMany(DataQuery $query)
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
        throw new InvalidArgumentException(static::class .
            " is used by supplying an array containing a geometry and a distance.");
    }

    protected function excludeMany(DataQuery $query)
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

        // Value comparison check
        $where = DB::get_schema()->translateStDistanceFilter($field, $value, $inclusive);

        return $this->aggregate ?
            $this->applyAggregate($query, $where) : $query->where($where);
    }
}
