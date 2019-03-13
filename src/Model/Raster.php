<?php

namespace Smindel\GIS\Model;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\DB;
use Smindel\GIS\GIS;

class Raster
{
    use Configurable;

    use Injectable;

    private static $tile_renderer = 'raster_renderer';

    protected $tableName;

    protected $rasterColumn;

    protected $srid = 4326;

    protected $dimensions;

    protected $colorMap;

    public function searchableFields()
    {
        return [
            'Band' => 'Band',
        ];
    }

    public function getTableName()
    {
        return $this->tableName;
    }

    public function getRasterColumn()
    {
        return $this->rasterColumn;
    }

    public function getSrid()
    {
        return $this->srid;
    }

    public function getDimensions()
    {
        return $this->dimensions;
    }

    public function getColorMap()
    {
        return $this->colorMap;
    }

    public function ST_SRID()
    {
        $sql = sprintf('
            SELECT
                ST_SRID(%2$s) srid
            FROM %1$s
            LIMIT 1',
            $this->tableName,
            $this->rasterColumn
        );

        return DB::query($sql)->value();
    }

    public function ST_SummaryStats($geo = null, $band = 1)
    {
        $sql = sprintf('
            SELECT
                (ST_SummaryStats(%2$s, %3$d)).*
            FROM %1$s',
            $this->tableName,
            $this->rasterColumn,
            $band
        );

        $sql .= $geo ? sprintf('
            WHERE ST_Intersects(%1$s, ST_GeomFromText(\'%2$s\', %3$d))',
            $this->rasterColumn,
            ...GIS::split_ewkt(GIS::to_ewkt($geo))
        ) : '';

        return DB::query($sql)->first();
    }

    public function ST_Value($geo, $band = 1)
    {
        $split = GIS::split_ewkt(GIS::to_ewkt($geo));

        $sql = sprintf('
            SELECT
                ST_Value(
                    %2$s,
                    %3$d,
                    ST_GeomFromText(
                        \'%4$s\',
                        %5$d
                    )
                )
            FROM %1$s
            WHERE
                ST_Intersects(
                    %2$s,
                    ST_GeomFromText(
                        \'%4$s\',
                        %5$d
                    )
                )
            ',
            $this->tableName,
            $this->rasterColumn,
            $band,
            $split[0], $split[1]
        );

        return DB::query($sql)->value();
    }
}
